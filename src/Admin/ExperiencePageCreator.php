<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use function absint;
use function add_action;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function delete_transient;
use function get_posts;
use function get_transient;
use function is_array;
use function is_wp_error;
use function sanitize_text_field;
use function set_transient;
use function submit_button;
use function wp_insert_post;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_die;

final class ExperiencePageCreator
{
    private const NOTICE_KEY = 'fp_exp_page_creator_notice';

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'maybe_handle_submit']);
    }

    public function maybe_handle_submit(): void
    {
        if (! current_user_can('fp_exp_manage')) {
            return;
        }

        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (! isset($_POST['fp_exp_page_creator'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        check_admin_referer('fp_exp_create_page', 'fp_exp_create_page_nonce');

        $experience_id = isset($_POST['fp_exp_experience']) ? absint($_POST['fp_exp_experience']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $title = isset($_POST['fp_exp_page_title']) ? sanitize_text_field((string) wp_unslash($_POST['fp_exp_page_title'])) : '';

        if ($experience_id <= 0 || '' === $title) {
            set_transient(self::NOTICE_KEY, [
                'message' => esc_html__('Seleziona un\'esperienza e inserisci un titolo valido.', 'fp-experiences'),
                'type' => 'error',
            ], 30);
            wp_safe_redirect(add_query_arg('page', 'fp_exp_create_page', admin_url('admin.php')));
            exit;
        }

        $content = sprintf('[fp_exp_page id="%d"]', $experience_id);
        $result = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);

        if ($result && ! is_wp_error($result)) {
            set_transient(self::NOTICE_KEY, [
                'message' => esc_html__('Pagina esperienza creata con successo.', 'fp-experiences'),
                'type' => 'success',
            ], 30);
        } else {
            set_transient(self::NOTICE_KEY, [
                'message' => esc_html__('Impossibile creare la pagina, riprova più tardi.', 'fp-experiences'),
                'type' => 'error',
            ], 30);
        }

        wp_safe_redirect(add_query_arg('page', 'fp_exp_create_page', admin_url('admin.php')));
        exit;
    }

    public function render_page(): void
    {
        if (! current_user_can('fp_exp_manage')) {
            wp_die(esc_html__('You do not have permission to generate experience pages.', 'fp-experiences'));
        }

        $notice = get_transient(self::NOTICE_KEY);
        if (is_array($notice) && ! empty($notice['message'])) {
            $class = 'notice notice-' . esc_attr($notice['type'] ?? 'success');
            echo '<div class="' . $class . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
            delete_transient(self::NOTICE_KEY);
        }

        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 200,
            'post_status' => ['publish', 'draft'],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<div class="wrap fp-exp-page-creator">';
        echo '<h1>' . esc_html__('Crea pagina esperienza', 'fp-experiences') . '</h1>';
        echo '<p>' . esc_html__('Genera una pagina WordPress con shortcode preconfigurato per un\'esperienza.', 'fp-experiences') . '</p>';

        echo '<form method="post">';
        wp_nonce_field('fp_exp_create_page', 'fp_exp_create_page_nonce');
        echo '<input type="hidden" name="fp_exp_page_creator" value="1" />';

        echo '<p>';
        echo '<label for="fp-exp-page-title">' . esc_html__('Titolo pagina', 'fp-experiences') . '</label><br />';
        echo '<input type="text" id="fp-exp-page-title" name="fp_exp_page_title" class="regular-text" required />';
        echo '</p>';

        echo '<p>';
        echo '<label for="fp-exp-experience-select">' . esc_html__('Esperienza da collegare', 'fp-experiences') . '</label><br />';
        echo '<select id="fp-exp-experience-select" name="fp_exp_experience" class="regular-text" required>';
        echo '<option value="">' . esc_html__('Seleziona un\'esperienza', 'fp-experiences') . '</option>';
        foreach ($experiences as $experience) {
            echo '<option value="' . esc_attr((string) $experience->ID) . '">' . esc_html($experience->post_title) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        submit_button(esc_html__('Crea pagina', 'fp-experiences'));
        echo '</form>';
        echo '</div>';
    }
}
