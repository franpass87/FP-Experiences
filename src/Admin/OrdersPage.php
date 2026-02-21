<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Core\Hook\HookableInterface;
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
use function wp_die;
use function wp_safe_redirect;
use function wp_unslash;

final class OrdersPage implements HookableInterface
{
    private const FILTER_KEY = 'fp_exp_filter';
    private const FILTER_VALUE = 'experiences';

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
                self::FILTER_KEY => self::FILTER_VALUE,
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

        $requested = isset($_GET[self::FILTER_KEY])
            ? sanitize_key((string) wp_unslash($_GET[self::FILTER_KEY]))
            : '';

        if (self::FILTER_VALUE !== $requested) {
            return;
        }

        $query->set('meta_query', [
            [
                'key' => '_fp_exp_isolated_checkout',
                'value' => 'yes',
            ],
        ]);
    }
}
