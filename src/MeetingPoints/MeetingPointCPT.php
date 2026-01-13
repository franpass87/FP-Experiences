<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use WP_Query;

use function add_action;
use function add_filter;
use function esc_html;
use function esc_html__;
use function get_post_meta;
use function register_post_type;
use function sanitize_text_field;
use function is_admin;
use function wp_kses_post;
use function wp_parse_args;

final class MeetingPointCPT implements HookableInterface
{
    public const POST_TYPE = 'fp_meeting_point';

    public function register_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'register_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_action('pre_get_posts', [$this, 'extend_admin_search']);
    }

    public function register_post_type(): void
    {
        $labels = [
            'name' => esc_html__('Meeting Points', 'fp-experiences'),
            'singular_name' => esc_html__('Meeting Point', 'fp-experiences'),
            'add_new' => esc_html__('Add Meeting Point', 'fp-experiences'),
            'add_new_item' => esc_html__('Add Meeting Point', 'fp-experiences'),
            'edit_item' => esc_html__('Edit Meeting Point', 'fp-experiences'),
            'new_item' => esc_html__('New Meeting Point', 'fp-experiences'),
            'view_item' => esc_html__('View Meeting Point', 'fp-experiences'),
            'search_items' => esc_html__('Search Meeting Points', 'fp-experiences'),
            'not_found' => esc_html__('No meeting points found.', 'fp-experiences'),
            'menu_name' => esc_html__('Meeting Points', 'fp-experiences'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
                'show_in_menu' => false,
            'supports' => ['title'],
            'show_in_rest' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /**
     * @param array<string, string> $columns
     *
     * @return array<string, string>
     */
    public function register_columns(array $columns): array
    {
        $positioned = [];
        foreach ($columns as $key => $label) {
            $positioned[$key] = $label;
            if ('title' === $key) {
                $positioned['fp_mp_address'] = esc_html__('Address', 'fp-experiences');
                $positioned['fp_mp_geo'] = esc_html__('Lat/Lng', 'fp-experiences');
            }
        }

        return $positioned;
    }

    public function render_column(string $column, int $post_id): void
    {
        if ('fp_mp_address' === $column) {
            $address = sanitize_text_field((string) get_post_meta($post_id, '_fp_mp_address', true));
            echo esc_html($address);

            return;
        }

        if ('fp_mp_geo' === $column) {
            $lat = sanitize_text_field((string) get_post_meta($post_id, '_fp_mp_lat', true));
            $lng = sanitize_text_field((string) get_post_meta($post_id, '_fp_mp_lng', true));
            $value = trim($lat . ($lat && $lng ? ', ' : '') . $lng);
            echo esc_html($value);
        }
    }

    public function extend_admin_search(WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get('post_type')) {
            return;
        }

        $search = $query->get('s');
        if (! is_string($search) || '' === $search) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = [
            'key' => '_fp_mp_address',
            'value' => $search,
            'compare' => 'LIKE',
        ];

        $query->set('meta_query', $meta_query);
    }
}
