<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP_Exp\Booking\Slots;
use FP_Exp\MeetingPoints\Repository as MeetingPointRepository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\LanguageHelper;
use FP_Exp\Utils\Theme;
use WP_Post;
use WP_Query;

use function __;
use function _n;
use function absint;
use function add_query_arg;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function get_permalink;
use function get_post_field;
use function get_post_meta;
use function get_posts;
use function get_the_post_thumbnail_url;
use function get_the_terms;
use function get_the_title;
use function get_transient;
use function home_url;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function number_format_i18n;
use function preg_match;
use function rawurlencode;
use function remove_query_arg;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;
use function sprintf;
use function strpos;
use function strtolower;
use function trim;
use function wp_get_post_terms;
use function wp_list_pluck;
use function wp_strip_all_tags;
use function wp_timezone;
use function wp_unslash;
use function wp_json_encode;

use const DAY_IN_SECONDS;

final class ListShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_list';

    protected string $template = 'front/list.php';

    protected array $defaults = [
        'filters' => '',
        'per_page' => '',
        'page' => '1',
        'order' => '',
        'orderby' => '',
        'search' => '',
        'view' => '',
        'show_map' => '0',
        'cta' => 'page',
        'badge_lang' => '1',
        'badge_duration' => '1',
        'badge_family' => '1',
        'show_price_from' => '',
        'columns_desktop' => '',
        'columns_tablet' => '',
        'columns_mobile' => '',
        'gap' => '',
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
        'variant' => '',
    ];

    private const ALLOWED_ORDERBY = ['menu_order', 'date', 'title', 'price'];

    private const ALLOWED_ORDER = ['ASC', 'DESC'];

    private const LISTING_PARAM_PREFIX = 'fp_exp_';

    private const PRICE_TRANSIENT_PREFIX = 'fp_exp_price_from_';

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|\WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        unset($content);

        $settings = Helpers::listing_settings();

        $per_page = $this->normalize_positive_int($attributes['per_page'] ?: $settings['per_page'], $settings['per_page']);
        $view = $this->normalize_view((string) $attributes['view']);
        $order = $this->normalize_order($attributes['order'] ?: $settings['order']);
        $orderby = $this->normalize_orderby($attributes['orderby'] ?: $settings['orderby']);
        $show_price_from = $this->normalize_bool($attributes['show_price_from'], $settings['show_price_from']);
        $show_map = $this->normalize_bool($attributes['show_map'] ?? '0', false);
        $cta_mode = $this->normalize_cta((string) $attributes['cta']);
        $badge_flags = [
            'language' => $this->normalize_bool($attributes['badge_lang'] ?? '1', true),
            'duration' => $this->normalize_bool($attributes['badge_duration'] ?? '1', true),
            'experience' => $this->normalize_bool($attributes['badge_family'] ?? '1', true),
        ];

        $variant = $this->normalize_variant((string) $attributes['variant']);

        if ('cards' === $variant) {
            $view = 'grid';
        }

        $layout = [
            'desktop' => $this->normalize_column_setting($attributes['columns_desktop'] ?? ''),
            'tablet' => $this->normalize_column_setting($attributes['columns_tablet'] ?? ''),
            'mobile' => $this->normalize_column_setting($attributes['columns_mobile'] ?? ''),
            'gap' => $this->normalize_gap_setting($attributes['gap'] ?? ''),
        ];

        if ('cards' === $variant) {
            if (0 === $layout['desktop']) {
                $layout['desktop'] = 4;
            }

            if (0 === $layout['tablet']) {
                $layout['tablet'] = 2;
            }

            if (0 === $layout['mobile']) {
                $layout['mobile'] = 1;
            }

            if ('' === $layout['gap']) {
                $layout['gap'] = 'cozy';
            }
        }

        $active_filters = isset($settings['filters']) && is_array($settings['filters'])
            ? array_values(array_filter(array_map('strval', $settings['filters'])))
            : [];

        $state = $this->read_filter_state($active_filters, $attributes, $order, $orderby, $view);

        if ('cards' === $variant) {
            $state['view'] = 'grid';
        }

        $order = $state['order'] ?? $order;
        $orderby = $state['orderby'] ?? $orderby;
        $view = $state['view'] ?? $view;

        if ('cards' === $variant) {
            $view = 'grid';
            $state['view'] = 'grid';
        }

        $query_args = $this->build_query_args($state, $order, $orderby);
        $query = new WP_Query($query_args);

        $experience_ids = array_map('absint', $query->posts);

        if (! empty($state['date'])) {
            $experience_ids = $this->filter_by_date($experience_ids, $state['date']);
        }

        $prices = $this->load_prices($experience_ids);

        if (isset($state['price_min'], $state['price_max'])) {
            $experience_ids = $this->filter_by_price($experience_ids, $prices, $state['price_min'], $state['price_max']);
        }

        if ('price' === $orderby) {
            $experience_ids = $this->sort_by_price($experience_ids, $prices, $order);
        }

        $total = count($experience_ids);
        $used_fallback = false;

        if (0 === $total && ! $this->has_active_content_filters($state, $active_filters)) {
            $fallback_query = new WP_Query([
                'post_type' => 'fp_experience',
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => $per_page,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
            ]);

            $experience_ids = array_map('absint', $fallback_query->posts);
            $total = count($experience_ids);
            $used_fallback = $total > 0;
        }

        $total_pages = max(1, (int) ceil($total / $per_page));
        $current_page = $used_fallback ? 1 : min($total_pages, max(1, (int) $state['page']));

        $page_ids = $used_fallback
            ? $experience_ids
            : array_slice($experience_ids, ($current_page - 1) * $per_page, $per_page);
        $experiences = $this->map_experiences($page_ids, $prices, $show_price_from, $badge_flags, $show_map, $cta_mode);
        $tracking_items = $this->build_tracking_items($page_ids, $prices);

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
            'theme' => $theme,
            'experiences' => $experiences,
            'state' => $state,
            'view' => $view,
            'per_page' => $per_page,
            'order' => $order,
            'orderby' => $orderby,
            'show_price_from' => $show_price_from,
            'show_map' => $show_map,
            'cta_mode' => $cta_mode,
            'badge_flags' => $badge_flags,
            'layout' => $layout,
            'variant' => $variant,
            'filters' => $active_filters,
            'filter_chips' => [],
            'has_active_filters' => $this->has_active_content_filters($state, $active_filters),
            'reset_url' => '',
            'show_filters' => ! empty($active_filters),
            'total' => $total,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'pagination_links' => $this->build_pagination_links($current_page, $total_pages, $state),
            'tracking_items' => $tracking_items,
            'schema_json' => $this->build_schema($experiences),
        ];
    }

    /**
     * @param array<int> $experience_ids
     *
     * @return array<int>
     */
    private function filter_by_date(array $experience_ids, string $date): array
    {
        if (empty($experience_ids)) {
            return [];
        }

        $date = sanitize_text_field($date);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $experience_ids;
        }

        $timezone = wp_timezone();

        try {
            $start = new DateTimeImmutable($date, $timezone);
            $end = $start->add(new DateInterval('P1D'));
        } catch (Exception $exception) {
            unset($exception);

            return $experience_ids;
        }

        $start_utc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $end_utc = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        global $wpdb;

        $table = $wpdb->prefix . 'fp_exp_slots';
        $placeholders = implode(',', array_fill(0, count($experience_ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT DISTINCT experience_id FROM {$table} WHERE experience_id IN ({$placeholders}) AND status = %s AND start_datetime >= %s AND start_datetime < %s",
            ...array_merge($experience_ids, [Slots::STATUS_OPEN, $start_utc, $end_utc])
        );

        $results = $wpdb->get_col($sql);
        if (empty($results)) {
            return [];
        }

        $allowed = array_map('absint', $results);

        return array_values(array_filter(
            $experience_ids,
            static fn (int $id): bool => in_array($id, $allowed, true)
        ));
    }

    /**
     * @param array<string, mixed> $state
     */
    private function build_query_args(array $state, string $order, string $orderby): array
    {
        $args = [
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => in_array($orderby, ['menu_order', 'date', 'title'], true) ? $orderby : 'menu_order',
            'order' => $order,
            'no_found_rows' => true,
        ];

        if (! empty($state['search'])) {
            $args['s'] = $state['search'];
        }

        $tax_query = [];
        $meta_query = [];

        if (! empty($state['theme'])) {
            $tax_query[] = [
                'taxonomy' => 'fp_exp_theme',
                'field' => 'slug',
                'terms' => $state['theme'],
                'operator' => 'IN',
            ];
        }

        if (! empty($state['language'])) {
            $tax_query[] = [
                'taxonomy' => 'fp_exp_language',
                'field' => 'slug',
                'terms' => $state['language'],
                'operator' => 'IN',
            ];
        }

        if (! empty($state['duration'])) {
            $tax_query[] = [
                'taxonomy' => 'fp_exp_duration',
                'field' => 'slug',
                'terms' => $state['duration'],
                'operator' => 'IN',
            ];
        }

        if (! empty($state['family'])) {
            $meta_query[] = [
                'key' => '_fp_experience_badges',
                'value' => '"family-friendly"',
                'compare' => 'LIKE',
            ];
        }

        if (! empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        if (! empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * @param array<int> $ids
     *
     * @return array<int, float>
     */
    private function load_prices(array $ids): array
    {
        $prices = [];

        foreach ($ids as $id) {
            $prices[$id] = $this->get_price_from_cache($id);
        }

        return $prices;
    }

    /**
     * @param array<int> $ids
     * @param array<int, float|null> $prices
     *
     * @return array<int>
     */
    private function filter_by_price(array $ids, array $prices, float $min, float $max): array
    {
        return array_values(array_filter(
            $ids,
            static function (int $id) use ($prices, $min, $max): bool {
                $price = $prices[$id] ?? null;
                if (null === $price) {
                    return false;
                }

                return $price >= $min && $price <= $max;
            }
        ));
    }

    /**
     * @param array<int> $ids
     * @param array<int, float|null> $prices
     *
     * @return array<int>
     */
    private function sort_by_price(array $ids, array $prices, string $order): array
    {
        $sorted = $ids;

        usort($sorted, static function (int $a, int $b) use ($prices, $order): int {
            $price_a = $prices[$a] ?? null;
            $price_b = $prices[$b] ?? null;

            if (null === $price_a && null === $price_b) {
                return 0;
            }

            if (null === $price_a) {
                return 'ASC' === $order ? 1 : -1;
            }

            if (null === $price_b) {
                return 'ASC' === $order ? -1 : 1;
            }

            if ($price_a === $price_b) {
                return 0;
            }

            return ('ASC' === $order ? 1 : -1) * (($price_a < $price_b) ? -1 : 1);
        });

        return $sorted;
    }

    /**
     * @param array<int> $ids
     * @param array<int, float|null> $prices
     * @param array<string, bool> $badge_flags
     *
     * @return array<int, array<string, mixed>>
     */
    private function map_experiences(array $ids, array $prices, bool $show_price_from, array $badge_flags, bool $show_map, string $cta_mode): array
    {
        if (empty($ids)) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'fp_experience',
            'post__in' => $ids,
            'orderby' => 'post__in',
            'numberposts' => count($ids),
        ]);

        $experiences = [];

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $experience = $this->map_experience($post, $prices[$post->ID] ?? null, $show_price_from, $badge_flags, $show_map, $cta_mode);

            if (! empty($experience)) {
                $experiences[] = $experience;
            }
        }

        return $experiences;
    }

    /**
     * @return array<string, mixed>
     */
    private function map_experience(WP_Post $post, ?float $price_from, bool $show_price_from, array $badge_flags, bool $show_map, string $cta_mode): array
    {
        $id = $post->ID;
        $title = get_the_title($post);
        $permalink = $this->resolve_permalink($id, $cta_mode);
        $thumbnail = get_the_post_thumbnail_url($id, 'large') ?: '';
        $highlights = array_slice(Helpers::get_meta_array($id, '_fp_highlights'), 0, 3);
        $short_description = sanitize_text_field((string) get_post_meta($id, '_fp_short_desc', true));
        $duration_minutes = absint((string) get_post_meta($id, '_fp_duration_minutes', true));

        $taxonomy_languages = wp_get_post_terms($id, 'fp_exp_language', ['fields' => 'names']);
        $language_term_names = is_array($taxonomy_languages)
            ? array_values(array_filter(array_map('sanitize_text_field', $taxonomy_languages)))
            : [];

        $languages = Helpers::get_meta_array($id, '_fp_languages');
        if (empty($languages)) {
            $languages = $language_term_names;
        }

        $language_badges = LanguageHelper::build_language_badges($languages);
        $experience_badge_slugs = Helpers::get_meta_array($id, '_fp_experience_badges');

        if (empty($experience_badge_slugs)) {
            $legacy_family_terms = get_the_terms($id, 'fp_exp_family_friendly');
            if (is_array($legacy_family_terms) && ! empty($legacy_family_terms)) {
                $experience_badge_slugs[] = 'family-friendly';
            }
        }

        $experience_badges = Helpers::experience_badge_payload($experience_badge_slugs);
        $duration_label = $this->format_duration($duration_minutes);
        $badges = [];
        $language_labels = [];

        if ($badge_flags['duration'] && $duration_label) {
            $badges[] = [
                'label' => $duration_label,
                'context' => 'duration',
            ];
        }

        if ($badge_flags['language'] && ! empty($language_badges)) {
            foreach ($language_badges as $language) {
                if (! is_array($language)) {
                    continue;
                }

                $code = isset($language['code']) ? (string) $language['code'] : '';
                $sprite = isset($language['sprite']) ? (string) $language['sprite'] : '';
                $label = isset($language['label']) ? (string) $language['label'] : '';
                $aria_label = isset($language['aria_label']) ? (string) $language['aria_label'] : $label;

                if ('' === $code || '' === $sprite) {
                    continue;
                }

                $badges[] = [
                    'label' => $code,
                    'context' => 'language',
                    'language' => [
                        'code' => $code,
                        'sprite' => $sprite,
                        'label' => $label,
                        'aria_label' => $aria_label,
                    ],
                ];

                if ('' !== $label) {
                    $language_labels[] = sanitize_text_field($label);
                }
            }
        }

        if ($badge_flags['experience'] && ! empty($experience_badges)) {
            foreach ($experience_badges as $experience_badge) {
                if (! is_array($experience_badge)) {
                    continue;
                }

                $label = isset($experience_badge['label']) ? (string) $experience_badge['label'] : '';
                if ('' === $label) {
                    continue;
                }

                $slug = isset($experience_badge['id']) ? (string) $experience_badge['id'] : '';

                $badges[] = [
                    'label' => $label,
                    'context' => 'experience',
                    'id' => $slug,
                ];
            }
        }

        $map_url = '';
        if ($show_map) {
            $map_url = $this->resolve_map_url($id);
        }

        $terms = [
            'theme' => wp_list_pluck(wp_get_post_terms($id, 'fp_exp_theme'), 'name'),
            'language' => wp_list_pluck(wp_get_post_terms($id, 'fp_exp_language'), 'name'),
            'duration' => wp_list_pluck(wp_get_post_terms($id, 'fp_exp_duration'), 'name'),
        ];

        $primary_theme = '';
        if (! empty($terms['theme'])) {
            $primary_theme_value = $terms['theme'][0];
            if (is_string($primary_theme_value)) {
                $primary_theme = sanitize_text_field($primary_theme_value);
            }
        }

        $language_labels = array_values(array_unique(array_filter($language_labels)));

        return [
            'id' => $id,
            'title' => $title,
            'permalink' => $permalink,
            'thumbnail' => $thumbnail,
            'highlights' => $highlights,
            'short_description' => $short_description,
            'badges' => $badges,
            'price_from' => $show_price_from ? $price_from : null,
            'price_from_display' => $show_price_from && null !== $price_from ? number_format_i18n($price_from, 0) : '',
            'map_url' => $map_url,
            'experience_badges' => $experience_badges,
            'languages' => $languages,
            'language_badges' => $language_badges,
            'duration_minutes' => $duration_minutes,
            'duration_label' => $duration_label,
            'language_labels' => $language_labels,
            'primary_theme' => $primary_theme,
            'terms' => $terms,
        ];
    }

    private function resolve_permalink(int $experience_id, string $cta_mode): string
    {
        $page_id = absint((string) get_post_meta($experience_id, '_fp_exp_page_id', true));

        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url && 'widget' === $cta_mode && $this->page_has_widget_anchor($page_id)) {
                return $url . '#fp-widget';
            }

            return $url ?: get_permalink($experience_id);
        }

        $fallback = get_permalink($experience_id);
        if (! $fallback) {
            return '';
        }

        if ('widget' === $cta_mode) {
            return $fallback . '#fp-widget';
        }

        return $fallback;
    }

    private function page_has_widget_anchor(int $page_id): bool
    {
        $content = get_post_field('post_content', $page_id);
        if (! is_string($content) || '' === $content) {
            return false;
        }

        return false !== strpos($content, '[fp_exp_page');
    }

    private function resolve_map_url(int $experience_id): string
    {
        $points = MeetingPointRepository::get_meeting_points_for_experience($experience_id);
        $primary = $points['primary'];
        if (! is_array($primary)) {
            return '';
        }

        $lat = $primary['lat'] ?? null;
        $lng = $primary['lng'] ?? null;

        if (is_numeric($lat) && is_numeric($lng)) {
            return sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', rawurlencode((string) $lat), rawurlencode((string) $lng));
        }

        $address = trim((string) ($primary['address'] ?? ''));
        if ($address) {
            return sprintf('https://www.google.com/maps/search/?api=1&query=%s', rawurlencode($address));
        }

        return '';
    }

    private function format_duration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0 && $remaining > 0) {
            return sprintf(__('%1$dh %2$dmin', 'fp-experiences'), $hours, $remaining);
        }

        if ($hours > 0) {
            return sprintf(_n('%dh', '%dh', $hours, 'fp-experiences'), $hours);
        }

        return sprintf(_n('%d minute', '%d minutes', $minutes, 'fp-experiences'), $minutes);
    }

    /**
     * @return array<string, mixed>
     */
    private function read_filter_state(array $active_filters, array $attributes, string $order, string $orderby, string $view_default): array
    {
        $state = [];
        $query = isset($_GET) && is_array($_GET) ? wp_unslash($_GET) : [];

        $state['page'] = $this->normalize_positive_int($query[self::LISTING_PARAM_PREFIX . 'page'] ?? $attributes['page'] ?? '1', 1);
        $state['view'] = $this->normalize_view($query[self::LISTING_PARAM_PREFIX . 'view'] ?? $attributes['view'] ?? $view_default);
        $state['order'] = $this->normalize_order($query[self::LISTING_PARAM_PREFIX . 'order'] ?? $order);
        $state['orderby'] = $this->normalize_orderby($query[self::LISTING_PARAM_PREFIX . 'orderby'] ?? $orderby);

        if (in_array('search', $active_filters, true)) {
            $state['search'] = sanitize_text_field((string) ($query[self::LISTING_PARAM_PREFIX . 'search'] ?? $attributes['search'] ?? ''));
        }

        foreach (['theme', 'language', 'duration'] as $filter) {
            if (! in_array($filter, $active_filters, true)) {
                continue;
            }

            $key = self::LISTING_PARAM_PREFIX . $filter;
            $value = $query[$key] ?? [];
            if (! is_array($value)) {
                $value = '' !== $value ? [$value] : [];
            }

            $state[$filter] = array_values(array_filter(array_map(static function ($item): string {
                $item = is_string($item) ? $item : '';
                $item = sanitize_key($item);

                return $item;
            }, $value)));
        }

        if (in_array('price', $active_filters, true)) {
            $min = isset($query[self::LISTING_PARAM_PREFIX . 'price_min']) ? (float) $query[self::LISTING_PARAM_PREFIX . 'price_min'] : null;
            $max = isset($query[self::LISTING_PARAM_PREFIX . 'price_max']) ? (float) $query[self::LISTING_PARAM_PREFIX . 'price_max'] : null;
            if (null !== $min && null !== $max && $max >= $min) {
                $state['price_min'] = max(0.0, $min);
                $state['price_max'] = max(0.0, $max);
            }
        }

        if (in_array('family', $active_filters, true)) {
            $state['family'] = ! empty($query[self::LISTING_PARAM_PREFIX . 'family']);
        }

        if (in_array('date', $active_filters, true)) {
            $date_value = (string) ($query[self::LISTING_PARAM_PREFIX . 'date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
                $state['date'] = $date_value;
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, string>   $filters
     */
    private function has_active_content_filters(array $state, array $filters): bool
    {
        foreach ($filters as $filter) {
            switch ($filter) {
                case 'search':
                    if (! empty($state['search'])) {
                        return true;
                    }

                    break;
                case 'theme':
                case 'language':
                case 'duration':
                    if (! empty($state[$filter])) {
                        return true;
                    }

                    break;
                case 'price':
                    if (isset($state['price_min'], $state['price_max'])) {
                        return true;
                    }

                    break;
                case 'family':
                    if (! empty($state['family'])) {
                        return true;
                    }

                    break;
                case 'date':
                    if (! empty($state['date'])) {
                        return true;
                    }

                    break;
            }
        }

        return false;
    }

    private function normalize_positive_int($value, int $fallback): int
    {
        $value = absint((string) $value);

        return $value > 0 ? $value : $fallback;
    }

    private function normalize_view(string $view): string
    {
        $view = strtolower($view);
        if (! in_array($view, ['grid', 'list'], true)) {
            return 'grid';
        }

        return $view;
    }

    private function normalize_order($order): string
    {
        $order = strtoupper(sanitize_key((string) $order));

        if (! in_array($order, self::ALLOWED_ORDER, true)) {
            return 'ASC';
        }

        return $order;
    }

    private function normalize_orderby($orderby): string
    {
        $orderby = sanitize_key((string) $orderby);

        if (! in_array($orderby, self::ALLOWED_ORDERBY, true)) {
            return 'menu_order';
        }

        return $orderby;
    }

    private function normalize_bool($value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ('' === $value) {
                return $fallback;
            }

            return in_array($value, ['1', 'yes', 'true', 'on'], true);
        }

        return $fallback;
    }

    private function normalize_cta(string $cta): string
    {
        $cta = strtolower(sanitize_key($cta));

        return in_array($cta, ['page', 'widget'], true) ? $cta : 'page';
    }

    private function normalize_column_setting($value): int
    {
        $value = absint((string) $value);

        if ($value < 1 || $value > 4) {
            return 0;
        }

        return $value;
    }

    private function normalize_gap_setting($value): string
    {
        $value = sanitize_key((string) $value);

        $allowed = ['compact', 'cozy', 'spacious'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function normalize_variant(string $value): string
    {
        $value = sanitize_key($value);

        if ('' === $value) {
            return 'cards';
        }

        $allowed = ['cards', 'classic'];

        return in_array($value, $allowed, true) ? $value : 'cards';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_tracking_items(array $ids, array $prices): array
    {
        $items = [];
        foreach ($ids as $position => $id) {
            $title = wp_strip_all_tags((string) get_the_title($id));

            $items[] = [
                'item_id' => (string) $id,
                'item_name' => $title,
                'index' => $position + 1,
                'price' => isset($prices[$id]) && null !== $prices[$id] ? (float) $prices[$id] : null,
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $experiences
     */
    private function build_schema(array $experiences): string
    {
        if (empty($experiences)) {
            return '';
        }

        $offers = [];
        foreach ($experiences as $experience) {
            if (! isset($experience['price_from']) || null === $experience['price_from']) {
                continue;
            }

            $offers[] = [
                '@type' => 'Offer',
                'price' => $experience['price_from'],
                'priceCurrency' => Helpers::currency_code(),
                'availability' => 'https://schema.org/InStock',
                'itemOffered' => [
                    '@type' => 'Product',
                    'name' => $experience['title'],
                    'url' => $experience['permalink'],
                ],
            ];
        }

        if (empty($offers)) {
            return '';
        }

        return wp_json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                static fn (array $offer, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $offer['itemOffered'],
                ],
                $offers,
                array_keys($offers)
            ),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_pagination_links(int $current_page, int $total_pages, array $state): array
    {
        if ($total_pages <= 1) {
            return [];
        }

        $links = [];
        $base_url = $this->current_url();
        $base_url = remove_query_arg(self::LISTING_PARAM_PREFIX . 'page', $base_url);

        for ($page = 1; $page <= $total_pages; ++$page) {
            $query_args = $this->build_query_from_state($state, $page);
            $url = add_query_arg($query_args, $base_url);
            $links[] = [
                'page' => $page,
                'url' => $url,
                'is_current' => $page === $current_page,
            ];
        }

        return $links;
    }

    /**
     * @return array<string, mixed>
     */
    private function build_query_from_state(array $state, int $page): array
    {
        $args = [];

        $args[self::LISTING_PARAM_PREFIX . 'page'] = $page;
        $args[self::LISTING_PARAM_PREFIX . 'view'] = $state['view'] ?? 'grid';
        $args[self::LISTING_PARAM_PREFIX . 'order'] = $state['order'] ?? 'ASC';
        $args[self::LISTING_PARAM_PREFIX . 'orderby'] = $state['orderby'] ?? 'menu_order';

        if (! empty($state['search'])) {
            $args[self::LISTING_PARAM_PREFIX . 'search'] = $state['search'];
        }

        foreach (['theme', 'language', 'duration'] as $filter) {
            if (! empty($state[$filter]) && is_array($state[$filter])) {
                $values = array_values(array_filter(array_map('sanitize_key', (array) $state[$filter])));
                sort($values);
                if (! empty($values)) {
                    $args[self::LISTING_PARAM_PREFIX . $filter] = $values;
                }
            }
        }

        if (isset($state['price_min'], $state['price_max'])) {
            $args[self::LISTING_PARAM_PREFIX . 'price_min'] = $state['price_min'];
            $args[self::LISTING_PARAM_PREFIX . 'price_max'] = $state['price_max'];
        }

        if (! empty($state['family'])) {
            $args[self::LISTING_PARAM_PREFIX . 'family'] = '1';
        }

        if (! empty($state['date'])) {
            $args[self::LISTING_PARAM_PREFIX . 'date'] = $state['date'];
        }

        return $args;
    }

    private function current_url(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

        return home_url($request_uri);
    }

    private function get_price_from_cache(int $experience_id): ?float
    {
        $key = self::PRICE_TRANSIENT_PREFIX . $experience_id;
        $cached = get_transient($key);
        if (false !== $cached) {
            if ('none' === $cached) {
                return null;
            }

            return is_numeric($cached) ? (float) $cached : null;
        }

        $price = $this->calculate_price_from_meta($experience_id);
        if (null !== $price) {
            set_transient($key, $price, DAY_IN_SECONDS);
        } else {
            set_transient($key, 'none', DAY_IN_SECONDS);
        }

        return $price;
    }

    private function calculate_price_from_meta(int $experience_id): ?float
    {
        $tickets = get_post_meta($experience_id, '_fp_ticket_types', true);
        if (! is_array($tickets) || empty($tickets)) {
            return null;
        }

        $min_price = null;
        foreach ($tickets as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            $price = (float) $ticket['price'];
            if ($price <= 0) {
                continue;
            }

            if (null === $min_price || $price < $min_price) {
                $min_price = $price;
            }
        }

        return $min_price;
    }
}
