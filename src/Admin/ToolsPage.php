<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function add_action;
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
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per eseguire gli strumenti di FP Experiences.', 'fp-experiences'));
        }

        echo '<div class="wrap fp-exp-tools-page">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-tools">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Strumenti', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Strumenti operativi', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Esegui azioni di manutenzione: sincronizzazioni Brevo, ripubblicazione eventi, pulizia cache e diagnostica.', 'fp-experiences') . '</p>';
        echo '</header>';

        settings_errors('fp_exp_settings');
        $this->settings_page->render_tools_panel();
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
