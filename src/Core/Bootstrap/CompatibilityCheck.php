<?php

declare(strict_types=1);

namespace FP_Exp\Core\Bootstrap;

/**
 * Compatibility check for plugin requirements.
 */
final class CompatibilityCheck
{
    /**
     * Validate system requirements.
     */
    public static function validate(): void
    {
        // PHP version check
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            self::showError('FP Experiences richiede PHP >= 8.0. Versione attuale: ' . PHP_VERSION);
            return;
        }

        // WordPress version check
        global $wp_version;
        if (is_string($wp_version) && $wp_version !== '' && version_compare($wp_version, '6.0', '<')) {
            self::showError('FP Experiences richiede WordPress >= 6.0. Versione attuale: ' . $wp_version);
            return;
        }

        // Structure check
        // __DIR__ is src/Core/Bootstrap/, so ../.. goes to plugin root
        $plugin_root = dirname(__DIR__, 3);
        if (!is_dir($plugin_root . '/src')) {
            self::showError('Struttura plugin non valida: cartella \'src\' mancante. Verifica lo ZIP caricato.');
            return;
        }
    }

    /**
     * Show error notice and store error information.
     *
     * @param string $message Error message
     */
    private static function showError(string $message): void
    {
        $payload = [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'php' => PHP_VERSION,
            'wp' => defined('WP_VERSION') ? WP_VERSION : (isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : ''),
            'file' => __FILE__,
            'context' => [],
            'message' => $message,
        ];

        update_option('fp_exp_boot_error', $payload, false);

        // Hook notice for admins only
        add_action('admin_notices', static function () use ($payload): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }
            $summary = isset($payload['message']) ? (string) $payload['message'] : 'FP Experiences: boot error';
            echo '<div class="notice notice-error"><p>' . esc_html($summary) . '</p></div>';
        });
    }
}



