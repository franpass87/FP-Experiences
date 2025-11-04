<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use Exception;
use FP_Exp\Integrations\Brevo;
use Throwable;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WC_Order;
use WC_Order_Item;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function __;
use function absint;
use function add_action;
use function apply_filters;
use function do_action;
use function current_time;
use function function_exists;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_current_user_id;
use function is_array;
use function is_wp_error;
use function json_decode;
use function nocache_headers;
use function number_format_i18n;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function strtotime;
use function wc_create_order;
use function wc_get_order;
use function wp_date;
use function wp_json_encode;
use function wp_mail;
use function wp_verify_nonce;

use const MINUTE_IN_SECONDS;

final class RequestToBook
{
    private Brevo $brevo;

    public function __construct(Brevo $brevo)
    {
        $this->brevo = $brevo;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_rest_route']);
    }

    public function register_rest_route(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/rtb/request',
            [
                'methods' => 'POST',
                'permission_callback' => [$this, 'check_rtb_permission'],
                'callback' => [$this, 'handle_request'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/rtb/quote',
            [
                'methods' => 'POST',
                'permission_callback' => [$this, 'check_rtb_permission'],
                'callback' => [$this, 'handle_quote'],
            ]
        );
    }

    public function check_rtb_permission(WP_REST_Request $request): bool
    {
        // Verifica nonce nell'header
        if (Helpers::verify_rest_nonce($request, 'fp-exp-rtb')) {
            return true;
        }

        // In alternativa verifica il nonce nel body della richiesta
        $nonce = $request->get_param('nonce');
        if (is_string($nonce) && $nonce && wp_verify_nonce($nonce, 'fp-exp-rtb')) {
            return true;
        }

        return false;
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle_request(WP_REST_Request $request)
    {
        nocache_headers();

        $nonce = (string) $request->get_param('nonce');

        if (! wp_verify_nonce($nonce, 'fp-exp-rtb')) {
            return new WP_Error('fp_exp_rtb_nonce', __('La sessione è scaduta. Aggiorna la pagina e riprova.', 'fp-experiences'), ['status' => 403]);
        }

        if (Helpers::hit_rate_limit('rtb_' . Helpers::client_fingerprint(), 5, MINUTE_IN_SECONDS)) {
            return new WP_Error('fp_exp_rtb_rate_limited', __('Attendi prima di inviare un’altra richiesta.', 'fp-experiences'), ['status' => 429]);
        }

        $experience_id = absint($request->get_param('experience_id'));
        $slot_id = absint($request->get_param('slot_id'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));
        $tickets = $this->normalize_array($request->get_param('tickets'));
        $addons = $this->normalize_array($request->get_param('addons'));

        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_rtb_invalid', __('Seleziona data e ora prima di inviare la richiesta.', 'fp-experiences'), ['status' => 400]);
        }

        if ($slot_id <= 0) {
            if (! $start || ! $end) {
                return new WP_Error('fp_exp_rtb_invalid', __('Seleziona data e ora prima di inviare la richiesta.', 'fp-experiences'), ['status' => 400]);
            }
            $slot_id = Slots::ensure_slot_for_occurrence($experience_id, $start, $end);
            
            // Handle WP_Error from ensure_slot_for_occurrence
            if (is_wp_error($slot_id)) {
                return $slot_id; // Pass through the detailed error
            }
            
            if ($slot_id <= 0) {
                return new WP_Error('fp_exp_rtb_slot', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), ['status' => 404]);
            }
        }

        $slot = Slots::get_slot($slot_id);
        if (! $slot || (int) $slot['experience_id'] !== $experience_id) {
            return new WP_Error('fp_exp_rtb_slot', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), ['status' => 404]);
        }

        $capacity = Slots::check_capacity($slot_id, $tickets);
        if (empty($capacity['allowed'])) {
            $message = isset($capacity['message']) ? (string) $capacity['message'] : __('Lo slot selezionato non può accettare altri partecipanti.', 'fp-experiences');

            return new WP_Error('fp_exp_rtb_capacity', $message, ['status' => 409]);
        }

        $contact = $this->sanitize_contact($request->get_param('contact'));

        if (! $contact['email']) {
            return new WP_Error('fp_exp_rtb_contact', __('Fornisci un indirizzo email valido per poterti rispondere.', 'fp-experiences'), ['status' => 400]);
        }

