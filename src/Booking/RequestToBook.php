<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use Exception;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Services\Options\OptionsInterface;
use Throwable;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WC_Order;
use WC_Order_Item_Product;
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

final class RequestToBook implements HookableInterface
{
    private Brevo $brevo;
    private ?OptionsInterface $options = null;

    /**
     * RequestToBook constructor.
     *
     * @param Brevo $brevo Brevo integration
     * @param OptionsInterface|null $options Optional OptionsInterface (will try to get from container if not provided)
     */
    public function __construct(Brevo $brevo, ?OptionsInterface $options = null)
    {
        $this->brevo = $brevo;
        $this->options = $options;
    }

    /**
     * Get OptionsInterface instance.
     * Tries container first, falls back to direct instantiation for backward compatibility.
     */
    private function getOptions(): OptionsInterface
    {
        if ($this->options !== null) {
            return $this->options;
        }

        // Try to get from container
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(OptionsInterface::class)) {
                try {
                    $this->options = $container->make(OptionsInterface::class);
                    return $this->options;
                } catch (\Throwable $e) {
                    // Fall through to direct instantiation
                }
            }
        }

        // Fallback to direct instantiation
        $this->options = new \FP_Exp\Services\Options\Options();
        return $this->options;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_rest_route']);
    }

    public function register_rest_route(): void
    {
        $this->debug_log('Registering RTB endpoints');
        
        // Usiamo __return_true come permission_callback perché:
        // 1. WordPress REST middleware controlla i cookie PRIMA del permission_callback
        // 2. Se l'utente è loggato, WP si aspetta un nonce wp_rest nell'header
        // 3. Noi usiamo un nonce custom nel body, verificato nell'handler
        register_rest_route(
            'fp-exp/v1',
            '/rtb/request',
            [
                'methods' => 'POST',
                'permission_callback' => '__return_true',
                'callback' => [$this, 'handle_request'],
            ]
        );
        
        $this->debug_log('RTB request endpoint registered');

        register_rest_route(
            'fp-exp/v1',
            '/rtb/quote',
            [
                'methods' => 'POST',
                'permission_callback' => '__return_true',
                'callback' => [$this, 'handle_quote'],
            ]
        );
    }

    public function check_rtb_permission(WP_REST_Request $request): bool
    {
        $this->debug_log('RTB permission check invoked', [
            'method' => $request->get_method(),
            'referer_present' => (bool) $request->get_header('referer'),
        ]);
        
        // Verifica nonce nell'header
        if (Helpers::verify_rest_nonce($request, 'fp-exp-rtb')) {
            $this->debug_log('RTB permission granted via header nonce');
            return true;
        }

        // In alternativa verifica il nonce nel body della richiesta
        $nonce = $request->get_param('nonce');
        if (is_string($nonce) && $nonce && wp_verify_nonce($nonce, 'fp-exp-rtb')) {
            $this->debug_log('RTB permission granted via body nonce');
            return true;
        }

        $this->debug_log('RTB permission denied: nonce missing or invalid');
        return false;
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle_request(WP_REST_Request $request)
    {
        $this->debug_log('RTB handle_request invoked');
        nocache_headers();

        // Verifica referer per protezione CSRF base
        $referer = $request->get_header('referer');
        $referer_host = $referer ? wp_parse_url($referer, PHP_URL_HOST) : '';
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($referer_host && $referer_host !== $home_host) {
            $this->debug_log('RTB request denied: invalid referer', ['referer' => $referer]);
            return new WP_Error('fp_exp_rtb_invalid_referer', __('Richiesta non valida. Ricarica la pagina e riprova.', 'fp-experiences'), ['status' => 403]);
        }

        // Rate limiting per prevenire abusi
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

        // Rileva la lingua con cui il cliente sta navigando
        // Usa il prefisso telefonico come fallback se il plugin multilingua non rileva la lingua
        $phone_number = $contact['phone'] ?? '';
        $customer_locale = $this->detect_customer_locale($phone_number);
        $this->debug_log('RTB customer locale detected', [
            'locale' => $customer_locale,
            'phone' => $phone_number ? 'present' : 'missing',
        ]);

        $reservation_id = Reservations::create([
            'order_id' => 0,
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
            'status' => Reservations::STATUS_PENDING_REQUEST,
            'pax' => $tickets,
            'addons' => $addons,
            'utm' => array_merge(Helpers::read_utm_cookie(), ['rtb' => true]),
            'locale' => $customer_locale,
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

        // La quote è una richiesta di sola lettura (calcolo prezzo), non richiede nonce
        // Protetta solo da rate limiting per prevenire abusi
        if (Helpers::hit_rate_limit('rtb_quote_' . Helpers::client_fingerprint(), 30, MINUTE_IN_SECONDS)) {
            return new WP_Error('fp_exp_rtb_rate_limited', __('Attendi prima di richiedere un nuovo preventivo.', 'fp-experiences'), ['status' => 429]);
        }

        $experience_id = absint($request->get_param('experience_id'));
        $slot_id = absint($request->get_param('slot_id'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));
        $tickets = $this->normalize_array($request->get_param('tickets'));
        $addons = $this->normalize_array($request->get_param('addons'));

        $this->debug_log('RTB quote request received', [
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
            'start' => $start,
            'end' => $end,
            'tickets_total' => array_sum(array_map('intval', $tickets)),
            'addons_count' => count($addons),
        ]);

        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_rtb_invalid', __('Seleziona data e ora prima di proseguire.', 'fp-experiences'), ['status' => 400]);
        }

        if ($slot_id <= 0) {
            if (! $start || ! $end) {
                $this->debug_log('RTB quote error: missing start/end', [
                    'start' => $start,
                    'end' => $end,
                ]);
                return new WP_Error('fp_exp_rtb_invalid', __('Seleziona data e ora prima di proseguire.', 'fp-experiences'), ['status' => 400]);
            }
            
            // Convert dates to UTC format if they contain timezone info
            try {
                // Se start è solo una data (senza orario), cerca di costruirla da end
                if ($start && strpos($start, 'T') === false && $end && strpos($end, 'T') !== false) {
                    // start è solo data (es. "2026-01-29"), end ha orario completo (es. "2026-01-29T18:00:00+01:00")
                    // Estrai la data da start e costruisci start con l'orario di inizio
                    // Per ora, usa la data di start con l'orario di end meno 2 ore (durata tipica)
                    $end_dt = new \DateTimeImmutable($end);
                    // Usa la data di start ma con l'orario calcolato da end (meno durata)
                    $start = $end_dt->modify('-2 hours')->format('Y-m-d\TH:i:sP');
                }
                
                // Try to parse as ISO datetime with timezone
                $start_dt = new \DateTimeImmutable($start);
                $end_dt = new \DateTimeImmutable($end);
                
                // Convert to UTC
                $start = $start_dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $end = $end_dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, return error
                $this->debug_log('RTB quote: date parsing failed', [
                    'start' => $start,
                    'end' => $end,
                    'error' => $e->getMessage(),
                ]);
                return new WP_Error('fp_exp_rtb_invalid', __('Formato data non valido. Riprova.', 'fp-experiences'), ['status' => 400]);
            }
            
            // Verifica che end sia dopo start
            $start_dt_check = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
            $end_dt_check = new \DateTimeImmutable($end, new \DateTimeZone('UTC'));
            if ($end_dt_check <= $start_dt_check) {
                $this->debug_log('RTB quote error: end is not after start', [
                    'experience_id' => $experience_id,
                    'start' => $start,
                    'end' => $end,
                    'start_iso' => $start_dt_check->format('c'),
                    'end_iso' => $end_dt_check->format('c'),
                ]);
                return new WP_Error('fp_exp_rtb_invalid', __('La data di fine deve essere dopo la data di inizio.', 'fp-experiences'), ['status' => 400]);
            }
            
            $this->debug_log('RTB quote ensuring slot for occurrence', [
                'experience_id' => $experience_id,
                'start' => $start,
                'end' => $end,
            ]);
            
            $slot_id = Slots::ensure_slot_for_occurrence($experience_id, $start, $end);
            
            // Handle WP_Error from ensure_slot_for_occurrence
            if (is_wp_error($slot_id)) {
                $this->debug_log('RTB quote error from ensure_slot_for_occurrence', [
                    'error_code' => $slot_id->get_error_code(),
                    'error_message' => $slot_id->get_error_message(),
                    'error_data' => $slot_id->get_error_data(),
                ]);
                return $slot_id; // Pass through the detailed error
            }
            
            if ($slot_id <= 0) {
                $this->debug_log('RTB quote error: ensure_slot_for_occurrence returned zero', [
                    'experience_id' => $experience_id,
                    'start' => $start,
                    'end' => $end,
                ]);
                return new WP_Error('fp_exp_rtb_slot', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), ['status' => 404]);
            }
            
            $this->debug_log('RTB quote slot ensured successfully', [
                'slot_id' => $slot_id,
            ]);
        }

        $slot = Slots::get_slot($slot_id);
        if (! $slot || (int) $slot['experience_id'] !== $experience_id) {
            $this->debug_log('RTB quote error: slot not found or mismatch', [
                'slot' => $slot,
                'expected_experience' => $experience_id,
            ]);
            return new WP_Error('fp_exp_rtb_slot', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), ['status' => 404]);
        }

        $this->debug_log('RTB quote calculating pricing', [
            'slot_id' => $slot_id,
            'tickets_total' => array_sum(array_map('intval', $tickets)),
        ]);

        try {
            $breakdown = Pricing::calculate_breakdown(
                $experience_id,
                (string) ($slot['start_datetime'] ?? ''),
                $tickets,
                $addons
            );

            if (! is_array($breakdown)) {
                $this->debug_log('RTB quote error: pricing calculation returned invalid result', [
                    'breakdown' => $breakdown,
                    'experience_id' => $experience_id,
                    'slot_id' => $slot_id,
                ]);
                return new WP_Error('fp_exp_pricing_error', __('Errore nel calcolo del prezzo. Riprova.', 'fp-experiences'), ['status' => 500]);
            }

            $this->debug_log('RTB quote pricing calculated successfully', [
                'total' => $breakdown['total'] ?? 0,
            ]);

            return rest_ensure_response([
                'success' => true,
                'breakdown' => $breakdown,
            ]);
        } catch (Throwable $e) {
            $this->debug_log('RTB quote pricing exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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

        $item = new WC_Order_Item_Product();
        $item->set_name($context['experience']['title'] ?? __('Experience booking', 'fp-experiences'));

        $total = (float) ($context['totals']['total'] ?? 0.0);
        $item->set_total($total);
        $item->set_subtotal($total);
        $item->add_meta_data('_fp_exp_item_type', 'rtb', true);

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

        // Recupera il titolo tradotto dell'esperienza (se disponibile)
        $experience_title = '';
        if ($experience) {
            $experience_title = $this->get_translated_title($experience, $reservation_id);
        }

        return [
            'reservation_id' => $reservation_id,
            'reservation' => [
                'id' => $reservation_id,
            ],
            'experience' => [
                'id' => $experience_id,
                'title' => $experience_title,
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
        // Recupera la locale del cliente dalla reservation
        $reservation_id = (int) ($context['reservation_id'] ?? 0);
        $customer_locale = '';
        
        if ($reservation_id > 0) {
            $reservation = Reservations::get($reservation_id);
            $customer_locale = $reservation['locale'] ?? '';
        }

        // Switcha alla lingua del cliente per le email
        $original_locale = get_locale();
        if ($customer_locale && function_exists('switch_to_locale')) {
            switch_to_locale($customer_locale);
        }

        $settings = Helpers::rtb_settings();
        $templates = $settings['templates'] ?? [];
        $template_id = isset($templates[$stage]) ? absint($templates[$stage]) : 0;

        if ($this->brevo->is_enabled() && $template_id > 0) {
            // Aggiungi la locale al context per Brevo
            $context['language_locale'] = $customer_locale;
            $context['locale'] = $customer_locale;
            
            $sent = $this->brevo->send_rtb_notification($stage, $context, (int) $context['reservation_id'], $template_id);
            if ($sent) {
                // Ripristina la locale originale
                if (function_exists('restore_current_locale') && $customer_locale && $customer_locale !== $original_locale) {
                    restore_current_locale();
                }
                return;
            }
        }

        $fallbacks = $settings['fallback'] ?? [];
        $message = $fallbacks[$stage] ?? [];

        // Testi di default traducibili per ogni stage
        $default_subjects = [
            'request' => __('We received your experience request', 'fp-experiences'),
            'approved' => __('Your experience request has been approved', 'fp-experiences'),
            'declined' => __('Update on your experience request', 'fp-experiences'),
            'payment' => __('Payment required for your experience booking', 'fp-experiences'),
        ];

        $default_bodies = [
            'request' => __('Thank you for your request. Our team will review it and get back to you shortly.', 'fp-experiences'),
            'approved' => __('Great news! Your experience request has been approved. We look forward to welcoming you.', 'fp-experiences'),
            'declined' => __('We regret to inform you that we are unable to accommodate your experience request at this time.', 'fp-experiences'),
            'payment' => __('Your experience request has been approved. Please complete your payment using the link below.', 'fp-experiences') . "\n\n{payment_url}",
        ];

        $subject = ! empty($message['subject']) ? $message['subject'] : ($default_subjects[$stage] ?? __('We received your experience request', 'fp-experiences'));
        $body = ! empty($message['body']) ? $message['body'] : ($default_bodies[$stage] ?? __('Thank you for your request. Our team will review it and get back to you shortly.', 'fp-experiences'));

        $payload = $this->replace_tokens($subject, $body, $context);

        if ($payload['body']) {
            wp_mail($context['customer']['email'], $payload['subject'], $payload['body']);
        }

        // Ripristina la locale originale
        if (function_exists('restore_current_locale') && $customer_locale && $customer_locale !== $original_locale) {
            restore_current_locale();
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function notify_staff(array $context): void
    {
        $emails = $this->getOptions()->get('fp_exp_emails', []);
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
            $structure = sanitize_email((string) $this->getOptions()->get('fp_exp_structure_email', ''));
        }
        if (! $webmaster) {
            $webmaster = sanitize_email((string) $this->getOptions()->get('fp_exp_webmaster_email', ''));
        }

        $recipients = array_filter(apply_filters('fp_exp_email_recipients', [$structure, $webmaster], $context['reservation_id'], 0));

        if (! $recipients) {
            return;
        }

        // Forza la locale italiana per le email staff
        $original_locale = get_locale();
        if (function_exists('switch_to_locale')) {
            switch_to_locale('it_IT');
        }

        $subject = sprintf(
            /* translators: %s: experience title. */
            __('Nuova richiesta per %s', 'fp-experiences'),
            $context['experience']['title']
        );

        $lines = [];
        $lines[] = sprintf(__('Cliente: %s (%s)', 'fp-experiences'), $context['customer']['name'] ?: __('Sconosciuto', 'fp-experiences'), $context['customer']['email']);
        if (! empty($context['customer']['phone'])) {
            $lines[] = sprintf(__('Telefono: %s', 'fp-experiences'), $context['customer']['phone']);
        }
        $lines[] = sprintf(__('Ospiti: %d', 'fp-experiences'), (int) ($context['totals']['guests'] ?? 0));
        $lines[] = sprintf(__('Slot richiesto: %s', 'fp-experiences'), $context['slot']['start_label'] ?? $context['slot']['start_datetime'] ?? '');
        if (! empty($context['notes'])) {
            $lines[] = __('Note:', 'fp-experiences');
            $lines[] = $context['notes'];
        }

        $body = implode("\n", $lines);

        wp_mail($recipients, $subject, $body);

        // Ripristina la locale originale
        if (function_exists('restore_current_locale') && $original_locale !== 'it_IT') {
            restore_current_locale();
        }
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

    /**
     * Rileva la lingua con cui il cliente sta navigando.
     * Usa il layer di compatibilità multilingua unificato.
     * Se disponibile, usa anche il prefisso telefonico come fallback.
     *
     * @param string|null $phone_number Optional phone number to detect language from prefix
     * @see \FP_Exp\Compatibility\Multilanguage
     */
    private function detect_customer_locale(?string $phone_number = null): string
    {
        // Usa la classe di compatibilità unificata con fallback al prefisso telefonico
        return \FP_Exp\Compatibility\Multilanguage::get_current_locale(null, $phone_number);
    }

    /**
     * Recupera il titolo tradotto dell'esperienza in base alla lingua del cliente.
     * Usa il layer di compatibilità multilingua unificato.
     *
     * @param \WP_Post $experience    Post dell'esperienza
     * @param int      $reservation_id ID della reservation per recuperare la locale
     * @return string Titolo tradotto o originale
     */
    private function get_translated_title(\WP_Post $experience, int $reservation_id): string
    {
        // Recupera la locale del cliente dalla reservation
        $customer_locale = '';
        if ($reservation_id > 0) {
            $reservation = Reservations::get($reservation_id);
            $customer_locale = $reservation['locale'] ?? '';
        }

        // Se la lingua è italiana o non specificata, usa il titolo originale
        if (! $customer_locale || $customer_locale === 'it_IT' || $customer_locale === 'it') {
            return $experience->post_title;
        }

        // Estrai il codice lingua dalla locale (es. 'en_US' -> 'en')
        $target_lang = substr($customer_locale, 0, 2);

        // Usa il layer di compatibilità multilingua per trovare la traduzione
        $translated_id = \FP_Exp\Compatibility\Multilanguage::get_translated_post_id(
            $experience->ID,
            $target_lang
        );

        if ($translated_id > 0 && $translated_id !== $experience->ID) {
            $translated_post = get_post($translated_id);
            if ($translated_post && $translated_post->post_status !== 'trash') {
                return $translated_post->post_title;
            }
        }

        // Fallback: titolo originale
        return $experience->post_title;
    }

    private function debug_log(string $message, array $context = []): void
    {
        if (! Helpers::debug_logging_enabled()) {
            return;
        }

        Helpers::log_debug('rtb', $message, $context);
    }
}
