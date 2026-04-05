<?php

declare(strict_types=1);

namespace FP_Exp\Gift;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use WP_Post;

use function absint;
use function add_action;
use function add_filter;
use function add_query_arg;
use function admin_url;
use function __;
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

final class VoucherCPT implements HookableInterface
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
        add_action('current_screen', [$this, 'bootstrap_gift_voucher_list_ui']);
    }

    /**
     * Abilita il layout FP (banner + card) sulla lista voucher (`edit.php` core non espone filtri sul `.wrap`).
     *
     * @param \WP_Screen|null $screen Schermata corrente.
     */
    public function bootstrap_gift_voucher_list_ui($screen): void
    {
        if (! $screen instanceof \WP_Screen || 'edit-' . self::POST_TYPE !== $screen->id) {
            return;
        }

        add_action('admin_print_footer_scripts', [$this, 'print_gift_voucher_list_layout_script'], 50);
    }

    /**
     * Stampa script che aggiunge classi al `.wrap`, banner gradiente DMS e card attorno a viste + tabella.
     */
    public function print_gift_voucher_list_layout_script(): void
    {
        $i18n = [
            'breadcrumbLabel' => __('Percorso di navigazione', 'fp-experiences'),
            'breadcrumbRoot' => __('FP Experiences', 'fp-experiences'),
            'breadcrumbSep' => "\u{203a}",
            'breadcrumbCurrent' => __('Gift vouchers', 'fp-experiences'),
            'srTitle' => __('Gift Vouchers', 'fp-experiences'),
            'pageTitle' => __('Gift vouchers', 'fp-experiences'),
            'pageDescription' => __('Gestisci i voucher emessi: destinatario, stato, validità e ordine WooCommerce collegato.', 'fp-experiences'),
            'cardTitle' => __('Elenco voucher', 'fp-experiences'),
            'dashboardUrl' => admin_url('admin.php?page=fp_exp_dashboard'),
            'version' => defined('FP_EXP_VERSION') ? FP_EXP_VERSION : '0',
        ];
        ?>
<script>
(function () {
    var i18n = <?php echo wp_json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    var wrap = document.querySelector('#wpbody-content > .wrap');
    if (!wrap || wrap.getAttribute('data-fp-exp-gift-layout') === '1') {
        return;
    }
    wrap.setAttribute('data-fp-exp-gift-layout', '1');
    wrap.classList.add('fp-exp-admin-page', 'fp-exp-gift-voucher-list');

    var sr = document.createElement('h1');
    sr.className = 'screen-reader-text';
    sr.textContent = i18n.srTitle;

    var header = document.createElement('div');
    header.className = 'fpexp-page-header';

    var nav = document.createElement('nav');
    nav.className = 'fp-exp-admin__breadcrumb';
    nav.setAttribute('aria-label', i18n.breadcrumbLabel);
    var aDash = document.createElement('a');
    aDash.href = i18n.dashboardUrl;
    aDash.textContent = i18n.breadcrumbRoot;
    nav.appendChild(aDash);
    nav.appendChild(document.createTextNode(' '));
    var sep = document.createElement('span');
    sep.setAttribute('aria-hidden', 'true');
    sep.textContent = i18n.breadcrumbSep + ' ';
    nav.appendChild(sep);
    var cur = document.createElement('span');
    cur.textContent = i18n.breadcrumbCurrent;
    nav.appendChild(cur);
    header.appendChild(nav);

    var content = document.createElement('div');
    content.className = 'fpexp-page-header-content';
    var h2 = document.createElement('h2');
    h2.className = 'fpexp-page-header-title';
    h2.setAttribute('aria-hidden', 'true');
    var icon = document.createElement('span');
    icon.className = 'dashicons dashicons-tickets-alt';
    icon.setAttribute('aria-hidden', 'true');
    h2.appendChild(icon);
    h2.appendChild(document.createTextNode(' ' + i18n.pageTitle));
    content.appendChild(h2);
    var desc = document.createElement('p');
    desc.className = 'fpexp-page-header-desc';
    desc.textContent = i18n.pageDescription;
    content.appendChild(desc);
    header.appendChild(content);

    var right = document.createElement('div');
    right.className = 'fp-exp-gift-voucher-list__header-right';
    var actions = document.createElement('div');
    actions.className = 'fp-exp-gift-voucher-list__actions';
    var addBtn = wrap.querySelector('a.page-title-action');
    if (addBtn) {
        actions.appendChild(addBtn);
    }
    right.appendChild(actions);
    var badge = document.createElement('span');
    badge.className = 'fpexp-page-header-badge';
    badge.textContent = 'v' + String(i18n.version);
    right.appendChild(badge);
    header.appendChild(right);

    var legacyH1 = wrap.querySelector('h1.wp-heading-inline');
    if (legacyH1) {
        legacyH1.classList.add('fp-exp-gift-voucher-list__legacy-title');
    }
    var legacyHr = wrap.querySelector('hr.wp-header-end');
    if (legacyHr) {
        legacyHr.classList.add('fp-exp-gift-voucher-list__legacy-hr');
    }

    wrap.insertBefore(header, wrap.firstChild);
    wrap.insertBefore(sr, header);

    var subsub = wrap.querySelector('ul.subsubsub');
    var form = wrap.querySelector('form#posts-filter');
    if (subsub && form && !wrap.querySelector('.fp-exp-gift-voucher-list__table-card')) {
        var card = document.createElement('div');
        card.className = 'fp-exp-dms-card fp-exp-gift-voucher-list__table-card';
        var cardHead = document.createElement('div');
        cardHead.className = 'fp-exp-dms-card-header';
        var cardHeadLeft = document.createElement('div');
        cardHeadLeft.className = 'fp-exp-dms-card-header-left';
        var cardIcon = document.createElement('span');
        cardIcon.className = 'dashicons dashicons-list-view';
        cardIcon.setAttribute('aria-hidden', 'true');
        cardHeadLeft.appendChild(cardIcon);
        var cardH = document.createElement('h2');
        cardH.textContent = i18n.cardTitle;
        cardHeadLeft.appendChild(cardH);
        cardHead.appendChild(cardHeadLeft);
        var cardBody = document.createElement('div');
        cardBody.className = 'fp-exp-dms-card-body';
        subsub.parentNode.insertBefore(card, subsub);
        card.appendChild(cardHead);
        card.appendChild(cardBody);
        cardBody.appendChild(subsub);
        cardBody.appendChild(form);
    }
})();
</script>
        <?php
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
                echo $this->render_status_badge($status);
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

    /**
     * Etichetta tradotta per lo stato voucher (testo puro).
     */
    private function get_status_label(string $status): string
    {
        return match ($status) {
            'pending' => __('Pending payment', 'fp-experiences'),
            'active' => __('Active', 'fp-experiences'),
            'redeemed' => __('Redeemed', 'fp-experiences'),
            'expired' => __('Expired', 'fp-experiences'),
            'cancelled' => __('Cancelled', 'fp-experiences'),
            default => __('Unknown', 'fp-experiences'),
        };
    }

    /**
     * Badge design system per la colonna stato in lista.
     */
    private function render_status_badge(string $status): string
    {
        $label = $this->get_status_label($status);
        $modifier = match ($status) {
            'active' => 'fp-exp-dms-badge-success',
            'redeemed' => 'fp-exp-dms-badge-info',
            'expired' => 'fp-exp-dms-badge-warning',
            'cancelled' => 'fp-exp-dms-badge-danger',
            'pending' => 'fp-exp-dms-badge-warning',
            default => 'fp-exp-dms-badge-neutral',
        };

        return '<span class="fp-exp-dms-badge ' . esc_attr($modifier) . '">' . esc_html($label) . '</span>';
    }
}
