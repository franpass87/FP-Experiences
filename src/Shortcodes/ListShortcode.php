<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Theme;
use WP_Error;
use WP_Post;
use WP_Query;

use function absint;
use function array_filter;
use function array_map;
use function explode;
use function get_option;
use function get_permalink;
use function get_post_meta;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function is_array;
use function is_numeric;
use function max;
use function sanitize_key;
use function sanitize_text_field;
use function strtoupper;
use function __;
use function gmdate;
use function in_array;
use function wp_get_post_terms;
use function wp_json_encode;
use function wp_timezone;
use function wp_list_pluck;
use const ARRAY_A;

final class ListShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_list';

    protected string $template = 'front/list.php';

    protected array $defaults = [
        'filters' => 'theme,duration,price',
        'per_page' => '9',
        'order' => 'menu_order',
        'order_direction' => 'ASC',
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
        $order = strtoupper(sanitize_key((string) $attributes['order_direction']));
        if (! in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $order_by = sanitize_key((string) $attributes['order']);
        if (! in_array($order_by, ['menu_order', 'date', 'title', 'modified'], true)) {
            $order_by = 'menu_order';
        }

        $per_page = max(1, absint($attributes['per_page']));

        $query = new WP_Query([
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'orderby' => $order_by,
            'order' => $order,
            'no_found_rows' => true,
        ]);

        $experiences = [];
        $schema = [];

        foreach ($query->posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $experience = $this->map_experience($post);

            if (empty($experience)) {
                continue;
            }

            $experiences[] = $experience;
            if (! empty($experience['schema'])) {
                $schema[] = $experience['schema'];
            }
        }

        $filters = $this->prepare_filters((string) $attributes['filters'], $experiences);

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

        return [
            'experiences' => $experiences,
            'filters' => $filters,
            'theme' => $theme,
            'schema_json' => $schema ? wp_json_encode(['@context' => 'https://schema.org', '@graph' => $schema]) : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function map_experience(WP_Post $post): array
    {
        $id = $post->ID;
        $gallery_ids = get_post_meta($id, '_fp_gallery_ids', true);
        $highlights = get_post_meta($id, '_fp_highlights', true);
        $duration = absint((string) get_post_meta($id, '_fp_duration_minutes', true));
        $ticket_types = get_post_meta($id, '_fp_ticket_types', true);
        $languages = get_post_meta($id, '_fp_languages', true);

        $price_from = null;
        if (is_array($ticket_types)) {
            foreach ($ticket_types as $ticket) {
                if (! isset($ticket['price'])) {
                    continue;
                }
                $price = (float) $ticket['price'];
                if ($price <= 0) {
                    continue;
                }
                if (null === $price_from || $price < $price_from) {
                    $price_from = $price;
                }
            }
        }

        $thumbnail = get_the_post_thumbnail_url($id, 'large');
        if (! $thumbnail && is_array($gallery_ids) && $gallery_ids) {
            $thumbnail = get_the_post_thumbnail_url((int) $gallery_ids[0], 'large');
        }

        $terms = [
            'theme' => wp_list_pluck(wp_get_post_terms($id, 'theme'), 'name'),
            'language' => wp_list_pluck(wp_get_post_terms($id, 'language'), 'name'),
            'duration' => wp_list_pluck(wp_get_post_terms($id, 'duration'), 'name'),
        ];

        $slots = $this->get_upcoming_slots($id, 6);

        $schema_offers = [];
        foreach ($slots as $slot) {
            $schema_offers[] = [
                '@type' => 'Offer',
                'availability' => $slot['availability'],
                'price' => $price_from ?? 0,
                'priceCurrency' => get_option('woocommerce_currency', 'EUR'),
                'validFrom' => $slot['start_iso'],
                'validThrough' => $slot['end_iso'],
            ];
        }

        $schema = [
            '@type' => 'TouristTrip',
            '@id' => get_permalink($id) . '#tour',
            'name' => get_the_title($post),
            'description' => sanitize_text_field((string) get_post_meta($id, '_fp_short_desc', true)),
            'image' => $thumbnail,
            'offers' => $schema_offers,
        ];

        $duration_label = $this->determine_duration_bucket($duration);

        return [
            'id' => $id,
            'title' => get_the_title($post),
            'permalink' => get_permalink($id),
            'short_description' => (string) get_post_meta($id, '_fp_short_desc', true),
            'highlights' => is_array($highlights) ? array_filter($highlights) : [],
            'duration' => $duration,
            'duration_label' => $duration_label,
            'thumbnail' => $thumbnail,
            'price_from' => $price_from,
            'languages' => is_array($languages) ? array_filter($languages) : [],
            'terms' => $terms,
            'slots' => $slots,
            'schema' => $schema,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_upcoming_slots(int $experience_id, int $limit = 6): array
    {
        global $wpdb;

        $table = Slots::table_name();
        $now = gmdate('Y-m-d H:i:s');

        $sql = $wpdb->prepare(
            "SELECT id, start_datetime, end_datetime, capacity_total FROM {$table} " .
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
        $slots = [];

        foreach ($rows as $row) {
            try {
                $start = new DateTimeImmutable((string) $row['start_datetime'], new DateTimeZone('UTC'));
                $end = new DateTimeImmutable((string) $row['end_datetime'], new DateTimeZone('UTC'));
            } catch (Exception $exception) {
                continue;
            }

            $snapshot = Slots::get_capacity_snapshot((int) $row['id']);
            $remaining = max(0, (int) $row['capacity_total'] - $snapshot['total']);

            $slots[] = [
                'id' => (int) $row['id'],
                'start' => $start->setTimezone($timezone)->format('Y-m-d H:i'),
                'start_iso' => $start->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'end_iso' => $end->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                'remaining' => $remaining,
                'availability' => $remaining > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            ];
        }

        return $slots;
    }

    /**
     * @param array<int, array<string, mixed>> $experiences
     *
     * @return array<string, mixed>
     */
    private function prepare_filters(string $filter_string, array $experiences): array
    {
        $available = array_map('trim', explode(',', $filter_string));

        $filters = [];

        foreach ($available as $filter) {
            $key = sanitize_key($filter);
            if (! $key) {
                continue;
            }

            switch ($key) {
                case 'theme':
                    $filters['theme'] = $this->collect_terms($experiences, 'theme');
                    break;
                case 'duration':
                    $filters['duration'] = $this->collect_durations($experiences);
                    break;
                case 'price':
                    $filters['price'] = $this->collect_price_range($experiences);
                    break;
            }
        }

        return array_filter($filters);
    }

    /**
     * @param array<int, array<string, mixed>> $experiences
     *
     * @return array<int, string>
     */
    private function collect_terms(array $experiences, string $taxonomy): array
    {
        $values = [];
        foreach ($experiences as $experience) {
            $terms = $experience['terms'][$taxonomy] ?? [];
            foreach ($terms as $term) {
                $values[$term] = $term;
            }
        }

        return array_values($values);
    }

    /**
     * @param array<int, array<string, mixed>> $experiences
     *
     * @return array<int, string>
     */
    private function collect_durations(array $experiences): array
    {
        $buckets = [];
        foreach ($experiences as $experience) {
            $duration = (int) ($experience['duration'] ?? 0);
            if ($duration <= 0) {
                continue;
            }

            $label = $this->determine_duration_bucket($duration);

            if ($label) {
                $buckets[$label] = $label;
            }
        }

        return array_values($buckets);
    }

    /**
     * @param array<int, array<string, mixed>> $experiences
     *
     * @return array{min:float|null,max:float|null}
     */
    private function collect_price_range(array $experiences): array
    {
        $min = null;
        $max = null;

        foreach ($experiences as $experience) {
            $price = $experience['price_from'] ?? null;

            if (! is_numeric($price)) {
                continue;
            }

            $price = (float) $price;

            if (null === $min || $price < $min) {
                $min = $price;
            }

            if (null === $max || $price > $max) {
                $max = $price;
            }
        }

        return [
            'min' => $min,
            'max' => $max,
        ];
    }

    private function determine_duration_bucket(int $duration): string
    {
        if ($duration <= 0) {
            return '';
        }

        if ($duration <= 90) {
            return __('Up to 1.5h', 'fp-experiences');
        }

        if ($duration <= 180) {
            return __('Up to 3h', 'fp-experiences');
        }

        return __('Full day', 'fp-experiences');
    }
}
