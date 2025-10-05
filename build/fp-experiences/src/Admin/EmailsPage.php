<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function admin_url;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function settings_errors;
use function settings_fields;
use function do_settings_sections;
use function submit_button;
use function wp_die;

final class EmailsPage
{
    private SettingsPage $settings_page;

    public function __construct(SettingsPage $settings_page)
    {
        $this->settings_page = $settings_page;
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('You do not have permission to manage email settings.', 'fp-experiences'));
        }

        echo '<div class="wrap fp-exp-emails-page">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>'; // layout wrapper
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-settings">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Email', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Gestione email', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Configura mittenti, destinatari, branding e integrazioni per le comunicazioni automatiche.', 'fp-experiences') . '</p>';
        echo '</header>';

        settings_errors('fp_exp_settings');

        echo '<div class="fp-exp-settings__panel">';
        echo '<h2>' . esc_html__('Mittenti e branding', 'fp-experiences') . '</h2>';
        echo '<form action="options.php" method="post" class="fp-exp-settings__form">';
        settings_fields('fp_exp_settings_emails');
        do_settings_sections('fp_exp_settings_emails');
        submit_button();
        echo '</form>';
        echo '</div>';

        echo '<div class="fp-exp-settings__panel">';
        echo '<h2>' . esc_html__('Integrazione Brevo', 'fp-experiences') . '</h2>';
        echo '<form action="options.php" method="post" class="fp-exp-settings__form">';
        settings_fields('fp_exp_settings_brevo');
        do_settings_sections('fp_exp_settings_brevo');
        submit_button();
        echo '</form>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
