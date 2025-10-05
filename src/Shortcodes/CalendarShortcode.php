<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Theme;
use WP_Error;
use WP_Post;

use function absint;
use function esc_html__;
use function get_option;
use function get_post;
use function gmdate;
use function max;
use function wp_json_encode;
use function wp_timezone;
use const ARRAY_A;

final class CalendarShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_calendar';

    protected string $template = 'front/calendar.php';

    protected array $defaults = [
        'id' => '',
        'months' => '2',
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
            return new WP_Error('fp_exp_calendar_invalid', esc_html__('Missing experience ID.', 'fp-experiences'));
        }

        $post = get_post($experience_id);
        if (! $post instanceof WP_Post || 'fp_experience' !== $post->post_type) {
            return new WP_Error('fp_exp_calendar_not_found', esc_html__('Experience not found.', 'fp-experiences'));
        }

        $months = max(1, min(12, absint($attributes['months'])));

        $slots = $this->collect_slots($experience_id, $months);
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

        $schema = [];
        foreach ($slots['flat'] as $slot) {
            $schema[] = [
                '@type' => 'Offer',
                'availability' => $slot['availability'],
                'price' => $slot['price_from'],
                'priceCurrency' => $slot['currency'],
                'validFrom' => $slot['start_iso'],
                'validThrough' => $slot['end_iso'],
            ];
        }

        return [
            'theme' => $theme,
            'experience' => [
                'id' => $experience_id,
                'title' => $post->post_title,
            ],
            'months' => $slots['calendar'],
            'schema_json' => $schema ? wp_json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'TouristTrip',
                'name' => $post->post_title,
                'offers' => $schema,
            ]) : '',
        ];
    }

    /**
     * @return array{calendar: array<int, array<string, mixed>>, flat: array<int, array<string, mixed>>}
     */
    private function collect_slots(int $experience_id, int $months): array
    {
        global $wpdb;

        $table = Slots::table_name();
        $now = gmdate('Y-m-d H:i:s');

        $period_end = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->add(new DateInterval('P' . $months . 'M'))
            ->format('Y-m-d H:i:s');

        $sql = $wpdb->prepare(
            "SELECT id, start_datetime, end_datetime, capacity_total FROM {$table} " .
            "WHERE experience_id = %d AND status = %s AND start_datetime BETWEEN %s AND %s ORDER BY start_datetime ASC",
            $experience_id,
            Slots::STATUS_OPEN,
            $now,
            $period_end
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! $rows) {
            $fallback_limit = max(1, $months * 31);
            $fallback_sql = $wpdb->prepare(
                "SELECT id, start_datetime, end_datetime, capacity_total FROM {$table} " .
                "WHERE experience_id = %d AND status = %s AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT %d",
                $experience_id,
                Slots::STATUS_OPEN,
                $now,
                $fallback_limit
            );

            $rows = $wpdb->get_results($fallback_sql, ARRAY_A);
        }
        $timezone = wp_timezone();
        $currency = get_option('woocommerce_currency', 'EUR');

        $calendar = [];
        $flat = [];

        if (! $rows) {
            return [
                'calendar' => $calendar,
                'flat' => $flat,
            ];
        }

        $slot_ids = [];
        foreach ($rows as $row) {
            $slot_ids[] = (int) $row['id'];
        }

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
            $day_key = $start->setTimezone($timezone)->format('Y-m-d');
            $month_key = $start->setTimezone($timezone)->format('Y-m');

            $slot = [
                'id' => $slot_id,
                'date' => $day_key,
                'time' => $start->setTimezone($timezone)->format('H:i'),
                'remaining' => $remaining,
                'availability' => $remaining > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                'start_iso' => $start->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'end_iso' => $end->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'currency' => $currency,
                'price_from' => 0,
            ];

            $month_label = $start->setTimezone($timezone)->format('F Y');

            if (! isset($calendar[$month_key])) {
                $calendar[$month_key] = [
                    'month_label' => $month_label,
                    'days' => [],
                ];
            } elseif (! isset($calendar[$month_key]['month_label'])) {
                $calendar[$month_key]['month_label'] = $month_label;
            }

            if (! isset($calendar[$month_key]['days'][$day_key])) {
                $calendar[$month_key]['days'][$day_key] = [];
            }

            $calendar[$month_key]['days'][$day_key][] = $slot;
            $flat[] = $slot;
        }

        return [
            'calendar' => $calendar,
            'flat' => $flat,
        ];
    }
}
