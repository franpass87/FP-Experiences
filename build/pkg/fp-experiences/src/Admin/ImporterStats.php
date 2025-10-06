<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function add_action;
use function admin_url;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_option;
use function time;
use function update_option;

/**
 * Tracks and displays importer statistics
 */
final class ImporterStats
{
    private const OPTION_KEY = 'fp_exp_importer_stats';
    private const MAX_HISTORY = 10;

    /**
     * Record an import operation
     *
     * @param array<string, mixed> $result Import result data.
     */
    public static function record_import(array $result): void
    {
        $stats = self::get_stats();

        $import_record = [
            'timestamp' => time(),
            'imported' => $result['imported'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
            'total_rows' => ($result['imported'] ?? 0) + ($result['skipped'] ?? 0),
            'has_errors' => ! empty($result['errors']),
            'created_ids' => $result['created_ids'] ?? [],
        ];

        // Add to history
        array_unshift($stats['history'], $import_record);

        // Keep only last MAX_HISTORY imports
        $stats['history'] = array_slice($stats['history'], 0, self::MAX_HISTORY);

        // Update totals
        $stats['total_imports'] = ($stats['total_imports'] ?? 0) + 1;
        $stats['total_experiences'] = ($stats['total_experiences'] ?? 0) + $import_record['imported'];
        $stats['last_import'] = time();

        update_option(self::OPTION_KEY, $stats);
    }

    /**
     * Get all stats
     *
     * @return array<string, mixed>
     */
    public static function get_stats(): array
    {
        $default = [
            'total_imports' => 0,
            'total_experiences' => 0,
            'last_import' => 0,
            'history' => [],
        ];

        $stats = get_option(self::OPTION_KEY, $default);

        return is_array($stats) ? array_merge($default, $stats) : $default;
    }

    /**
     * Render stats widget for dashboard
     */
    public static function render_dashboard_widget(): void
    {
        if (! Helpers::can_manage_fp()) {
            return;
        }

        $stats = self::get_stats();

        echo '<div class="fp-exp-importer-stats" style="padding: 12px;">';

        if ($stats['total_imports'] === 0) {
            echo '<p style="margin: 0; color: #646970;">';
            echo esc_html__('Nessun import effettuato ancora.', 'fp-experiences');
            echo '</p>';
            echo '<p style="margin: 12px 0 0;">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_importer')) . '" class="button button-secondary">';
            echo esc_html__('Vai all\'Importer', 'fp-experiences');
            echo '</a>';
            echo '</p>';
            return;
        }

        // Summary stats
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 16px; margin-bottom: 16px;">';

        echo '<div>';
        echo '<div style="font-size: 24px; font-weight: 700; color: #2271b1;">' . esc_html((string) $stats['total_imports']) . '</div>';
        echo '<div style="font-size: 12px; color: #646970;">' . esc_html__('Import Totali', 'fp-experiences') . '</div>';
        echo '</div>';

        echo '<div>';
        echo '<div style="font-size: 24px; font-weight: 700; color: #00a32a;">' . esc_html((string) $stats['total_experiences']) . '</div>';
        echo '<div style="font-size: 12px; color: #646970;">' . esc_html__('Esperienze Importate', 'fp-experiences') . '</div>';
        echo '</div>';

        echo '</div>';

        // Recent history
        if (! empty($stats['history'])) {
            echo '<h4 style="margin: 16px 0 8px; font-size: 13px; color: #1d2327;">' . esc_html__('Ultimi Import', 'fp-experiences') . '</h4>';
            echo '<ul style="margin: 0; padding: 0; list-style: none;">';

            foreach (array_slice($stats['history'], 0, 5) as $record) {
                $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $record['timestamp']);
                $icon = $record['has_errors'] ? '⚠️' : '✅';

                echo '<li style="padding: 6px 0; border-bottom: 1px solid #f0f0f1; font-size: 12px;">';
                echo '<span style="margin-right: 6px;">' . $icon . '</span>';
                echo '<strong>' . esc_html((string) $record['imported']) . '</strong> ';
                echo esc_html__('importate', 'fp-experiences');

                if ($record['skipped'] > 0) {
                    echo ', <span style="color: #d63638;">' . esc_html((string) $record['skipped']) . ' ' . esc_html__('errori', 'fp-experiences') . '</span>';
                }

                echo '<br>';
                echo '<span style="color: #646970;">' . esc_html($date) . '</span>';
                echo '</li>';
            }

            echo '</ul>';
        }

        echo '<p style="margin: 12px 0 0;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_importer')) . '" class="button button-secondary">';
        echo esc_html__('Nuovo Import', 'fp-experiences');
        echo '</a>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Register dashboard widget
     */
    public static function register_dashboard_widget(): void
    {
        if (! Helpers::can_manage_fp()) {
            return;
        }

        wp_add_dashboard_widget(
            'fp_exp_importer_stats',
            __('📊 Statistiche Importer Esperienze', 'fp-experiences'),
            [self::class, 'render_dashboard_widget']
        );
    }

    /**
     * Register hooks
     */
    public static function register_hooks(): void
    {
        add_action('wp_dashboard_setup', [self::class, 'register_dashboard_widget']);
    }
}
