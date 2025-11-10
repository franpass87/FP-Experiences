<?php

declare(strict_types=1);

namespace FP_Exp\Gift;

use FP_Exp\Utils\Helpers;
use WP_Post;

use function absint;
use function add_action;
use function add_filter;
use function add_query_arg;
use function admin_url;
use function date_i18n;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function get_option;
use function get_post_meta;
use function get_post_modified_time;
use function get_post_time;
use function get_the_title;
use function in_array;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function time;
use function update_post_meta;
use function wp_create_nonce;
use function wp_die;
use function wp_get_current_user;
use function wp_safe_redirect;
use function wp_verify_nonce;
use function register_post_type;

use const DAY_IN_SECONDS;

final class VoucherCPT
{
    public const POST_TYPE = 'fp_exp_gift_voucher';

    public function register_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'register_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_filter('post_row_actions', [$this, 'filter_row_actions'], 10, 2);
        add_action('admin_post_fp_exp_gift_update_status', [$this, 'handle_admin_action']);
        add_action('admin_notices', [$this, 'maybe_render_notice']);
    }

    public function register_post_type(): void
    {
        $labels = [
            'name' => esc_html__('Gift Vouchers', 'fp-experiences'),
            'singular_name' => esc_html__('Gift Voucher', 'fp-experiences'),
            'menu_name' => esc_html__('Gift vouchers', 'fp-experiences'),
            'add_new' => esc_html__('Add New', 'fp-experiences'),
            'add_new_item' => esc_html__('Add New Voucher', 'fp-experiences'),
            'edit_item' => esc_html__('Edit Voucher', 'fp-experiences'),
            'view_item' => esc_html__('View Voucher', 'fp-experiences'),
            'search_items' => esc_html__('Search Vouchers', 'fp-experiences'),
        ];

        $capabilities = [
            'edit_posts' => 'fp_exp_manage',
            'edit_others_posts' => 'fp_exp_manage',
            'publish_posts' => 'fp_exp_manage',
            'read_private_posts' => 'fp_exp_manage',
            'delete_posts' => 'fp_exp_manage',
            'delete_private_posts' => 'fp_exp_manage',
            'delete_published_posts' => 'fp_exp_manage',
            'delete_others_posts' => 'fp_exp_manage',
        ];

        register_post_type(self::POST_TYPE, [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'menu_position' => 61,
            'supports' => ['title'],
            'capability_type' => 'fp_exp_gift_voucher',
            'capabilities' => $capabilities,
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
        $new = [];
        $new['cb'] = $columns['cb'] ?? '<input type="checkbox" />';
        $new['title'] = esc_html__('Voucher', 'fp-experiences');
        $new['experience'] = esc_html__('Experience', 'fp-experiences');
        $new['recipient'] = esc_html__('Recipient', 'fp-experiences');
        $new['status'] = esc_html__('Status', 'fp-experiences');
        $new['valid_until'] = esc_html__('Valid until', 'fp-experiences');
        $new['order'] = esc_html__('Order', 'fp-experiences');

        return $new;
    }

    public function render_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'experience':
                $experience_id = absint((string) get_post_meta($post_id, '_fp_exp_gift_experience_id', true));
                if ($experience_id > 0) {
                    $title = get_the_title($experience_id);
                    if ($title) {
                        echo esc_html($title);
                    } else {
                        esc_html_e('Deleted experience', 'fp-experiences');
                    }
                } else {
                    echo '—';
                }
                break;
            case 'recipient':
                $recipient = get_post_meta($post_id, '_fp_exp_gift_recipient', true);
                if (! is_array($recipient)) {
                    echo '—';
                    break;
                }
                $name = sanitize_text_field((string) ($recipient['name'] ?? ''));
                $email = sanitize_text_field((string) ($recipient['email'] ?? ''));
                if ($name) {
                    echo esc_html($name);
                    if ($email) {
                        echo '<br /><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                    }
                } elseif ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '—';
                }
                break;
            case 'status':
                $status = sanitize_key((string) get_post_meta($post_id, '_fp_exp_gift_status', true));
                echo esc_html($this->format_status_label($status));
                break;
            case 'valid_until':
                $valid_until = (int) get_post_meta($post_id, '_fp_exp_gift_valid_until', true);
                if ($valid_until > 0) {
                    echo esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until));
                } else {
                    echo '—';
                }
                break;
            case 'order':
                $order_id = absint((string) get_post_meta($post_id, '_fp_exp_gift_order_id', true));
                if ($order_id > 0) {
                    $url = esc_url(add_query_arg([
                        'post' => $order_id,
                        'action' => 'edit',
                    ], admin_url('post.php')));
                    echo '<a href="' . $url . '">#' . esc_html((string) $order_id) . '</a>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * @param array<string, string> $actions
     *
     * @return array<string, string>
     */
    public function filter_row_actions(array $actions, WP_Post $post): array
    {
        if (self::POST_TYPE !== $post->post_type || ! Helpers::can_manage_fp()) {
            return $actions;
        }

        $status = sanitize_key((string) get_post_meta($post->ID, '_fp_exp_gift_status', true));
        $nonce = wp_create_nonce('fp_exp_gift_action_' . $post->ID);

        if (! in_array($status, ['cancelled', 'expired', 'redeemed'], true)) {
            $cancel_url = add_query_arg([
                'action' => 'fp_exp_gift_update_status',
                'voucher' => $post->ID,
                'operation' => 'cancel',
                '_wpnonce' => $nonce,
            ], admin_url('admin-post.php'));
            $actions['fp_exp_cancel'] = '<a href="' . esc_url($cancel_url) . '">' . esc_html__('Cancel', 'fp-experiences') . '</a>';
        }

        if (! in_array($status, ['cancelled', 'expired'], true)) {
            $extend_url = add_query_arg([
                'action' => 'fp_exp_gift_update_status',
                'voucher' => $post->ID,
                'operation' => 'extend',
                '_wpnonce' => $nonce,
            ], admin_url('admin-post.php'));
            $actions['fp_exp_extend'] = '<a href="' . esc_url($extend_url) . '">' . esc_html__('Extend +30 days', 'fp-experiences') . '</a>';
        }

        return $actions;
    }

    public function handle_admin_action(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('You do not have permission to manage vouchers.', 'fp-experiences'));
        }

        $voucher_id = isset($_GET['voucher']) ? absint((string) $_GET['voucher']) : 0;
        $operation = isset($_GET['operation']) ? sanitize_key((string) $_GET['operation']) : '';
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';

        if ($voucher_id <= 0 || ! wp_verify_nonce($nonce, 'fp_exp_gift_action_' . $voucher_id)) {
            wp_die(esc_html__('Invalid action.', 'fp-experiences'));
        }

        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
        $redirect = add_query_arg([
            'post_type' => self::POST_TYPE,
            'fp_exp_gift_notice' => 'noop',
        ], admin_url('edit.php'));

        if ('cancel' === $operation && ! in_array($status, ['cancelled', 'redeemed'], true)) {
            update_post_meta($voucher_id, '_fp_exp_gift_status', 'cancelled');
            $this->append_log($voucher_id, 'cancelled');
            $this->sync_table($voucher_id);
            $redirect = add_query_arg('fp_exp_gift_notice', 'cancelled', $redirect);
        } elseif ('extend' === $operation && ! in_array($status, ['cancelled', 'expired'], true)) {
            $valid_until = (int) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true);
            if ($valid_until <= 0) {
                $valid_until = time();
            }
            $extended = $valid_until + (DAY_IN_SECONDS * 30);
            update_post_meta($voucher_id, '_fp_exp_gift_valid_until', $extended);
            $this->append_log($voucher_id, 'extended');
            $this->sync_table($voucher_id);
            $redirect = add_query_arg('fp_exp_gift_notice', 'extended', $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function maybe_render_notice(): void
    {
        $screen = get_current_screen();
        if (! $screen || $screen->id !== 'edit-' . self::POST_TYPE) {
            return;
        }

        $notice = isset($_GET['fp_exp_gift_notice']) ? sanitize_key((string) $_GET['fp_exp_gift_notice']) : '';
        if (! $notice || 'noop' === $notice) {
            return;
        }

        $message = '';
        if ('cancelled' === $notice) {
            $message = esc_html__('The voucher has been cancelled.', 'fp-experiences');
        } elseif ('extended' === $notice) {
            $message = esc_html__('The voucher validity was extended by 30 days.', 'fp-experiences');
        }

        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        }
    }

    private function sync_table(int $voucher_id): void
    {
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));

        if ('' === $code) {
            return;
        }

        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
        if ('' === $status) {
            $status = 'pending';
        }

        $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
        $valid_until = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true));
        $value = (float) get_post_meta($voucher_id, '_fp_exp_gift_value', true);
        $currency = sanitize_text_field((string) get_post_meta($voucher_id, '_fp_exp_gift_currency', true));
        $created = (int) get_post_time('U', true, $voucher_id, true);
        $modified = (int) get_post_modified_time('U', true, $voucher_id, true);

        VoucherTable::upsert([
            'voucher_id' => $voucher_id,
            'code' => $code,
            'status' => $status,
            'experience_id' => $experience_id,
            'valid_until' => $valid_until,
            'value' => $value,
            'currency' => $currency,
            'created_at' => $created ?: null,
            'updated_at' => $modified ?: time(),
        ]);
    }

    private function append_log(int $voucher_id, string $event): void
    {
        $logs = get_post_meta($voucher_id, '_fp_exp_gift_logs', true);
        $logs = is_array($logs) ? $logs : [];

        $user = wp_get_current_user();
        $logs[] = [
            'event' => $event,
            'timestamp' => time(),
            'user' => $user && $user->exists() ? $user->ID : 0,
        ];

        update_post_meta($voucher_id, '_fp_exp_gift_logs', $logs);
    }

    private function format_status_label(string $status): string
    {
        return match ($status) {
            'pending' => esc_html__('Pending payment', 'fp-experiences'),
            'active' => esc_html__('Active', 'fp-experiences'),
            'redeemed' => esc_html__('Redeemed', 'fp-experiences'),
            'expired' => esc_html__('Expired', 'fp-experiences'),
            'cancelled' => esc_html__('Cancelled', 'fp-experiences'),
            default => esc_html__('Unknown', 'fp-experiences'),
        };
    }
}
