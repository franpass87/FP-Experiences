<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function esc_html;
use function esc_html__;
use function wp_die;

final class HelpPage
{
    public function render_page(): void
    {
        if (! Helpers::can_access_guides()) {
            wp_die(esc_html__('You do not have permission to access the FP Experiences guide.', 'fp-experiences'));
        }

        echo '<div class="wrap fp-exp-help">';
        echo '<h1>' . esc_html__('Guida & Shortcode', 'fp-experiences') . '</h1>';
        echo '<p>' . esc_html__('Consulta i componenti disponibili e copia rapidamente gli shortcode nelle pagine del sito.', 'fp-experiences') . '</p>';

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
    }
}
