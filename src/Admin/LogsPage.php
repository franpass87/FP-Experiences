<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Admin\Traits\EmptyStateRenderer;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;

use function add_action;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function class_exists;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_bloginfo;
use function nocache_headers;
use function sanitize_key;
use function sanitize_text_field;
use function submit_button;
use function wp_die;
use function wp_json_encode;
use function wp_nonce_field;
use function wp_unslash;

final class LogsPage
{
    use EmptyStateRenderer;

    public function register_hooks(): void
    {
        // Intentionally left blank; menu registered via AdminMenu.
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per visualizzare i log di FP Experiences.', 'fp-experiences'));
        }

        $channel_filter = isset($_GET['channel']) ? sanitize_key((string) wp_unslash($_GET['channel'])) : '';
        $search_filter = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';

        if (isset($_GET['export'])) {
            $entries = Logger::query([
                'channel' => $channel_filter,
                'search' => $search_filter,
                'limit' => 0,
            ]);
            $csv = Logger::export_csv($entries);
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=fp-experiences-logs.csv');
            echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $notice_html = '';

        if (isset($_POST['fp_exp_clear_logs'])) {
            check_admin_referer('fp_exp_clear_logs', 'fp_exp_clear_logs_nonce');
            Logger::clear();
            $notice_html = '<div class="notice notice-success"><p>' . esc_html__('Log cancellati con successo.', 'fp-experiences') . '</p></div>';
        }

        $logs = Logger::query([
            'limit' => 200,
            'channel' => $channel_filter,
            'search' => $search_filter,
        ]);

        echo '<div class="wrap">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-logs">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Logs', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('FP Experiences Logs', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Monitora gli eventi applicativi, esporta diagnosi e ripulisci i registri di sistema.', 'fp-experiences') . '</p>';
        echo '</header>';

        if ($notice_html) {
            echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        $this->render_filters($channel_filter, $search_filter);

        echo '<form method="post">';
        wp_nonce_field('fp_exp_clear_logs', 'fp_exp_clear_logs_nonce');
        echo '<input type="hidden" name="fp_exp_clear_logs" value="1" />';
        submit_button(esc_html__('Cancella log', 'fp-experiences'), 'delete');
        echo '</form>';

        if (! $logs) {
            self::render_empty_state(
                'admin-generic',
                esc_html__('Nessun log registrato', 'fp-experiences'),
                esc_html__('I log di sistema appariranno qui quando verranno registrati eventi importanti o errori.', 'fp-experiences')
            );
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Data/Ora', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Canale', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Messaggio', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Contesto', 'fp-experiences') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($logs as $entry) {
                $context = isset($entry['context']) ? wp_json_encode($entry['context'], JSON_PRETTY_PRINT) : '';
                echo '<tr>';
                echo '<td>' . esc_html((string) ($entry['timestamp'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['channel'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['message'] ?? '')) . '</td>';
                echo '<td><pre style="white-space:pre-wrap;max-width:480px;">' . esc_html((string) $context) . '</pre></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        $diagnostics = $this->collect_diagnostics();
        echo '<h2>' . esc_html__('Diagnostics', 'fp-experiences') . '</h2>';
        echo '<table class="widefat striped">';
        foreach ($diagnostics as $label => $value) {
            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</table>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_filters(string $channel, string $search): void
    {
        $channels = Logger::channels();
        $base_url = add_query_arg('page', 'fp_exp_logs', admin_url('admin.php'));
        $export_url = add_query_arg(
            [
                'page' => 'fp_exp_logs',
                'channel' => $channel,
                's' => $search,
                'export' => '1',
            ],
            admin_url('admin.php')
        );

        echo '<form method="get" action="' . esc_url($base_url) . '" class="fp-exp-log-filter">';
        echo '<input type="hidden" name="page" value="fp_exp_logs" />';

        echo '<label for="fp-exp-log-channel" class="screen-reader-text">' . esc_html__('Filtra per canale', 'fp-experiences') . '</label>';
        echo '<select id="fp-exp-log-channel" name="channel">';
        echo '<option value="">' . esc_html__('Tutti i canali', 'fp-experiences') . '</option>';
        foreach ($channels as $available_channel) {
            $label = ucwords(str_replace('_', ' ', $available_channel));
            $selected = $channel === $available_channel ? ' selected' : '';
            echo '<option value="' . esc_attr($available_channel) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<label for="fp-exp-log-search" class="screen-reader-text">' . esc_html__('Cerca nei log', 'fp-experiences') . '</label>';
        echo '<input type="search" id="fp-exp-log-search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Cerca nei log', 'fp-experiences') . '" />';

        submit_button(esc_html__('Filtra', 'fp-experiences'), '', '', false);
        echo ' <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Esporta CSV', 'fp-experiences') . '</a>';

        echo '</form>';
    }

    /**
     * @return array<string, string>
     */
    private function collect_diagnostics(): array
    {
        global $wpdb;

        $wc_active = class_exists('WooCommerce') ? esc_html__('Yes', 'fp-experiences') : esc_html__('No', 'fp-experiences');

        return [
            esc_html__('WordPress version', 'fp-experiences') => esc_html((string) get_bloginfo('version')),
            esc_html__('PHP version', 'fp-experiences') => esc_html(PHP_VERSION),
            esc_html__('Database version', 'fp-experiences') => esc_html((string) $wpdb->db_version()),
            esc_html__('WooCommerce active', 'fp-experiences') => $wc_active,
        ];
    }
}
