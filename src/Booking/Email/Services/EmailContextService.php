<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Services;

use FP_Exp\Booking\EmailTranslator;
use FP_Exp\Booking\ICS;
use FP_Exp\Booking\Reservations;
use FP_Exp\MeetingPoints\Repository;
use WC_Order;

use function absint;
use function array_map;
use function array_sum;
use function get_bloginfo;
use function get_locale;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function gmdate;
use function sanitize_email;
use function sanitize_text_field;
use function wc_get_order;
use function wp_date;
use function wp_timezone_string;

/**
 * Service for building email context from reservation and order.
 */
final class EmailContextService
{
    /**
     * Get email context from reservation and order.
     *
     * @return array<string, mixed>|null
     */
    public function getContext(int $reservation_id, int $order_id): ?array
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation || ! is_array($reservation)) {
            return null;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return null;
        }

        $experience_id = absint((int) ($reservation['experience_id'] ?? 0));
        $experience = get_post($experience_id);

        if (! $experience) {
            return null;
        }

        $contact = $this->normalizeContact($reservation['contact'] ?? [], $order);
        $billing = $this->normalizeBilling($reservation['billing'] ?? [], $order);
        $tickets = $this->normalizeTickets($experience_id, $reservation['pax'] ?? []);
        $addons = $this->normalizeAddons($experience_id, $reservation['addons'] ?? []);

        $start_timestamp = isset($reservation['start']) ? strtotime((string) $reservation['start']) : 0;
        $end_timestamp = isset($reservation['end']) ? strtotime((string) $reservation['end']) : 0;
        $timezone_string = wp_timezone_string();
        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');

        $start_utc = $start_timestamp ? gmdate('Ymd\THis\Z', $start_timestamp) : '';
        $end_utc = $end_timestamp ? gmdate('Ymd\THis\Z', $end_timestamp) : '';
        $start_iso = $start_timestamp ? gmdate('c', $start_timestamp) : '';
        $end_iso = $end_timestamp ? gmdate('c', $end_timestamp) : '';

        $meeting_point_repo = new Repository();
        $meeting_point_id = absint((int) ($reservation['meeting_point_id'] ?? 0));
        $meeting_point = $meeting_point_id > 0
            ? $meeting_point_repo->get($meeting_point_id)
            : '';

        $short_desc = get_post_meta($experience_id, '_fp_short_description', true);
        $booking_timestamp = time();
        $reminder_offset = 24;
        $followup_offset = 24;
        $reminder_timestamp = $start_timestamp > 0 ? max(0, $start_timestamp - ($reminder_offset * 3600)) : 0;
        $followup_timestamp = $end_timestamp > 0 ? max(0, $end_timestamp + ($followup_offset * 3600)) : 0;

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
            'organizer_email' => $this->getStructureEmail(),
            'url' => get_permalink($experience),
            'uid' => 'fp-exp-' . $reservation_id,
        ];

        $ics_content = ICS::generate($event);
        $ics_filename = 'fp-experience-' . $reservation_id . '.ics';
        $calendar_link = ICS::google_calendar_link($event);

        $total_pax = array_sum(array_map('absint', $reservation['pax'] ?? []));
        $marketing_consent = 'yes' === $order->get_meta('_fp_exp_consent_marketing');

        $context = [
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
                'slug' => (string) $experience->post_name,
            ],
            'slot' => [
                'start_utc' => $start_utc,
                'end_utc' => $end_utc,
                'start_iso' => $start_iso,
                'end_iso' => $end_iso,
                'start_local_date' => $start_timestamp ? wp_date($date_format, $start_timestamp) : '',
                'start_local_time' => $start_timestamp ? wp_date($time_format, $start_timestamp) : '',
                'end_local_time' => $end_timestamp ? wp_date($time_format, $end_timestamp) : '',
                'timezone' => $timezone_string,
                'start_timestamp' => $start_timestamp,
                'end_timestamp' => $end_timestamp,
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
                'gross' => (float) ($reservation['total_gross'] ?? 0),
                'tax' => (float) ($reservation['tax_total'] ?? 0),
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
            'timers' => [
                'booked_timestamp' => $booking_timestamp,
                'booked_iso' => $booking_timestamp ? gmdate('c', $booking_timestamp) : '',
                'reminder_timestamp' => $reminder_timestamp,
                'reminder_iso' => $reminder_timestamp ? gmdate('c', $reminder_timestamp) : '',
                'reminder_local_date' => $reminder_timestamp ? wp_date($date_format, $reminder_timestamp) : '',
                'reminder_local_time' => $reminder_timestamp ? wp_date($time_format, $reminder_timestamp) : '',
                'followup_timestamp' => $followup_timestamp,
                'followup_iso' => $followup_timestamp ? gmdate('c', $followup_timestamp) : '',
                'followup_local_date' => $followup_timestamp ? wp_date($date_format, $followup_timestamp) : '',
                'followup_local_time' => $followup_timestamp ? wp_date($time_format, $followup_timestamp) : '',
                'reminder_offset' => $reminder_offset,
                'followup_offset' => $followup_offset,
            ],
        ];

        $language = $this->detectLanguage($context);
        $context['language'] = $language;
        $context['language_locale'] = EmailTranslator::LANGUAGE_IT === $language ? 'it_IT' : 'en_US';

        return $context;
    }

    /**
     * Normalize contact data.
     *
     * @param mixed $meta
     *
     * @return array<string, string>
     */
    private function normalizeContact($meta, WC_Order $order): array
    {
        $meta = is_array($meta) ? $meta : [];

        return [
            'email' => sanitize_email((string) ($meta['email'] ?? $order->get_billing_email())),
            'first_name' => sanitize_text_field((string) ($meta['first_name'] ?? $order->get_billing_first_name())),
            'last_name' => sanitize_text_field((string) ($meta['last_name'] ?? $order->get_billing_last_name())),
            'phone' => sanitize_text_field((string) ($meta['phone'] ?? $order->get_billing_phone())),
        ];
    }

    /**
     * Normalize billing data.
     *
     * @param mixed $meta
     *
     * @return array<string, string>
     */
    private function normalizeBilling($meta, WC_Order $order): array
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
     * Normalize tickets data.
     *
     * @param mixed $pax
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTickets(int $experience_id, $pax): array
    {
        $pax = is_array($pax) ? $pax : [];
        $labels = $this->getTicketLabels($experience_id);
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
     * Normalize addons data.
     *
     * @param mixed $addons
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAddons(int $experience_id, $addons): array
    {
        $addons = is_array($addons) ? $addons : [];
        $labels = $this->getAddonLabels($experience_id);
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
     * Get ticket labels.
     *
     * @return array<string, string>
     */
    private function getTicketLabels(int $experience_id): array
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
     * Get addon labels.
     *
     * @return array<string, string>
     */
    private function getAddonLabels(int $experience_id): array
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
     * Detect language from context.
     *
     * @param array<string, mixed> $context
     */
    private function detectLanguage(array $context): string
    {
        $locale = get_locale();
        $language = EmailTranslator::LANGUAGE_IT;

        if (strpos($locale, 'en') === 0) {
            $language = EmailTranslator::LANGUAGE_EN;
        }

        return EmailTranslator::normalize($language);
    }

    /**
     * Get structure email.
     */
    private function getStructureEmail(): string
    {
        $emails = get_option('fp_exp_emails', []);
        if (is_array($emails) && ! empty($emails['sender']['structure'])) {
            $candidate = sanitize_email((string) $emails['sender']['structure']);
            if ($candidate) {
                return $candidate;
            }
        }

        $option = (string) get_option('fp_exp_structure_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }
}















