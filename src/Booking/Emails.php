<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use FP_Exp\Integrations\Brevo;
use WC_Order;

use function __;
use function absint;
use function add_action;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_sum;
use function esc_html__;
use function function_exists;
use function get_bloginfo;
use function get_locale;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function strtotime;
use function trim;
use function wc_get_order;
use function wp_date;
use function wp_mail;
use function wp_strip_all_tags;
use function wp_timezone_string;
use function admin_url;
use function file_exists;
use function unlink;

final class Emails
{
    private ?Brevo $brevo = null;

    public function __construct(?Brevo $brevo = null)
    {
        $this->brevo = $brevo;
    }

    public function register_hooks(): void
    {
        add_action('fp_exp_reservation_paid', [$this, 'handle_reservation_paid'], 10, 2);
        add_action('fp_exp_reservation_cancelled', [$this, 'handle_reservation_cancelled'], 10, 2);
    }

    public function handle_reservation_paid(int $reservation_id, int $order_id): void
    {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $this->send_customer_confirmation($context);
        $this->send_staff_notification($context, false);
    }

    public function handle_reservation_cancelled(int $reservation_id, int $order_id): void
    {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $context['status_label'] = esc_html__('Cancelled', 'fp-experiences');

        $this->send_staff_notification($context, true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_customer_confirmation(array $context): void
    {
        $this->dispatch_customer_confirmation($context, false);
    }

    /**
     * Allow Brevo failures to fall back to local Woo mailer delivery.
     *
     * @param array<string, mixed> $context
     */
    public function send_customer_confirmation_fallback(array $context): void
    {
        $this->dispatch_customer_confirmation($context, true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function dispatch_customer_confirmation(array $context, bool $force_send): void
    {
        $recipient = $context['customer']['email'] ?? '';

        if (! $recipient) {
            return;
        }

        if (! $force_send && $this->brevo instanceof Brevo && $this->brevo->is_enabled()) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: experience title. */
            __('Your reservation for %s', 'fp-experiences'),
            $context['experience']['title'] ?? ''
        );

        $message = $this->render_template('customer-confirmation', $context);

        if ('' === trim($message)) {
            return;
        }

        $attachments = $this->prepare_attachments($context);

        $this->dispatch([$recipient], $subject, $message, $attachments);

        $this->cleanup_attachments($attachments);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_staff_notification(array $context, bool $is_cancelled): void
    {
        $recipients = $this->resolve_staff_recipients($context);

        if (! $recipients) {
            return;
        }

        if ($is_cancelled) {
            $subject = sprintf(
                /* translators: %s: experience title. */
                __('Reservation cancelled for %s', 'fp-experiences'),
                $context['experience']['title'] ?? ''
            );
        } else {
            $subject = sprintf(
                /* translators: %s: experience title. */
                __('New reservation for %s', 'fp-experiences'),
                $context['experience']['title'] ?? ''
            );
        }

        $message = $this->render_template('staff-notification', $context + [
            'is_cancelled' => $is_cancelled,
        ]);

        if ('' === trim($message)) {
            return;
        }

        $attachments = $is_cancelled ? [] : $this->prepare_attachments($context);

        $this->dispatch($recipients, $subject, $message, $attachments);

        $this->cleanup_attachments($attachments);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_context(int $reservation_id, int $order_id): ?array
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation) {
            return null;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return null;
        }

        $experience = get_post(absint($reservation['experience_id'] ?? 0));

        if (! $experience) {
            return null;
        }

        $slot = Slots::get_slot(absint($reservation['slot_id'] ?? 0));

        if (! $slot) {
            return null;
        }

        $contact_meta = $order->get_meta('_fp_exp_contact');
        $contact = $this->normalize_contact($contact_meta, $order);
        $billing_meta = $order->get_meta('_fp_exp_billing');
        $billing = $this->normalize_billing($billing_meta, $order);

        $meeting_point = get_post_meta($experience->ID, '_fp_meeting_point', true);
        $short_desc = get_post_meta($experience->ID, '_fp_short_desc', true);
        $tickets = $this->normalize_tickets($experience->ID, $reservation['pax'] ?? []);
        $addons = $this->normalize_addons($experience->ID, $reservation['addons'] ?? []);

        $start_utc = (string) ($slot['start_datetime'] ?? '');
        $end_utc = (string) ($slot['end_datetime'] ?? '');
        $start_timestamp = $start_utc ? strtotime($start_utc . ' UTC') : 0;
        $end_timestamp = $end_utc ? strtotime($end_utc . ' UTC') : 0;

        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');

        $event = [
            'summary' => sprintf(
                /* translators: %s: experience title. */
                __('Experience: %s', 'fp-experiences'),
                $experience->post_title
            ),
            'description' => $short_desc ? (string) $short_desc : wp_strip_all_tags((string) $experience->post_excerpt),
            'location' => (string) $meeting_point,
            'start' => $start_utc,
            'end' => $end_utc ?: $start_utc,
            'organizer_name' => get_bloginfo('name'),
            'organizer_email' => $this->get_structure_email(),
            'url' => get_permalink($experience),
            'uid' => 'fp-exp-' . $reservation_id,
        ];

        $ics_content = ICS::generate($event);
        $ics_filename = 'fp-experience-' . $reservation_id . '.ics';
        $calendar_link = ICS::google_calendar_link($event);

        $total_pax = array_sum(array_map('absint', $reservation['pax'] ?? []));
        $marketing_consent = 'yes' === $order->get_meta('_fp_exp_consent_marketing');

        return [
            'reservation' => [
                'id' => $reservation_id,
                'status' => $reservation['status'] ?? '',
            ],
            'experience' => [
                'id' => $experience->ID,
                'title' => $experience->post_title,
                'permalink' => get_permalink($experience),
                'meeting_point' => (string) $meeting_point,
                'short_description' => (string) $short_desc,
            ],
            'slot' => [
                'start_utc' => $start_utc,
                'end_utc' => $end_utc,
                'start_local_date' => $start_timestamp ? wp_date($date_format, $start_timestamp) : '',
                'start_local_time' => $start_timestamp ? wp_date($time_format, $start_timestamp) : '',
                'end_local_time' => $end_timestamp ? wp_date($time_format, $end_timestamp) : '',
                'timezone' => wp_timezone_string(),
            ],
            'order' => [
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'total' => $order->get_formatted_order_total(),
                'currency' => $order->get_currency(),
                'notes' => $order->get_customer_note(),
                'admin_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            ],
            'customer' => [
                'name' => trim($contact['first_name'] . ' ' . $contact['last_name']),
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'first_name' => $contact['first_name'],
                'last_name' => $contact['last_name'],
            ],
            'billing' => $billing,
            'tickets' => $tickets,
            'addons' => $addons,
            'totals' => [
                'pax_total' => $total_pax,
                'gross' => (float) $reservation['total_gross'],
                'tax' => (float) $reservation['tax_total'],
            ],
            'consent' => [
                'marketing' => $marketing_consent,
            ],
            'locale' => get_locale(),
            'ics' => [
                'content' => $ics_content,
                'filename' => $ics_filename,
                'google_link' => $calendar_link,
            ],
        ];
    }

    /**
     * @param mixed $meta
     *
     * @return array<string, string>
     */
    private function normalize_contact($meta, WC_Order $order): array
    {
        $meta = is_array($meta) ? $meta : [];

        $email = sanitize_email((string) ($meta['email'] ?? $order->get_billing_email()));
        $first_name = sanitize_text_field((string) ($meta['first_name'] ?? $order->get_billing_first_name()));
        $last_name = sanitize_text_field((string) ($meta['last_name'] ?? $order->get_billing_last_name()));
        $phone = sanitize_text_field((string) ($meta['phone'] ?? $order->get_billing_phone()));

        return [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
        ];
    }

    /**
     * @param mixed $meta
     *
     * @return array<string, string>
     */
    private function normalize_billing($meta, WC_Order $order): array
    {
        $meta = is_array($meta) ? $meta : [];

        return [
            'first_name' => sanitize_text_field((string) ($meta['first_name'] ?? $order->get_billing_first_name())),
            'last_name' => sanitize_text_field((string) ($meta['last_name'] ?? $order->get_billing_last_name())),
            'address' => sanitize_text_field((string) ($meta['address_1'] ?? $order->get_billing_address_1())),
            'city' => sanitize_text_field((string) ($meta['city'] ?? $order->get_billing_city())),
            'postcode' => sanitize_text_field((string) ($meta['postcode'] ?? $order->get_billing_postcode())),
            'country' => sanitize_text_field((string) ($meta['country'] ?? $order->get_billing_country())),
        ];
    }

    /**
     * @param array<string, mixed> $pax
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalize_tickets(int $experience_id, $pax): array
    {
        $pax = is_array($pax) ? $pax : [];
        $labels = $this->get_ticket_labels($experience_id);
        $output = [];

        foreach ($pax as $type => $quantity) {
            $type_key = sanitize_key((string) $type);
            $output[] = [
                'type' => $type_key,
                'label' => $labels[$type_key] ?? ucfirst(str_replace('_', ' ', $type_key)),
                'quantity' => absint($quantity),
            ];
        }

        return $output;
    }

    /**
     * @param mixed $addons
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalize_addons(int $experience_id, $addons): array
    {
        $addons = is_array($addons) ? $addons : [];
        $labels = $this->get_addon_labels($experience_id);
        $output = [];

        foreach ($addons as $key => $addon) {
            $addon_key = sanitize_key((string) $key);
            $quantity = absint($addon['quantity'] ?? (is_numeric($addon) ? $addon : 0));

            if ($quantity <= 0) {
                continue;
            }

            $output[] = [
                'key' => $addon_key,
                'label' => $labels[$addon_key] ?? ucfirst(str_replace('_', ' ', $addon_key)),
                'quantity' => $quantity,
            ];
        }

        return $output;
    }

    /**
     * @return array<string, string>
     */
    private function get_ticket_labels(int $experience_id): array
    {
        $meta = get_post_meta($experience_id, '_fp_ticket_types', true);

        if (! is_array($meta)) {
            return [];
        }

        $labels = [];

        foreach ($meta as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = sanitize_key((string) ($entry['key'] ?? $entry['slug'] ?? $entry['id'] ?? ''));

            if (! $key) {
                continue;
            }

            $labels[$key] = sanitize_text_field((string) ($entry['label'] ?? $entry['name'] ?? ucfirst($key)));
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function get_addon_labels(int $experience_id): array
    {
        $meta = get_post_meta($experience_id, '_fp_addons', true);

        if (! is_array($meta)) {
            return [];
        }

        $labels = [];

        foreach ($meta as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = sanitize_key((string) ($entry['key'] ?? $entry['slug'] ?? $entry['id'] ?? ''));

            if (! $key) {
                continue;
            }

            $labels[$key] = sanitize_text_field((string) ($entry['label'] ?? $entry['name'] ?? ucfirst($key)));
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    private function resolve_staff_recipients(array $context): array
    {
        $structure = $this->get_structure_email();
        $webmaster = $this->get_webmaster_email();

        $recipients = array_filter([
            $structure,
            $webmaster,
        ]);

        /** @var array<int, string> $filtered */
        $filtered = apply_filters('fp_exp_email_recipients', $recipients, $context, 'staff');

        $filtered = array_map('sanitize_email', $filtered);

        return array_values(array_filter($filtered));
    }

    private function get_structure_email(): string
    {
        $option = (string) get_option('fp_exp_structure_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    private function get_webmaster_email(): string
    {
        $option = (string) get_option('fp_exp_webmaster_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<int, string>
     */
    private function prepare_attachments(array $context): array
    {
        $ics = $context['ics']['content'] ?? '';
        $filename = $context['ics']['filename'] ?? 'fp-experience.ics';

        if (! is_string($ics) || '' === trim($ics)) {
            return [];
        }

        $path = ICS::create_file($ics, $filename);

        if (! $path) {
            return [];
        }

        return [$path];
    }

    /**
     * @param array<int, string> $attachments
     */
    private function cleanup_attachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                @unlink($attachment);
            }
        }
    }

    /**
     * @param array<int, string> $recipients
     * @param array<int, string> $attachments
     */
    private function dispatch(array $recipients, string $subject, string $message, array $attachments = []): void
    {
        $recipients = array_values(array_filter(array_map('sanitize_email', $recipients)));

        if (! $recipients) {
            return;
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $to = implode(',', $recipients);

        if (function_exists('wc')) {
            $mailer = \wc()->mailer();
            $mailer->send($to, $subject, $message, $headers, $attachments);

            return;
        }

        wp_mail($to, $subject, $message, $headers, $attachments);
    }

    private function render_template(string $template, array $context): string
    {
        $path = FP_EXP_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

        if (! file_exists($path)) {
            return '';
        }

        ob_start();
        $email_context = $context;
        include $path;

        return (string) ob_get_clean();
    }
}
