<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;
use WP_Query;

use function add_action;
use function add_filter;
use function add_query_arg;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function is_admin;
use function sanitize_key;
use function strpos;
use function wp_die;
use function wp_safe_redirect;
use function wp_unslash;

final class OrdersPage
{
    private const ORDER_ITEM_TYPE = 'fp_experience_item';

    public function register_hooks(): void
    {
        add_action('pre_get_posts', [$this, 'maybe_filter_orders']);
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp() || ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Non hai i permessi per visualizzare gli ordini delle esperienze.', 'fp-experiences'));
        }

        $url = add_query_arg(
            [
                'post_type' => 'shop_order',
                'fp_exp_order_item_type' => self::ORDER_ITEM_TYPE,
            ],
            admin_url('edit.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public function maybe_filter_orders($query): void
    {
        if (! $query instanceof WP_Query || ! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ('shop_order' !== $query->get('post_type')) {
            return;
        }

        $requested = isset($_GET['fp_exp_order_item_type']) ? sanitize_key((string) wp_unslash($_GET['fp_exp_order_item_type'])) : '';

        if (self::ORDER_ITEM_TYPE !== $requested) {
            return;
        }

        $query->set('fp_exp_order_item_type', self::ORDER_ITEM_TYPE);

        add_filter('posts_join', [$this, 'filter_orders_join'], 10, 2);
        add_filter('posts_where', [$this, 'filter_orders_where'], 10, 2);
        add_filter('posts_distinct', [$this, 'filter_orders_distinct'], 10, 2);
    }

    public function filter_orders_join(string $join, WP_Query $query): string
    {
        if (self::ORDER_ITEM_TYPE !== $query->get('fp_exp_order_item_type')) {
            return $join;
        }

        global $wpdb;

        if (false === strpos($join, 'fp_exp_items')) {
            $join .= " INNER JOIN {$wpdb->prefix}woocommerce_order_items fp_exp_items ON fp_exp_items.order_id = {$wpdb->posts}.ID";
        }

        return $join;
    }

    public function filter_orders_where(string $where, WP_Query $query): string
    {
        if (self::ORDER_ITEM_TYPE !== $query->get('fp_exp_order_item_type')) {
            return $where;
        }

        global $wpdb;

        return $where . $wpdb->prepare(' AND fp_exp_items.order_item_type = %s', self::ORDER_ITEM_TYPE);
    }

    public function filter_orders_distinct(string $distinct, WP_Query $query): string
    {
        if (self::ORDER_ITEM_TYPE !== $query->get('fp_exp_order_item_type')) {
            return $distinct;
        }

        return 'DISTINCT';
    }
}
