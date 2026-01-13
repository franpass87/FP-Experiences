<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WP_Error;
use WP_Post;
use WP_Theme;

use function __;
use function absint;
use function add_action;
use function add_filter;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function delete_transient;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_transient;
use function is_array;
use function is_wp_error;
use function sanitize_text_field;
use function set_transient;
use function submit_button;
use function update_post_meta;
use function wp_get_theme;
use function wp_insert_post;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_strip_all_tags;
use function wp_unslash;
use function wp_die;

final class ExperiencePageCreator implements HookableInterface
{
    private const NOTICE_KEY = 'fp_exp_page_creator_notice';

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'maybe_handle_submit']);
        add_action('save_post_fp_experience', [$this, 'maybe_generate_page'], 20, 3);
        add_filter('fp_exp_tools_resync_pages', [$this, 'handle_tools_resync']);
    }

    public function maybe_handle_submit(): void
    {
        if (! Helpers::can_manage_fp()) {
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
        $template = $this->locate_full_width_template();

        $page_args = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'meta_input' => [
                '_fp_experience_id' => $experience_id,
            ],
        ];

        if ($template) {
            $page_args['meta_input']['_wp_page_template'] = $template;
        }

        $result = wp_insert_post($page_args);

        if ($result && ! is_wp_error($result)) {
            // Verify this page_id isn't already used by another experience before saving
            $page_id = (int) $result;
            $existing_uses = get_posts([
                'post_type' => 'fp_experience',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'post__not_in' => [$experience_id],
                'meta_query' => [
                    [
                        'key' => '_fp_exp_page_id',
                        'value' => $page_id,
                        'compare' => '=',
                    ],
                ],
            ]);
            
            if (empty($existing_uses)) {
                update_post_meta($experience_id, '_fp_exp_page_id', $page_id);
            } else {
                // Another experience already uses this page_id, don't save it
                Helpers::log_debug('pages', 'Prevented duplicate page_id assignment', [
                    'experience_id' => $experience_id,
                    'page_id' => $page_id,
                    'already_used_by' => $existing_uses,
                ]);
            }

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
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per generare pagine esperienza.', 'fp-experiences'));
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
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Crea pagina esperienza', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Crea pagina esperienza', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Genera una pagina WordPress con shortcode preconfigurato per un\'esperienza.', 'fp-experiences') . '</p>';
        echo '</header>';

        echo '<form method="post" class="fp-exp-settings__form">';
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
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function maybe_generate_page(int $post_id, WP_Post $post, bool $update): void
    {
        unset($update);

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if ('publish' !== $post->post_status) {
            return;
        }

        $current_page_id = absint((string) get_post_meta($post_id, '_fp_exp_page_id', true));
        if ($current_page_id) {
            $existing = get_post($current_page_id);
            if ($existing instanceof WP_Post && 'trash' !== $existing->post_status) {
                return;
            }
        }

        $this->create_linked_page($post_id, $post);
    }

    /**
     * @param array<string, int> $summary
     *
     * @return array<string, int>
     */
    public function handle_tools_resync(array $summary = []): array
    {
        $result = $this->resync_missing_pages();

        $summary['checked'] = ($summary['checked'] ?? 0) + $result['checked'];
        $summary['created'] = ($summary['created'] ?? 0) + $result['created'];

        return $summary;
    }

    /**
     * @return array{checked: int, created: int}
     */
    private function resync_missing_pages(): array
    {
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        $checked = 0;
        $created = 0;

        foreach ($experiences as $experience_id) {
            $experience_id = (int) $experience_id;
            if ($experience_id <= 0) {
                continue;
            }

            $checked++;
            $page_id = absint((string) get_post_meta($experience_id, '_fp_exp_page_id', true));
            if ($page_id) {
                $page = get_post($page_id);
                if ($page instanceof WP_Post && 'trash' !== $page->post_status) {
                    continue;
                }
            }

            if ($this->create_linked_page($experience_id)) {
                $created++;
            }
        }

        if ($checked > 0) {
            Logger::log('pages', 'Resynchronised experience pages', [
                'checked' => $checked,
                'created' => $created,
            ]);
        }

        return [
            'checked' => $checked,
            'created' => $created,
        ];
    }

    private function create_linked_page(int $experience_id, ?WP_Post $experience = null): int
    {
        $experience = $experience instanceof WP_Post ? $experience : get_post($experience_id);
        if (! $experience instanceof WP_Post || 'fp_experience' !== $experience->post_type) {
            return 0;
        }

        $title = wp_strip_all_tags($experience->post_title ?: '');
        if ('' === $title) {
            $title = sprintf(
                /* translators: %d: experience ID. */
                __('Esperienza %d', 'fp-experiences'),
                $experience_id
            );
        }

        $template = $this->locate_full_width_template();

        $page_args = [
            'post_title' => $title,
            'post_content' => sprintf('[fp_exp_page id="%d"]', $experience_id),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => (int) $experience->post_author,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'meta_input' => [
                '_fp_experience_id' => $experience_id,
            ],
        ];

        if ($template) {
            $page_args['meta_input']['_wp_page_template'] = $template;
        }

        $page_id = wp_insert_post($page_args, true);
        if (! $page_id || is_wp_error($page_id)) {
            $error_message = $page_id instanceof WP_Error ? $page_id->get_error_message() : 'unknown';

            Logger::log('pages', 'Failed to auto-create experience page', [
                'experience_id' => $experience_id,
                'error' => $error_message,
            ]);

            return 0;
        }

        // Verify this page_id isn't already used by another experience before saving
        $existing_uses = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post__not_in' => [$experience_id],
            'meta_query' => [
                [
                    'key' => '_fp_exp_page_id',
                    'value' => (int) $page_id,
                    'compare' => '=',
                ],
            ],
        ]);
        
        if (! empty($existing_uses)) {
            // Another experience already uses this page_id, don't save it
            Helpers::log_debug('pages', 'Prevented duplicate page_id assignment in auto-create', [
                'experience_id' => $experience_id,
                'page_id' => (int) $page_id,
                'already_used_by' => $existing_uses,
            ]);
            
            return 0;
        }
        
        update_post_meta($experience_id, '_fp_exp_page_id', (int) $page_id);

        Logger::log('pages', 'Auto-created experience page', [
            'experience_id' => $experience_id,
            'page_id' => (int) $page_id,
        ]);

        return (int) $page_id;
    }

    private function locate_full_width_template(): string
    {
        $theme = wp_get_theme();
        if (! $theme->exists()) {
            return '';
        }

        $template = $this->find_full_width_template($theme);
        if ($template) {
            return $template;
        }

        $parent = $theme->parent();
        if ($parent instanceof WP_Theme) {
            return $this->find_full_width_template($parent);
        }

        return '';
    }

    private function find_full_width_template(WP_Theme $theme): string
    {
        $templates = $theme->get_page_templates(null, 'page');
        foreach ($templates as $file => $label) {
            $haystack = strtolower($file . ' ' . $label);
            if (
                str_contains($haystack, 'full-width') ||
                str_contains($haystack, 'full width') ||
                str_contains($haystack, 'fullwidth')
            ) {
                return $file;
            }
        }

        return '';
    }
}
