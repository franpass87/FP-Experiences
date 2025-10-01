<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

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
    public function register_hooks(): void
    {
        // Intentionally left blank; menu registered via AdminMenu.
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('You do not have permission to view FP Experiences logs.', 'fp-experiences'));
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

        if (isset($_POST['fp_exp_clear_logs'])) {
            check_admin_referer('fp_exp_clear_logs', 'fp_exp_clear_logs_nonce');
            Logger::clear();
            echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared successfully.', 'fp-experiences') . '</p></div>';
        }

        $logs = Logger::query([
            'limit' => 200,
            'channel' => $channel_filter,
            'search' => $search_filter,
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('FP Experiences Logs', 'fp-experiences') . '</h1>';

        $this->render_filters($channel_filter, $search_filter);

        echo '<form method="post">';
        wp_nonce_field('fp_exp_clear_logs', 'fp_exp_clear_logs_nonce');
        echo '<input type="hidden" name="fp_exp_clear_logs" value="1" />';
        submit_button(esc_html__('Clear logs', 'fp-experiences'), 'delete');
        echo '</form>';

        if (! $logs) {
            echo '<p>' . esc_html__('No log entries recorded yet.', 'fp-experiences') . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Timestamp', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Channel', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Message', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Context', 'fp-experiences') . '</th>';
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

        echo '<label for="fp-exp-log-channel" class="screen-reader-text">' . esc_html__('Filter by channel', 'fp-experiences') . '</label>';
        echo '<select id="fp-exp-log-channel" name="channel">';
        echo '<option value="">' . esc_html__('All channels', 'fp-experiences') . '</option>';
        foreach ($channels as $available_channel) {
            $label = ucwords(str_replace('_', ' ', $available_channel));
            $selected = $channel === $available_channel ? ' selected' : '';
            echo '<option value="' . esc_attr($available_channel) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<label for="fp-exp-log-search" class="screen-reader-text">' . esc_html__('Search logs', 'fp-experiences') . '</label>';
        echo '<input type="search" id="fp-exp-log-search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search logs', 'fp-experiences') . '" />';

        submit_button(esc_html__('Filter', 'fp-experiences'), '', '', false);
        echo ' <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Export CSV', 'fp-experiences') . '</a>';

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