        if (! $contact['phone']) {
            return new WP_Error('fp_exp_rtb_contact', __('Fornisci un numero di telefono per poterti contattare.', 'fp-experiences'), ['status' => 400]);
        }

        if (empty($request->get_param('consent')['privacy'])) {
            return new WP_Error('fp_exp_rtb_consent', __('Devi accettare l’informativa privacy per inviare la richiesta.', 'fp-experiences'), ['status' => 400]);
        }

        $slot_start = $slot['start_datetime'] ?? '';
        $breakdown = Pricing::calculate_breakdown($experience_id, (string) $slot_start, $tickets, $addons);
        $pax_total = (int) ($breakdown['total_guests'] ?? 0);
        $grand_total = (float) ($breakdown['total'] ?? 0.0);

        $requested_mode = $request->get_param('mode');
        $rtb_mode = $this->resolve_mode_from_submission($experience_id, $requested_mode);
        $forced = ! empty($request->get_param('forced'));

        $timeout_minutes = Helpers::rtb_hold_timeout();
        $hold_expires = current_time('timestamp', true) + ($timeout_minutes * MINUTE_IN_SECONDS);

        $reservation_id = Reservations::create([
            'order_id' => 0,
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
            'status' => Reservations::STATUS_PENDING_REQUEST,
            'pax' => $tickets,
            'addons' => $addons,
            'utm' => array_merge(Helpers::read_utm_cookie(), ['rtb' => true]),
            'locale' => sanitize_text_field((string) get_option('WPLANG', '')), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            'total_gross' => $grand_total,
            'tax_total' => 0.0,
            'hold_expires_at' => gmdate('Y-m-d H:i:s', $hold_expires),
            'meta' => [
                'contact' => $contact,
                'notes' => sanitize_textarea_field((string) ($request->get_param('notes') ?? '')),
                'consent' => [
                    'marketing' => ! empty($request->get_param('consent')['marketing']),
                    'privacy' => true,
                ],
                'summary' => $breakdown,
                'rtb' => [
                    'mode' => $rtb_mode,
                    'forced' => (bool) $forced,
                ],
            ],
        ]);

        if ($reservation_id <= 0) {
            return new WP_Error('fp_exp_rtb_store', __('Impossibile registrare la richiesta. Riprova.', 'fp-experiences'), ['status' => 500]);
        }

        // FIX: Double-check capacity after creating reservation to prevent race condition overbooking
        // This catches cases where multiple simultaneous requests passed the initial capacity check
        if ($slot && !empty($tickets)) {
            $capacity_total = absint($slot['capacity_total']);
            
            if ($capacity_total > 0) {
                $snapshot = Slots::get_capacity_snapshot($slot_id);
                
                if ($snapshot['total'] > $capacity_total) {
                    // Overbooking detected! Rollback this reservation
                    Reservations::delete($reservation_id);
                    
                    return new WP_Error(
                        'fp_exp_rtb_capacity_exceeded',
                        __('Lo slot selezionato si è appena esaurito. Riprova con un altro orario.', 'fp-experiences'),
                        ['status' => 409]
                    );
                }
            }
        }

        $context = $this->build_context($reservation_id, $experience_id, $slot, $contact, $breakdown, (string) ($request->get_param('notes') ?? ''));
        $context['rtb'] = [
            'mode' => $rtb_mode,
            'forced' => (bool) $forced,
        ];
        $this->notify_customer($context, 'request');
        $this->notify_staff($context);

        do_action('fp_exp_rtb_request_created', $reservation_id, $context);

