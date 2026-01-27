<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP_Exp\Application\Settings\GetSettingsUseCase;
use FP_Exp\Booking\Slots;
use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\MeetingPoints\Repository as MeetingPointRepository;
use FP_Exp\Services\Cache\CacheInterface;
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
use function wp_get_attachment_image_url;
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
    private ?ExperienceRepositoryInterface $experienceRepository = null;
    private ?GetSettingsUseCase $getSettingsUseCase = null;
    private ?CacheInterface $cache = null;

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
        'event_only' => '0',
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
                $layout['desktop'] = 2;
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

        $state['event_only'] = $this->normalize_bool($attributes['event_only'] ?? '0', false);
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

        // Separate events from regular experiences
        $event_ids = [];
        $regular_ids = [];
        foreach ($experience_ids as $id) {
            $is_event = (bool) get_post_meta($id, '_fp_is_event', true);
            if ($is_event) {
                $event_ids[] = $id;
            } else {
                $regular_ids[] = $id;
            }
        }

        // Sort events by event_datetime (ascending)
        if (!empty($event_ids)) {
            usort($event_ids, function ($a, $b) {
                $date_a = (string) get_post_meta($a, '_fp_event_datetime', true);
                $date_b = (string) get_post_meta($b, '_fp_event_datetime', true);
                if ($date_a === '' && $date_b === '') {
                    return 0;
                }
                if ($date_a === '') {
                    return 1;
                }
                if ($date_b === '') {
                    return -1;
                }
                return strcmp($date_a, $date_b);
            });
        }

        // Put events first
        $experience_ids = array_merge($event_ids, $regular_ids);

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

        if (! empty($state['language'])) {
            $tax_query[] = [
                'taxonomy' => 'fp_exp_language',
                'field' => 'slug',
                'terms' => $state['language'],
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

        if (! empty($state['event_only'])) {
            $meta_query[] = [
                'key' => '_fp_is_event',
                'value' => '1',
                'compare' => '=',
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
        $title = $post->post_title; // Use direct property instead of get_the_title() to avoid global post interference
        $permalink = $this->resolve_permalink($id, $cta_mode);
        $thumbnail = $this->get_experience_thumbnail($id);
        $highlights = array_slice(Helpers::get_meta_array($id, '_fp_highlights'), 0, 3);
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $short_description = '';
        $duration_minutes = 0;
        if ($repo !== null) {
            $short_description = sanitize_text_field((string) $repo->getMeta($id, '_fp_short_desc', ''));
            $duration_minutes = absint((string) $repo->getMeta($id, '_fp_duration_minutes', 0));
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $short_description = sanitize_text_field((string) get_post_meta($id, '_fp_short_desc', true));
            $duration_minutes = absint((string) get_post_meta($id, '_fp_duration_minutes', true));
        }

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

        $experience_badges = Helpers::experience_badge_payload($experience_badge_slugs);
        $duration_label = $this->format_duration($duration_minutes);
        $is_event = (bool) get_post_meta($id, '_fp_is_event', true);
        $event_datetime = $is_event ? (string) get_post_meta($id, '_fp_event_datetime', true) : '';
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
            'language' => wp_list_pluck(wp_get_post_terms($id, 'fp_exp_language'), 'name'),
        ];

        $primary_theme = '';

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
            'is_event' => $is_event,
            'event_datetime' => $event_datetime,
        ];
    }

    private function resolve_permalink(int $experience_id, string $cta_mode): string
    {
        // Check if this experience has a dedicated page
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $page_id = 0;
        if ($repo !== null) {
            $page_id = absint((string) $repo->getMeta($experience_id, '_fp_exp_page_id', 0));
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $page_id = absint((string) get_post_meta($experience_id, '_fp_exp_page_id', true));
        }

        // Debug logging for link resolution issues
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $debug_info = [
                'experience_id' => $experience_id,
                'page_id' => $page_id,
            ];
        }

        // If experience has a dedicated page, use that page's URL
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG && isset($debug_info)) {
                $debug_info['page_url'] = $url ?: 'false';
                error_log('[FP-EXP-LIST] Page link: ' . wp_json_encode($debug_info));
            }
            
            if ($url && 'widget' === $cta_mode && $this->page_has_widget_anchor($page_id)) {
                return $url . '#fp-widget';
            }

            // Fallback to experience permalink if page permalink fails
            $experience_url = get_permalink($experience_id);
            return $url ?: ($experience_url ?: '');
        }

        // Use experience post permalink directly
        $fallback = get_permalink($experience_id);
        
        if (defined('WP_DEBUG') && WP_DEBUG && isset($debug_info)) {
            $debug_info['experience_url'] = $fallback ?: 'false';
            error_log('[FP-EXP-LIST] Experience link: ' . wp_json_encode($debug_info));
        }
        
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
            return sprintf(__('%dh %02dm', 'fp-experiences'), $hours, $remaining);
        }

        if ($hours > 0) {
            return sprintf(__('%dh', 'fp-experiences'), $hours);
        }

        // Use simple __ instead of _n to avoid issues with .mo plural compilation
        // Note: We use Italian 'minuti' as source string because that's our msgid in .po files
        return sprintf(__('%d minuti', 'fp-experiences'), $minutes);
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
        
        // Try to use cache service if available
        $cache = $this->getCache();
        $cached = null;
        if ($cache !== null) {
            $cached = $cache->get($key);
        } else {
            // Fallback to direct get_transient for backward compatibility
            $cached = get_transient($key);
        }
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
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $tickets = [];
        if ($repo !== null) {
            $tickets = $repo->getMeta($experience_id, '_fp_ticket_types', []);
            if (!is_array($tickets)) {
                $tickets = [];
            }
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $tickets = get_post_meta($experience_id, '_fp_ticket_types', true);
            if (!is_array($tickets)) {
                $tickets = [];
            }
        }
        
        // If _fp_ticket_types doesn't have the flag, try reading from _fp_exp_pricing
        $pricing_meta = null;
        if (empty($tickets)) {
            $pricing_meta = get_post_meta($experience_id, '_fp_exp_pricing', true);
            if (is_array($pricing_meta) && isset($pricing_meta['tickets']) && is_array($pricing_meta['tickets'])) {
                $tickets = $pricing_meta['tickets'];
            }
        }
        
        // Also check if tickets from _fp_ticket_types don't have use_as_price_from flag
        // If so, try to get it from _fp_exp_pricing
        $has_primary_flag = false;
        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                if (is_array($ticket) && isset($ticket['use_as_price_from'])) {
                    $has_primary_flag = true;
                    break;
                }
            }
        }
        
        // If no flag found in _fp_ticket_types, try _fp_exp_pricing
        if (!$has_primary_flag && $pricing_meta === null) {
            $pricing_meta = get_post_meta($experience_id, '_fp_exp_pricing', true);
            if (is_array($pricing_meta) && isset($pricing_meta['tickets']) && is_array($pricing_meta['tickets'])) {
                // Merge tickets from _fp_exp_pricing, prioritizing those with use_as_price_from
                $pricing_tickets = $pricing_meta['tickets'];
                if (!empty($tickets)) {
                    // Update existing tickets with flag from pricing meta
                    foreach ($pricing_tickets as $pricing_ticket) {
                        if (!is_array($pricing_ticket)) {
                            continue;
                        }
                        // Find matching ticket by label or slug
                        foreach ($tickets as $index => $ticket) {
                            if (!is_array($ticket)) {
                                continue;
                            }
                            $match = false;
                            if (isset($pricing_ticket['label']) && isset($ticket['label']) && $pricing_ticket['label'] === $ticket['label']) {
                                $match = true;
                            } elseif (isset($pricing_ticket['slug']) && isset($ticket['slug']) && $pricing_ticket['slug'] === $ticket['slug']) {
                                $match = true;
                            }
                            if ($match && isset($pricing_ticket['use_as_price_from'])) {
                                $tickets[$index]['use_as_price_from'] = $pricing_ticket['use_as_price_from'];
                                $has_primary_flag = true;
                            }
                        }
                    }
                } else {
                    // Use tickets from pricing meta directly
                    $tickets = $pricing_tickets;
                }
            }
        }
        
        if (empty($tickets)) {
            return null;
        }

        // First, look for a ticket marked as "use_as_price_from"
        // Check both boolean true and string "1" for compatibility
        foreach ($tickets as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            // Check if this ticket is marked as primary price display
            $is_primary = isset($ticket['use_as_price_from']) && (
                $ticket['use_as_price_from'] === true 
                || $ticket['use_as_price_from'] === '1' 
                || $ticket['use_as_price_from'] === 1
                || (is_string($ticket['use_as_price_from']) && strtolower(trim($ticket['use_as_price_from'])) === 'true')
                || (is_string($ticket['use_as_price_from']) && strtolower(trim($ticket['use_as_price_from'])) === 'yes')
            );

            if ($is_primary) {
                $price = (float) $ticket['price'];
                if ($price > 0) {
                    return $price;
                }
            }
        }

        // If no ticket is marked, fall back to the lowest price
        $min_price = null;
        foreach ($tickets as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            $price = (float) $ticket['price'];
            if ($price > 0 && (null === $min_price || $price < $min_price)) {
                $min_price = $price;
            }
        }

        return $min_price;
    }

    /**
     * Get the thumbnail URL for an experience, falling back to hero image if needed.
     */
    private function get_experience_thumbnail(int $experience_id): string
    {
        // Try the WordPress featured image first
        $thumbnail = get_the_post_thumbnail_url($experience_id, 'large');
        if ($thumbnail) {
            return (string) $thumbnail;
        }

        // Fall back to the hero image meta
        $hero_image_id = absint((string) get_post_meta($experience_id, '_fp_hero_image_id', true));
        if ($hero_image_id > 0) {
            $hero_url = wp_get_attachment_image_url($hero_image_id, 'large');
            if ($hero_url) {
                return (string) $hero_url;
            }
        }

        // Fall back to the first gallery image if available
        $gallery_ids = get_post_meta($experience_id, '_fp_gallery_ids', true);
        if (is_array($gallery_ids) && ! empty($gallery_ids)) {
            $first_gallery_id = absint((string) reset($gallery_ids));
            if ($first_gallery_id > 0) {
                $gallery_url = wp_get_attachment_image_url($first_gallery_id, 'large');
                if ($gallery_url) {
                    return (string) $gallery_url;
                }
            }
        }

        return '';
    }

    /**
     * Get ExperienceRepository from container if available.
     */
    private function getExperienceRepository(): ?ExperienceRepositoryInterface
    {
        if ($this->experienceRepository !== null) {
            return $this->experienceRepository;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(ExperienceRepositoryInterface::class)) {
                return null;
            }

            $this->experienceRepository = $container->make(ExperienceRepositoryInterface::class);
            return $this->experienceRepository;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get GetSettingsUseCase from container if available.
     */
    private function getGetSettingsUseCase(): ?GetSettingsUseCase
    {
        if ($this->getSettingsUseCase !== null) {
            return $this->getSettingsUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(GetSettingsUseCase::class)) {
                return null;
            }

            $this->getSettingsUseCase = $container->make(GetSettingsUseCase::class);
            return $this->getSettingsUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get CacheInterface from container if available.
     */
    private function getCache(): ?CacheInterface
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(CacheInterface::class)) {
                return null;
            }

            $this->cache = $container->make(CacheInterface::class);
            return $this->cache;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
