<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use FP_Exp\Booking\AvailabilityService;
use FP_Exp\Booking\Slots;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\LanguageHelper;
use FP_Exp\Utils\Theme;
use WP_Error;
use WP_Post;

use function absint;
use function apply_filters;
use function array_filter;
use function array_slice;
use function array_unique;
use function esc_html__;
use function get_locale;
use function get_option;
use function get_post;
use function get_permalink;
use function get_post_meta;
use function get_post_modified_time;
use function get_post_type;
use function get_the_title;
use function get_the_ID;
use function gmdate;
use function in_array;
use function is_array;
use function is_numeric;
use function max;
use function ksort;
use function sanitize_key;
use function sanitize_text_field;
use function substr;
use function uksort;
use function wp_create_nonce;
use function wp_json_encode;
use function wp_get_attachment_image_src;
use function wp_timezone;
use function time;
use function trim;
use const ARRAY_A;

final class WidgetShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_widget';

    protected string $template = 'front/widget.php';

    protected array $defaults = [
        'id' => '',
        'sticky' => '0',
        'show_calendar' => '0',
        'display_context' => '',
        'preset' => '',
        'mode' => '',
        'primary' => '',
        'secondary' => '',
        'accent' => '',
        'background' => '',
        'surface' => '',
        'text' => '',
        'muted' => '',
        'success' => '',
        'warning' => '',
        'danger' => '',
        'radius' => '',
        'shadow' => '',
        'font' => '',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $experience_id = absint($attributes['id']);

        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_widget_invalid', esc_html__('Missing experience ID.', 'fp-experiences'));
        }

        $post = get_post($experience_id);

        if (! $post instanceof WP_Post || 'fp_experience' !== $post->post_type) {
            return new WP_Error('fp_exp_widget_not_found', esc_html__('Experience not found.', 'fp-experiences'));
        }

        $flow_attribute = strtolower((string) $attributes['mode']);
        $rtb_forced = 'rtb' === $flow_attribute;

        if ($rtb_forced) {
            $attributes['mode'] = '';
        }

        $tickets = $this->prepare_tickets(get_post_meta($experience_id, '_fp_ticket_types', true));
        $addons = $this->prepare_addons(get_post_meta($experience_id, '_fp_addons', true));
        $highlights = Helpers::get_meta_array($experience_id, '_fp_highlights');
        $meeting_point = Repository::get_primary_summary_for_experience($experience_id);
        $duration = absint((string) get_post_meta($experience_id, '_fp_duration_minutes', true));
        $taxonomy_languages = wp_get_post_terms($experience_id, 'fp_exp_language', ['fields' => 'names']);
        $language_term_names = is_array($taxonomy_languages)
            ? array_values(array_filter(array_map('sanitize_text_field', $taxonomy_languages)))
            : [];

        $languages = Helpers::get_meta_array($experience_id, '_fp_languages');
        if (empty($languages)) {
            $languages = $language_term_names;
        }

        $language_badges = LanguageHelper::build_language_badges($languages);

        $slots = $this->get_upcoming_slots($experience_id, $tickets, 60);

        // Fallback: se non ci sono slot persistiti, genera slot virtuali basati sulle meta di disponibilità
        if (empty($slots)) {
            $slots = $this->generate_virtual_slots($experience_id, $tickets, 6, 300);
        }
        $calendar = $this->group_slots_by_day($slots);

        $display_context = apply_filters('fp_exp_widget_display_context', (string) $attributes['display_context'], $experience_id, $attributes);

        $theme = Theme::resolve_palette([
            'preset' => (string) $attributes['preset'],
            'mode' => (string) $attributes['mode'],
            'primary' => (string) $attributes['primary'],
            'secondary' => (string) $attributes['secondary'],
            'accent' => (string) $attributes['accent'],
            'background' => (string) $attributes['background'],
            'surface' => (string) $attributes['surface'],
            'text' => (string) $attributes['text'],
            'muted' => (string) $attributes['muted'],
            'success' => (string) $attributes['success'],
            'warning' => (string) $attributes['warning'],
            'danger' => (string) $attributes['danger'],
            'radius' => (string) $attributes['radius'],
            'shadow' => (string) $attributes['shadow'],
            'font' => (string) $attributes['font'],
        ]);

        $schema_offers = [];
        foreach ($slots as $slot) {
            $schema_offers[] = [
                '@type' => 'Offer',
                'availability' => $slot['availability'],
                'price' => $slot['price_from'],
                'priceCurrency' => $slot['currency'],
                'validFrom' => $slot['start_iso'],
                'validThrough' => $slot['end_iso'],
            ];
        }

        $schema = wp_json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => get_the_title($post),
            'description' => sanitize_text_field((string) get_post_meta($experience_id, '_fp_short_desc', true)),
            'offers' => $schema_offers,
        ]);

        $scope = Theme::generate_scope();

        $rtb_settings = Helpers::rtb_settings();
        $experience_rtb_mode = Helpers::rtb_mode_for_experience($experience_id);
        $rtb_enabled = ('off' !== $experience_rtb_mode) && Helpers::experience_uses_rtb($experience_id);

        if ($rtb_forced) {
            $rtb_enabled = true;
            if ('off' === $rtb_settings['mode']) {
                $experience_rtb_mode = 'confirm';
            } else {
                $experience_rtb_mode = $rtb_settings['mode'];
            }
        }

        $rtb_context = [
            'enabled' => $rtb_enabled,
            'mode' => $rtb_enabled ? $experience_rtb_mode : 'off',
            'timeout' => Helpers::rtb_hold_timeout(),
            'forced' => $rtb_forced,
        ];

        $behavior = [
            'sticky' => in_array((string) $attributes['sticky'], ['1', 'true'], true),
            'show_calendar' => in_array((string) $attributes['show_calendar'], ['1', 'true'], true),
        ];

        $cognitive_bias_meta = get_post_meta($experience_id, '_fp_cognitive_biases', true);
        $cognitive_bias_slugs = is_array($cognitive_bias_meta)
            ? array_values(array_filter(array_map('sanitize_key', $cognitive_bias_meta)))
            : [];
        $cognitive_bias_badges = Helpers::cognitive_bias_badges($cognitive_bias_slugs);

        $modified = get_post_modified_time('U', true, $post);

        $context = [
            'theme' => $theme,
            'scope_class' => $scope,
            'rtb' => $rtb_context,
            'rtb_nonce' => wp_create_nonce('fp-exp-rtb'),
            'experience' => [
                'id' => $experience_id,
                'title' => get_the_title($post),
                'permalink' => get_permalink($post),
                'highlights' => array_slice($highlights, 0, 4),
                'meeting_point' => $meeting_point,
                'duration' => $duration,
                'languages' => $languages,
                'language_badges' => $language_badges,
            ],
            'tickets' => $tickets,
            'addons' => $addons,
            'slots' => $slots,
            'calendar' => $calendar,
            'behavior' => $behavior,
            'schema_json' => $schema,
            'locale' => get_locale(),
            'timezone' => wp_timezone()->getName(),
            'rtb_settings' => $rtb_settings,
            'display_context' => $display_context,
            'config_version' => $modified ? (string) $modified : (string) time(),
            'overview' => [
                'cognitive_biases' => $cognitive_bias_badges,
            ],
        ];

        $missing_meta = [];

        if (empty($tickets)) {
            $missing_meta[] = '_fp_ticket_types';
        }

        if (Helpers::meeting_points_enabled() && '' === trim($meeting_point)) {
            $missing_meta[] = '_fp_meeting_point_id';
        }

        if (! empty($missing_meta)) {
            $normalized_keys = array_values(array_unique(array_map(static fn ($key) => sanitize_key((string) $key), $missing_meta)));
            Helpers::log_debug('shortcodes', 'Widget shortcode missing meta', [
                'shortcode' => $this->tag,
                'experience_id' => $experience_id,
                'meta_keys' => $normalized_keys,
            ]);
        }

        return $context;
    }

    /**
     * Genera slot virtuali per i prossimi N mesi usando le meta di disponibilità.
     *
     * @param array<int, array<string, mixed>> $tickets
     *
     * @return array<int, array<string, mixed>>
     */
    private function generate_virtual_slots(int $experience_id, array $tickets, int $monthsAhead = 6, int $limit = 300): array
    {
        $monthsAhead = max(1, $monthsAhead);
        $limit = max(1, $limit);

        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            $endUtc = $nowUtc->add(new \DateInterval('P' . $monthsAhead . 'M'));
        } catch (Exception $exception) {
            $endUtc = $nowUtc->add(new \DateInterval('P6M'));
        }

        $virtual = AvailabilityService::get_virtual_slots(
            $experience_id,
            $nowUtc->setTime(0, 0)->format('Y-m-d H:i:s'),
            $endUtc->setTime(23, 59, 59)->format('Y-m-d H:i:s')
        );

        if (empty($virtual)) {
            return [];
        }

        $timezone = wp_timezone();
        $currency = get_option('woocommerce_currency', 'EUR');
        $price_from = $this->determine_price(null, $tickets);

        $mapped = [];
        foreach ($virtual as $row) {
            $startSql = isset($row['start']) ? (string) $row['start'] : '';
            $endSql = isset($row['end']) ? (string) $row['end'] : '';

            if ('' === $startSql || '' === $endSql) {
                continue;
            }

            try {
                $start = new DateTimeImmutable($startSql, new DateTimeZone('UTC'));
                $end = new DateTimeImmutable($endSql, new DateTimeZone('UTC'));
            } catch (Exception $exception) {
                continue;
            }

            $remaining = isset($row['capacity_remaining']) ? (int) $row['capacity_remaining'] : 0;

            $mapped[] = [
                // Nessun ID persistito per slot virtuali
                'id' => 0,
                'start' => $start->setTimezone($timezone)->format('Y-m-d'),
                'time' => $start->setTimezone($timezone)->format('H:i'),
                'start_iso' => $start->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'end_iso' => $end->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'remaining' => $remaining,
                'availability' => $remaining > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                'currency' => $currency,
                'price_from' => $price_from,
            ];

            if (count($mapped) >= $limit) {
                break;
            }
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function resolve_experience_id(array $attributes): int
    {
        $experience_id = absint($attributes['id'] ?? 0);

        if ($experience_id > 0) {
            return $experience_id;
        }

        $current_id = absint(get_the_ID() ?: 0);

        if ($current_id <= 0) {
            Helpers::log_debug('shortcodes', 'Widget shortcode missing explicit ID', [
                'shortcode' => $this->tag,
                'context_post_type' => '',
                'context_post_id' => 0,
            ]);

            return 0;
        }

        $post_type = get_post_type($current_id) ?: '';

        if ('fp_experience' === $post_type) {
            return $current_id;
        }

        Helpers::log_debug('shortcodes', 'Widget shortcode missing explicit ID', [
            'shortcode' => $this->tag,
            'context_post_type' => $post_type,
            'context_post_id' => $current_id,
        ]);

        return 0;
    }

    /**
     * @param mixed $raw
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepare_tickets($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $tickets = [];
        foreach ($raw as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            $slug = sanitize_key($ticket['slug'] ?? ($ticket['label'] ?? ''));
            if (! $slug) {
                continue;
            }

            $price = isset($ticket['price']) ? (float) $ticket['price'] : 0.0;
            $cap = isset($ticket['cap']) ? absint($ticket['cap']) : null;

            $tickets[] = [
                'slug' => $slug,
                'label' => sanitize_text_field((string) ($ticket['label'] ?? '')),
                'description' => sanitize_text_field((string) ($ticket['description'] ?? '')),
                'price' => $price,
                'cap' => $cap,
            ];
        }

        return $tickets;
    }

    /**
     * @param mixed $raw
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepare_addons($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $addons = [];
        foreach ($raw as $addon) {
            if (! is_array($addon)) {
                continue;
            }

            $slug = sanitize_key($addon['slug'] ?? ($addon['label'] ?? ''));
            if (! $slug) {
                continue;
            }

            $image_id = isset($addon['image_id']) ? absint($addon['image_id']) : 0;
            $image = $image_id > 0 ? wp_get_attachment_image_src($image_id, 'medium') : false;

            $selection_type = isset($addon['selection_type']) ? sanitize_key((string) $addon['selection_type']) : 'checkbox';
            if (! in_array($selection_type, ['checkbox', 'radio'], true)) {
                $selection_type = 'checkbox';
            }

            $addons[] = [
                'slug' => $slug,
                'label' => sanitize_text_field((string) ($addon['label'] ?? '')),
                'description' => sanitize_text_field((string) ($addon['description'] ?? '')),
                'price' => isset($addon['price']) ? (float) $addon['price'] : 0.0,
                'image_id' => $image_id,
                'image' => [
                    'url' => $image ? (string) $image[0] : '',
                    'width' => $image ? absint((string) $image[1]) : 0,
                    'height' => $image ? absint((string) $image[2]) : 0,
                ],
                'selection_type' => $selection_type,
                'selection_group' => sanitize_text_field((string) ($addon['selection_group'] ?? '')),
            ];
        }

        return $addons;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_upcoming_slots(int $experience_id, array $tickets, int $limit = 60): array
    {
        global $wpdb;

        $table = Slots::table_name();
        $now = gmdate('Y-m-d H:i:s');

        $sql = $wpdb->prepare(
            "SELECT id, start_datetime, end_datetime, capacity_total, price_rules FROM {$table} " .
            "WHERE experience_id = %d AND status = %s AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT %d",
            $experience_id,
            Slots::STATUS_OPEN,
            $now,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! $rows) {
            return [];
        }

        $timezone = wp_timezone();
        $currency = get_option('woocommerce_currency', 'EUR');
        $slots = [];

        $slot_ids = array_map(static fn ($row) => (int) ($row['id'] ?? 0), $rows);
        $snapshots = Slots::get_capacity_snapshots($slot_ids);

        foreach ($rows as $row) {
            try {
                $start = new DateTimeImmutable((string) $row['start_datetime'], new DateTimeZone('UTC'));
                $end = new DateTimeImmutable((string) $row['end_datetime'], new DateTimeZone('UTC'));
            } catch (Exception $exception) {
                continue;
            }

            $slot_id = (int) $row['id'];
            $snapshot = $snapshots[$slot_id] ?? ['total' => 0, 'per_type' => []];
            $remaining = max(0, (int) $row['capacity_total'] - $snapshot['total']);
            $price_from = $this->determine_price($row['price_rules'] ?? null, $tickets);

            $slots[] = [
                'id' => $slot_id,
                'start' => $start->setTimezone($timezone)->format('Y-m-d'),
                'time' => $start->setTimezone($timezone)->format('H:i'),
                'start_iso' => $start->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'end_iso' => $end->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'remaining' => $remaining,
                'availability' => $remaining > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                'currency' => $currency,
                'price_from' => $price_from,
            ];
        }

        return $slots;
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function group_slots_by_day(array $slots): array
    {
        $grouped = [];
        $timezone = wp_timezone();

        foreach ($slots as $slot) {
            $day_key = isset($slot['start']) ? (string) $slot['start'] : '';
            $month_key = '';
            $month_label = '';

            if ('' === $day_key) {
                continue;
            }

            $month_key = substr($day_key, 0, 7);

            $start_iso = isset($slot['start_iso']) ? (string) $slot['start_iso'] : '';
            if ('' !== $start_iso) {
                try {
                    $start = new DateTimeImmutable($start_iso);
                    $month_label = $start->setTimezone($timezone)->format('F Y');
                } catch (Exception $exception) {
                    $month_label = '';
                }
            }

            if (! isset($grouped[$month_key])) {
                $grouped[$month_key] = [
                    'month_label' => $month_label,
                    'days' => [],
                ];
            } elseif ('' !== $month_label && empty($grouped[$month_key]['month_label'])) {
                $grouped[$month_key]['month_label'] = $month_label;
            }

            if (! isset($grouped[$month_key]['days'][$day_key])) {
                $grouped[$month_key]['days'][$day_key] = [];
            }

            $grouped[$month_key]['days'][$day_key][] = $slot;
        }

        uksort($grouped, static fn (string $a, string $b) => strcmp($a, $b));

        foreach ($grouped as &$month) {
            if (isset($month['days']) && is_array($month['days'])) {
                ksort($month['days']);
            }
        }
        unset($month);

        return $grouped;
    }

    /**
     * @param mixed $rules
     * @param array<int, array<string, mixed>> $tickets
     */
    private function determine_price($rules, array $tickets): float
    {
        if (is_array($rules) && isset($rules['base_price'])) {
            return (float) $rules['base_price'];
        }

        error_log('[FP-EXP Widget] Tickets for price calculation: ' . print_r($tickets, true));

        // First, look for a ticket marked as "use_as_price_from"
        foreach ($tickets as $ticket) {
            error_log('[FP-EXP Widget] Checking ticket: ' . print_r($ticket, true));
            if (! empty($ticket['use_as_price_from'])) {
                $price = $ticket['price'] ?? 0;
                error_log('[FP-EXP Widget] Found use_as_price_from, price: ' . $price);
                if (is_numeric($price) && $price > 0) {
                    return (float) $price;
                }
            }
        }

        // If no ticket is marked, fall back to the lowest price
        $price_from = null;
        foreach ($tickets as $ticket) {
            $price = $ticket['price'] ?? 0;
            if (! is_numeric($price) || $price <= 0) {
                continue;
            }

            $price = (float) $price;
            if (null === $price_from || $price < $price_from) {
                $price_from = $price;
            }
        }

        error_log('[FP-EXP Widget] Final price: ' . ($price_from ?? 0.0));
        return $price_from ?? 0.0;
    }
}