        Logger::log('rtb', 'Request-to-book created', [
            'reservation_id' => $reservation_id,
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
        ]);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Grazie! Abbiamo ricevuto la tua richiesta e il team confermerà la disponibilità a breve.', 'fp-experiences'),
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle_quote(WP_REST_Request $request)
    {
        nocache_headers();

        $nonce = (string) $request->get_param('nonce');

        if (! wp_verify_nonce($nonce, 'fp-exp-rtb')) {
            // Log per debug
            error_log('[FP-EXP] Quote nonce verification failed: ' . $nonce);
            return new WP_Error('fp_exp_rtb_nonce', __('La sessione è scaduta. Aggiorna la pagina e riprova.', 'fp-experiences'), ['status' => 403]);
        }

        if (Helpers::hit_rate_limit('rtb_quote_' . Helpers::client_fingerprint(), 20, MINUTE_IN_SECONDS)) {
            return new WP_Error('fp_exp_rtb_rate_limited', __('Attendi prima di richiedere un nuovo preventivo.', 'fp-experiences'), ['status' => 429]);
        }

        $experience_id = absint($request->get_param('experience_id'));
        $slot_id = absint($request->get_param('slot_id'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));
        $tickets = $this->normalize_array($request->get_param('tickets'));
        $addons = $this->normalize_array($request->get_param('addons'));

        // Log per debug
        error_log('[FP-EXP] Quote request params: ' . json_encode([
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
            'start' => $start,
            'end' => $end,
            'tickets' => $tickets,
            'addons' => $addons
        ]));

        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_rtb_invalid', __('Seleziona data e ora prima di proseguire.', 'fp-experiences'), ['status' => 400]);
        }

