<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function add_action;
use function admin_url;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function wp_die;

final class HelpPage
{
    public function register_hooks(): void
    {
        // Asset admin base gestiti da AdminMenu.
    }

    public function render_page(): void
    {
        if (! Helpers::can_access_guides()) {
            wp_die(esc_html__('Non hai i permessi per accedere alla guida di FP Experiences.', 'fp-experiences'));
        }

        echo '<div class="wrap fp-exp-help fp-exp-admin-page">';
        echo '<h1 class="screen-reader-text">' . esc_html__('Guida & Shortcode', 'fp-experiences') . '</h1>';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout">';
        echo '<div class="fpexp-page-header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Guida', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<div class="fpexp-page-header-content">';
        echo '<h2 class="fpexp-page-header-title" aria-hidden="true">' . esc_html__('Guida & Shortcode', 'fp-experiences') . '</h2>';
        echo '<p class="fpexp-page-header-desc">' . esc_html__('Consulta i componenti disponibili e copia rapidamente gli shortcode nelle pagine del sito.', 'fp-experiences') . '</p>';
        echo '</div>';
        echo '<span class="fpexp-page-header-badge">v' . esc_html( defined( 'FP_EXP_VERSION' ) ? FP_EXP_VERSION : '0' ) . '</span>';
        echo '</div>';

        echo '<section class="fp-exp-help__section">';
        echo '<h2>' . esc_html__('Shortcode disponibili', 'fp-experiences') . '</h2>';
        echo '<ul>';
        echo '<li><code>[fp_exp_page id="123"]</code> — ' . esc_html__('Pagina esperienza completa con calendario e CTA.', 'fp-experiences') . '</li>';
        echo '<li><code>[fp_exp_widget id="123"]</code> — ' . esc_html__('Widget prenotazione compatto per pagine di vendita.', 'fp-experiences') . '</li>';
        echo '<li><code>[fp_exp_list theme="" columns="3"]</code> — ' . esc_html__('Vetrina delle esperienze con filtri e ricerca.', 'fp-experiences') . '</li>';
        echo '<li><code>[fp_exp_meeting_points id="123"]</code> — ' . esc_html__('Mappa dei meeting point collegati a una esperienza.', 'fp-experiences') . '</li>';
        echo '</ul>';
        echo '</section>';

        echo '<section class="fp-exp-help__section">';
        echo '<h2>' . esc_html__('Risorse utili', 'fp-experiences') . '</h2>';
        echo '<p>' . esc_html__('Tutte le pagine dell\'amministrazione FP Experiences rispettano i ruoli guida, operatore e manager. Per supporto aggiuntivo consulta la documentazione interna.', 'fp-experiences') . '</p>';
        echo '</section>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
