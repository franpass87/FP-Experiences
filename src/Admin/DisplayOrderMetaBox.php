<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Core\Hook\HookableInterface;
use WP_Post;

use function add_action;
use function add_meta_box;
use function absint;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_current_screen;
use function sanitize_text_field;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;
use function wp_update_post;

/**
 * Gestisce il metabox per l'ordine di visualizzazione delle esperienze.
 */
final class DisplayOrderMetaBox implements HookableInterface
{
    /**
     * Registra gli hooks WordPress.
     */
    public function register_hooks(): void
    {
        add_action('add_meta_boxes_fp_experience', [$this, 'add_meta_box']);
        add_action('save_post_fp_experience', [$this, 'save_meta_box'], 10, 3);
    }

    /**
     * Aggiunge il metabox nella sidebar.
     */
    public function add_meta_box(): void
    {
        add_meta_box(
            'fp-exp-display-order',
            esc_html__('Ordine di visualizzazione', 'fp-experiences'),
            [$this, 'render_meta_box'],
            'fp_experience',
            'side',
            'default'
        );
    }

    /**
     * Renderizza il contenuto del metabox.
     */
    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('fp_exp_display_order_nonce', 'fp_exp_display_order_nonce');

        $menu_order = absint($post->menu_order);
        ?>
        <div class="fp-exp-display-order-field">
            <p>
                <label for="fp_exp_menu_order">
                    <?php echo esc_html__('Numero ordine:', 'fp-experiences'); ?>
                </label>
            </p>
            <p>
                <input
                    type="number"
                    id="fp_exp_menu_order"
                    name="fp_exp_menu_order"
                    value="<?php echo esc_attr((string) $menu_order); ?>"
                    min="0"
                    step="1"
                    class="widefat"
                />
            </p>
            <p class="description">
                <?php echo esc_html__('Imposta un numero per ordinare le esperienze nelle liste. I numeri più bassi vengono visualizzati per primi.', 'fp-experiences'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Salva il valore del menu_order.
     */
    public function save_meta_box(int $post_id, WP_Post $post, bool $update): void
    {
        unset($post, $update);

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (! isset($_POST['fp_exp_display_order_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST['fp_exp_display_order_nonce']));
        if (! wp_verify_nonce($nonce, 'fp_exp_display_order_nonce')) {
            return;
        }

        $menu_order = 0;
        if (isset($_POST['fp_exp_menu_order'])) {
            $menu_order = absint(wp_unslash((string) $_POST['fp_exp_menu_order']));
        }

        // Aggiorna il campo menu_order del post
        wp_update_post([
            'ID' => $post_id,
            'menu_order' => $menu_order,
        ]);
    }
}