        if ($slot_id <= 0) {
            if (! $start || ! $end) {
                return new WP_Error('fp_exp_rtb_invalid', __('Seleziona data e ora prima di proseguire.', 'fp-experiences'), ['status' => 400]);
            }
            $slot_id = Slots::ensure_slot_for_occurrence($experience_id, $start, $end);
            
            // Handle WP_Error from ensure_slot_for_occurrence
            if (is_wp_error($slot_id)) {
                return $slot_id; // Pass through the detailed error
            }
            
            if ($slot_id <= 0) {
                return new WP_Error('fp_exp_rtb_slot', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), ['status' => 404]);
            }
        }

        $slot = Slots::get_slot($slot_id);
        if (! $slot || (int) $slot['experience_id'] !== $experience_id) {
            return new WP_Error('fp_exp_rtb_slot', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), ['status' => 404]);
        }

        try {
            $breakdown = Pricing::calculate_breakdown(
                $experience_id,
                (string) ($slot['start_datetime'] ?? ''),
                $tickets,
                $addons
            );

            if (! is_array($breakdown)) {
                error_log('[FP-EXP] Pricing calculation returned invalid result: ' . json_encode($breakdown));
                return new WP_Error('fp_exp_pricing_error', __('Errore nel calcolo del prezzo. Riprova.', 'fp-experiences'), ['status' => 500]);
            }

            return rest_ensure_response([
                'success' => true,
                'breakdown' => $breakdown,
            ]);
        } catch (Throwable $e) {
            error_log('[FP-EXP] Pricing calculation exception: ' . $e->getMessage());
            return new WP_Error('fp_exp_pricing_error', __('Errore nel calcolo del prezzo. Riprova.', 'fp-experiences'), ['status' => 500]);
        }
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalize_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function sanitize_contact($value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'name' => sanitize_text_field((string) ($value['name'] ?? ($value['full_name'] ?? ''))),
            'email' => sanitize_email((string) ($value['email'] ?? '')),
            'phone' => sanitize_text_field((string) ($value['phone'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $slot
     *
     * @return array<string, mixed>
     */
    private function format_slot(array $slot): array
    {
        $start = isset($slot['start_datetime']) ? (string) $slot['start_datetime'] : '';
        $end = isset($slot['end_datetime']) ? (string) $slot['end_datetime'] : '';
        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');

        if ($start) {
            $timestamp = strtotime($start . ' UTC');

            if ($timestamp) {
                $slot['start_label'] = wp_date($date_format . ' ' . $time_format, $timestamp);
                $slot['start_local_date'] = wp_date($date_format, $timestamp);
                $slot['start_local_time'] = wp_date($time_format, $timestamp);
            }
        }

        if ($end) {
            $end_timestamp = strtotime($end . ' UTC');

            if ($end_timestamp) {
                $slot['end_local_date'] = wp_date($date_format, $end_timestamp);
                $slot['end_local_time'] = wp_date($time_format, $end_timestamp);
            }
        }

        return $slot;
    }

    private function resolve_mode_from_submission(int $experience_id, $requested_mode): string
    {
        $requested = is_string($requested_mode) ? sanitize_key($requested_mode) : '';

        if (in_array($requested, ['confirm', 'pay_later'], true)) {
            return $requested;
        }

        $mode = Helpers::rtb_mode_for_experience($experience_id);

        if (in_array($mode, ['confirm', 'pay_later'], true)) {
            return $mode;
        }

        return 'confirm';
    }

    /**
     * @param array<string, mixed> $reservation
     */
    private function resolve_mode(array $reservation): string
    {
        $meta = is_array($reservation['meta'] ?? null) ? $reservation['meta'] : [];

        if (isset($meta['rtb']) && is_array($meta['rtb'])) {
            $candidate = sanitize_key((string) ($meta['rtb']['mode'] ?? ''));

            if (in_array($candidate, ['confirm', 'pay_later'], true)) {
                return $candidate;
            }
        }

        $experience_id = absint($reservation['experience_id'] ?? 0);
        $mode = Helpers::rtb_mode_for_experience($experience_id);

        return in_array($mode, ['confirm', 'pay_later'], true) ? $mode : 'confirm';
    }

    /**
     * @param array<string, mixed> $reservation
     */
    public function resolve_mode_for_reservation(array $reservation): string
    {
        return $this->resolve_mode($reservation);
    }

    public function get_request_context(int $reservation_id): ?array
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation) {
            return null;
        }

        $slot = Slots::get_slot(absint($reservation['slot_id'] ?? 0));

        if (! $slot) {
            return null;
        }

        $experience_id = absint($reservation['experience_id'] ?? 0);
        $meta = is_array($reservation['meta'] ?? null) ? $reservation['meta'] : [];
        $contact = isset($meta['contact']) && is_array($meta['contact']) ? $meta['contact'] : [];
        $notes = isset($meta['notes']) ? (string) $meta['notes'] : '';
        $summary = isset($meta['summary']) && is_array($meta['summary']) ? $meta['summary'] : [];

        if (! $summary) {
            $tickets = is_array($reservation['pax'] ?? null) ? $reservation['pax'] : [];
            $addons = is_array($reservation['addons'] ?? null) ? $reservation['addons'] : [];
            $summary = Pricing::calculate_breakdown(
                $experience_id,
                (string) ($slot['start_datetime'] ?? ''),
                $tickets,
                $addons
            );
        }

        $context = $this->build_context($reservation_id, $experience_id, $slot, $contact, $summary, $notes);
        $context['reservation']['status'] = $reservation['status'] ?? Reservations::STATUS_PENDING_REQUEST;
        $context['reservation']['hold_expires_at'] = $reservation['hold_expires_at'] ?? null;
        $context['rtb'] = isset($meta['rtb']) && is_array($meta['rtb']) ? $meta['rtb'] : [];

        if (! empty($reservation['order_id'])) {
            $order = wc_get_order((int) $reservation['order_id']);

            if ($order instanceof WC_Order) {
                $context['order'] = [
                    'id' => $order->get_id(),
                    'number' => $order->get_order_number(),
                    'total' => (float) $order->get_total(),
                    'currency' => $order->get_currency(),
                ];
                $context['payment_url'] = $order->get_checkout_payment_url();
            }
        }

        return $context;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function approve(int $reservation_id)
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation) {
            return new WP_Error('fp_exp_rtb_missing', __('The request could not be found.', 'fp-experiences'));
        }

        $context = $this->get_request_context($reservation_id);

        if (! $context) {
            return new WP_Error('fp_exp_rtb_context', __('Unable to load the request details.', 'fp-experiences'));
        }

        $status = Reservations::normalize_status((string) ($reservation['status'] ?? ''));
        $mode = $this->resolve_mode($reservation);
        $stage = 'approved';

        if ('pay_later' === $mode) {
            $order = $this->ensure_payment_order($reservation_id, $reservation, $context);

            if (is_wp_error($order)) {
                return $order;
            }

            $stage = 'payment';
            $context['order'] = [
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'total' => (float) $order->get_total(),
                'currency' => $order->get_currency(),
            ];
            $context['payment_url'] = $order->get_checkout_payment_url();
        }

        Reservations::update_fields($reservation_id, [
            'status' => ('pay_later' === $mode) ? Reservations::STATUS_APPROVED_PENDING_PAYMENT : Reservations::STATUS_APPROVED_CONFIRMED,
            'hold_expires_at' => null,
        ]);

        Reservations::update_meta($reservation_id, [
            'rtb_decision' => [
                'mode' => $mode,
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql', true),
            ],
        ]);

        $context['reservation']['status'] = ('pay_later' === $mode)
            ? Reservations::STATUS_APPROVED_PENDING_PAYMENT
            : Reservations::STATUS_APPROVED_CONFIRMED;

        $this->notify_customer($context, $stage);

        do_action('fp_exp_rtb_request_approved', $reservation_id, $context, $mode);

        Logger::log('rtb', 'Request-to-book approved', [
            'reservation_id' => $reservation_id,
            'mode' => $mode,
            'status' => $status,
        ]);

        return $context;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function decline(int $reservation_id, string $reason = '')
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation) {
            return new WP_Error('fp_exp_rtb_missing', __('The request could not be found.', 'fp-experiences'));
        }

        $context = $this->get_request_context($reservation_id);

        if (! $context) {
            return new WP_Error('fp_exp_rtb_context', __('Unable to load the request details.', 'fp-experiences'));
        }

        $reason = trim($reason);

        if ($reason) {
            $reason = sanitize_textarea_field($reason);
            $context['notes'] = trim($context['notes'] . "\n" . $reason);
            $context['decline_reason'] = $reason;
        }

        Reservations::update_fields($reservation_id, [
            'status' => Reservations::STATUS_DECLINED,
            'hold_expires_at' => null,
        ]);

        Reservations::update_meta($reservation_id, [
            'rtb_decision' => [
                'mode' => $this->resolve_mode($reservation),
                'declined_by' => get_current_user_id(),
                'declined_at' => current_time('mysql', true),
                'reason' => $reason,
            ],
        ]);

        $context['reservation']['status'] = Reservations::STATUS_DECLINED;

        $this->notify_customer($context, 'declined');

        do_action('fp_exp_rtb_request_declined', $reservation_id, $context, $reason);

        Logger::log('rtb', 'Request-to-book declined', [
            'reservation_id' => $reservation_id,
        ]);

        return $context;
    }

    /**
     * @param array<string, mixed> $reservation
     * @param array<string, mixed> $context
     *
     * @return WC_Order|WP_Error
     */
    private function ensure_payment_order(int $reservation_id, array $reservation, array &$context)
    {
        $existing_id = absint($reservation['order_id'] ?? 0);

        if ($existing_id > 0) {
            $existing = wc_get_order($existing_id);

            if ($existing instanceof WC_Order) {
                return $existing;
            }
        }

        if (! function_exists('wc_create_order')) {
            return new WP_Error('fp_exp_rtb_order', __('WooCommerce is required to send payment requests.', 'fp-experiences'));
        }

        try {
            $order = wc_create_order([
                'status' => 'pending',
            ]);
        } catch (Exception $exception) {
            return new WP_Error('fp_exp_rtb_order', __('Impossibile generare l’ordine di pagamento. Riprova.', 'fp-experiences'));
        }

        if (is_wp_error($order)) {
            return new WP_Error('fp_exp_rtb_order', __('Impossibile generare l’ordine di pagamento. Riprova.', 'fp-experiences'));
        }

        $order->set_created_via('fp-exp-rtb');
        $currency = $context['totals']['currency'] ?? get_option('woocommerce_currency', 'EUR');
        $order->set_currency($currency);
        $order->set_shipping_total(0.0);
        $order->set_shipping_tax(0.0);

        $experience_id = absint($reservation['experience_id'] ?? 0);
        $slot_id = absint($reservation['slot_id'] ?? 0);
        $tickets = is_array($reservation['pax'] ?? null) ? $reservation['pax'] : [];
        $addons = is_array($reservation['addons'] ?? null) ? $reservation['addons'] : [];
        $slot = $slot_id > 0 ? Slots::get_slot($slot_id) : null;

        if ($experience_id > 0 && $slot) {
            $breakdown = Pricing::calculate_breakdown(
                $experience_id,
                (string) ($slot['start_datetime'] ?? ''),
                $tickets,
                $addons
            );

            $context['totals']['total'] = (float) ($breakdown['total'] ?? $context['totals']['total'] ?? 0.0);
            $context['totals']['currency'] = $breakdown['currency'] ?? $context['totals']['currency'] ?? $currency;
            $context['totals']['guests'] = (int) ($breakdown['total_guests'] ?? $context['totals']['guests'] ?? 0);
            $context['tickets'] = $breakdown['tickets'] ?? $context['tickets'] ?? [];
            $context['addons'] = $breakdown['addons'] ?? $context['addons'] ?? [];
        }

        $contact = isset($context['customer']) && is_array($context['customer']) ? $context['customer'] : [];
        $names = $this->split_contact_name($contact);

        $order->set_billing_first_name($names['first_name']);
        $order->set_billing_last_name($names['last_name']);
        $order->set_billing_email($contact['email'] ?? '');
        $order->set_billing_phone($contact['phone'] ?? '');
        $order->set_billing_country('');
        $order->set_billing_postcode('');
        $order->set_billing_city('');
        $order->set_billing_address_1('');

        $item = new WC_Order_Item();
        $item->set_type('fp_experience_item');
        $item->set_name($context['experience']['title'] ?? __('Experience booking', 'fp-experiences'));

        $total = (float) ($context['totals']['total'] ?? 0.0);
        $item->set_total($total);
        $item->set_subtotal($total);

        $slot = $context['slot'] ?? [];
        $item->add_meta_data('experience_id', absint($context['experience']['id'] ?? 0), true);
        $item->add_meta_data('experience_title', sanitize_text_field((string) ($context['experience']['title'] ?? '')), true);
        $item->add_meta_data('slot_id', absint($reservation['slot_id'] ?? 0), true);
        $item->add_meta_data('slot_start', sanitize_text_field((string) ($slot['start_datetime'] ?? '')), true);
        $item->add_meta_data('slot_end', sanitize_text_field((string) ($slot['end_datetime'] ?? '')), true);
        $item->add_meta_data('tickets', $context['tickets'] ?? [], true);
        $item->add_meta_data('addons', $context['addons'] ?? [], true);

        $order->add_item($item);

        $order->set_total($total);

        $order->update_meta_data('_fp_exp_contact', $this->prepare_contact_meta($contact));
        $order->update_meta_data('_fp_exp_isolated_checkout', 'yes');
        $order->update_meta_data('_fp_exp_consent_marketing', ! empty($reservation['meta']['consent']['marketing']) ? 'yes' : 'no');

        if (! empty($reservation['utm'])) {
            $order->update_meta_data('_fp_exp_utm', $reservation['utm']);
        }

        $order->save();

        Reservations::update_fields($reservation_id, [
            'order_id' => $order->get_id(),
            'total_gross' => $total,
            'tax_total' => 0.0,
        ]);

        Logger::log('rtb', 'Request-to-book payment order created', [
            'reservation_id' => $reservation_id,
            'order_id' => $order->get_id(),
        ]);

        return $order;
    }

    /**
     * @param array<string, mixed> $contact
     *
     * @return array{first_name:string,last_name:string}
     */
    private function split_contact_name(array $contact): array
    {
        $name = trim((string) ($contact['name'] ?? ''));

        if ('' === $name) {
            return [
                'first_name' => '',
                'last_name' => '',
            ];
        }

        $parts = preg_split('/\s+/', $name);
        $first = array_shift($parts) ?: '';
        $last = trim(implode(' ', $parts));

        if ('' === $last) {
            $last = $first;
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
        ];
    }

    /**
     * @param array<string, mixed> $contact
     *
     * @return array<string, string>
     */
    private function prepare_contact_meta(array $contact): array
    {
        $names = $this->split_contact_name($contact);

        return [
            'first_name' => $names['first_name'],
            'last_name' => $names['last_name'],
            'email' => (string) ($contact['email'] ?? ''),
            'phone' => (string) ($contact['phone'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $slot
     * @param array<string, mixed> $breakdown
     * @param array<string, string> $contact
     *
     * @return array<string, mixed>
     */
    private function build_context(int $reservation_id, int $experience_id, array $slot, array $contact, array $breakdown, string $notes): array
    {
        $experience = get_post($experience_id);
        $slot = $this->format_slot($slot);
        $currency = $breakdown['currency'] ?? get_option('woocommerce_currency', 'EUR');

        return [
            'reservation_id' => $reservation_id,
            'reservation' => [
                'id' => $reservation_id,
            ],
            'experience' => [
                'id' => $experience_id,
                'title' => $experience ? $experience->post_title : '',
                'permalink' => $experience ? get_permalink($experience) : '',
                'meeting_point' => Repository::get_primary_summary_for_experience($experience_id),
            ],
            'slot' => $slot,
            'customer' => $contact,
            'totals' => [
                'guests' => (int) ($breakdown['total_guests'] ?? 0),
                'total' => (float) ($breakdown['total'] ?? 0.0),
                'currency' => $currency,
            ],
            'tickets' => $breakdown['tickets'] ?? [],
            'addons' => $breakdown['addons'] ?? [],
            'notes' => $notes,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function notify_customer(array $context, string $stage): void
    {
        $settings = Helpers::rtb_settings();
        $templates = $settings['templates'] ?? [];
        $template_id = isset($templates[$stage]) ? absint($templates[$stage]) : 0;

        if ($this->brevo->is_enabled() && $template_id > 0) {
            $sent = $this->brevo->send_rtb_notification($stage, $context, (int) $context['reservation_id'], $template_id);
            if ($sent) {
                return;
            }
        }

        $fallbacks = $settings['fallback'] ?? [];
        $message = $fallbacks[$stage] ?? [];

        $subject = ! empty($message['subject']) ? $message['subject'] : __('We received your experience request', 'fp-experiences');
        $body = ! empty($message['body']) ? $message['body'] : __('Grazie per la richiesta. Il nostro team ti ricontatterà a breve.', 'fp-experiences');

        $payload = $this->replace_tokens($subject, $body, $context);

        if ($payload['body']) {
            wp_mail($context['customer']['email'], $payload['subject'], $payload['body']);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function notify_staff(array $context): void
    {
        $emails = get_option('fp_exp_emails', []);
        $emails = is_array($emails) ? $emails : [];
        $structure = '';
        $webmaster = '';
        if (! empty($emails['sender']['structure'])) {
            $structure = sanitize_email((string) $emails['sender']['structure']);
        }
        if (! empty($emails['sender']['webmaster'])) {
            $webmaster = sanitize_email((string) $emails['sender']['webmaster']);
        }
        if (! $structure) {
            $structure = sanitize_email((string) get_option('fp_exp_structure_email', ''));
        }
        if (! $webmaster) {
            $webmaster = sanitize_email((string) get_option('fp_exp_webmaster_email', ''));
        }

        $recipients = array_filter(apply_filters('fp_exp_email_recipients', [$structure, $webmaster], $context['reservation_id'], 0));

        if (! $recipients) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: experience title. */
            __('New request-to-book for %s', 'fp-experiences'),
            $context['experience']['title']
        );

        $lines = [];
        $lines[] = sprintf(__('Customer: %s (%s)', 'fp-experiences'), $context['customer']['name'] ?: __('Unknown', 'fp-experiences'), $context['customer']['email']);
        if (! empty($context['customer']['phone'])) {
            $lines[] = sprintf(__('Phone: %s', 'fp-experiences'), $context['customer']['phone']);
        }
        $lines[] = sprintf(__('Ospiti: %d', 'fp-experiences'), (int) ($context['totals']['guests'] ?? 0));
        $lines[] = sprintf(__('Requested slot: %s', 'fp-experiences'), $context['slot']['start_label'] ?? $context['slot']['start_datetime'] ?? '');
        if (! empty($context['notes'])) {
            $lines[] = __('Notes:', 'fp-experiences');
            $lines[] = $context['notes'];
        }

        $body = implode("\n", $lines);

        wp_mail($recipients, $subject, $body);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{subject:string, body:string}
     */
    private function replace_tokens(string $subject, string $body, array $context): array
    {
        $slotLabel = $context['slot']['start_label'] ?? $context['slot']['start_datetime'] ?? '';
        $total_value = (float) ($context['totals']['total'] ?? 0.0);
        $currency = (string) ($context['totals']['currency'] ?? '');
        $formatted_total = number_format_i18n($total_value, 2);
        if ($currency) {
            $formatted_total .= ' ' . $currency;
        }
        $replacements = [
            '{customer_name}' => $context['customer']['name'] ?? '',
            '{experience}' => $context['experience']['title'] ?? '',
            '{date}' => $slotLabel,
            '{time}' => $context['slot']['start_local_time'] ?? '',
            '{guests}' => (string) ($context['totals']['guests'] ?? 0),
            '{notes}' => $context['notes'] ?? '',
            '{payment_url}' => $context['payment_url'] ?? '',
            '{total}' => $formatted_total,
        ];

        foreach ($replacements as $token => $value) {
            $subject = str_replace($token, (string) $value, $subject);
            $body = str_replace($token, (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
