<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use function add_action;
use function current_user_can;
use function esc_html__;
use function get_current_screen;
use function settings_errors;
use function wp_die;

final class ToolsPage
{
    private SettingsPage $settings_page;

    public function __construct(SettingsPage $settings_page)
    {
        $this->settings_page = $settings_page;
    }

    public function register_hooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if (! $screen || 'fp-exp-dashboard_page_fp_exp_tools' !== $screen->id) {
            return;
        }

        $this->settings_page->enqueue_tools_assets();
    }

    public function render_page(): void
    {
        if (! current_user_can('fp_exp_manage')) {
            wp_die(esc_html__('You do not have permission to run FP Experiences tools.', 'fp-experiences'));
        }

        echo '<div class="wrap fp-exp-tools-page">';
        echo '<h1>' . esc_html__('Strumenti operativi', 'fp-experiences') . '</h1>';
        echo '<p>' . esc_html__('Esegui azioni di manutenzione: sincronizzazioni Brevo, ripubblicazione eventi, pulizia cache e diagnostica.', 'fp-experiences') . '</p>';

        settings_errors('fp_exp_settings');
        $this->settings_page->render_tools_panel();
        echo '</div>';
    }
}
