<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\LanguageHelper;
use WP_Screen;
use WP_Term;

use function add_action;
use function add_filter;
use function esc_attr;
use function esc_html;
use function esc_url;
use function function_exists;
use function get_current_screen;
use function is_admin;
use function sprintf;
use function wp_enqueue_style;

final class LanguageAdmin
{
    public function register_hooks(): void
    {
        add_filter('term_name', [$this, 'decorate_term_name'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * @param WP_Term|string $term
     */
    public function decorate_term_name(string $name, $term): string
    {
        if (! is_admin() || ! $term instanceof WP_Term) {
            return $name;
        }

        if ('fp_exp_language' !== $term->taxonomy) {
            return $name;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen instanceof WP_Screen || 'edit-fp_exp_language' !== $screen->id) {
            return $name;
        }

        $badge = LanguageHelper::build_single_badge($term->name);
        $sprite = $badge['sprite'] ?? '';
        $code = $badge['code'] ?? '';

        if ('' === $sprite || '' === $code) {
            return $name;
        }

        $label = $badge['label'] ?? $name;
        $aria_label = $badge['aria_label'] ?? $label;
        $sprite_url = LanguageHelper::get_sprite_url();

        return sprintf(
            '<span class="fp-exp-language-term"><span class="fp-exp-language-term__flag" role="img" aria-label="%s"><svg viewBox="0 0 24 16" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg></span><span class="fp-exp-language-term__code" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
            esc_attr($aria_label),
            esc_url($sprite_url),
            esc_attr($sprite),
            esc_html($code),
            esc_html($label)
        );
    }

    public function enqueue_assets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen instanceof WP_Screen || 'edit-fp_exp_language' !== $screen->id) {
            return;
        }

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );
    }
}
