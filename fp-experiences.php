<?php
/**
 * Plugin Name: FP Experiences
 * Description: Booking esperienze stile GetYourGuide — shortcode/Elementor only, carrello/checkout isolati, Brevo opzionale, Google Calendar opzionale, tracking marketing opzionale.
 * Version: 1.1.5
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-experiences
 * Domain Path: /languages
 * License: GPLv2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: franpass87/FP-Experiences
 * Primary Branch: main
 * Release Asset: true
 */

declare(strict_types=1);

namespace FP_Exp;

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('FP_EXP_PLUGIN_FILE')) {
    define('FP_EXP_PLUGIN_FILE', __FILE__);
}

if (! defined('FP_EXP_PLUGIN_DIR')) {
    define('FP_EXP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('FP_EXP_PLUGIN_URL')) {
    define('FP_EXP_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (! defined('FP_EXP_VERSION')) {
    define('FP_EXP_VERSION', '1.1.5');
}

// Early bootstrap guard: detect common issues and surface an admin notice instead of a fatal.
// Stores last boot error in option 'fp_exp_boot_error' and shows an admin notice in wp‑admin.
(function () {
	// Helper to persist and render a meaningful admin notice.
	$store_and_hook_notice = function (string $message, array $context = []): void {
		$payload = [
			'timestamp' => gmdate('Y-m-d H:i:s'),
			'php' => PHP_VERSION,
			'wp' => defined('WP_VERSION') ? WP_VERSION : (isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : ''),
			'file' => __FILE__,
			'context' => $context,
			'message' => $message,
		];

		update_option('fp_exp_boot_error', $payload, false);

		// Hook notice for admins only.
		add_action('admin_notices', static function () use ($payload): void {
			if (! current_user_can('activate_plugins')) {
				return;
			}
			$summary = isset($payload['message']) ? (string) $payload['message'] : 'FP Experiences: boot error';
			echo '<div class="notice notice-error"><p>' . esc_html($summary) . '</p></div>';
		});
	};

	// 1) PHP version check (plugin requires >= 8.0 per composer.json; recommend >= 8.1).
	if (version_compare(PHP_VERSION, '8.0', '<')) {
		$store_and_hook_notice('FP Experiences richiede PHP >= 8.0. Versione attuale: ' . PHP_VERSION);
		return;
	}

	// 2) WordPress version check (soft guard to help on early fatals in very old sites).
	global $wp_version;
	if (is_string($wp_version) && $wp_version !== '' && version_compare($wp_version, '6.0', '<')) {
		$store_and_hook_notice('FP Experiences richiede WordPress >= 6.0. Versione attuale: ' . $wp_version);
		return;
	}

	// 3) Basic structure sanity checks before loading anything else.
	if (! is_dir(__DIR__ . '/src')) {
		$store_and_hook_notice('Struttura plugin non valida: cartella \'src\' mancante. Verifica lo ZIP caricato.');
		return;
	}
})();

$autoload = __DIR__ . '/vendor/autoload.php';

if (is_readable($autoload)) {
    require $autoload;
} else {
    // Simple PSR-4 autoloader for the plugin when Composer autoload is unavailable.
    spl_autoload_register(function (string $class): void {
        if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
            return;
        }

        $relative = substr($class, strlen(__NAMESPACE__ . '\\'));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $path = __DIR__ . '/src/' . $relative . '.php';

        if (is_readable($path)) {
            require_once $path;
        }
    });
}

use FP_Exp\Activation;
use FP_Exp\Plugin;

// Hard fallback: ensure critical classes exist even if autoloading fails in some environments.
if (! class_exists(Activation::class) && is_readable(__DIR__ . '/src/Activation.php')) {
    require_once __DIR__ . '/src/Activation.php';
}

register_activation_hook(__FILE__, [Activation::class, 'activate']);
register_deactivation_hook(__FILE__, [Activation::class, 'deactivate']);

// Final guard: if the main Plugin class is missing or boot throws, show a notice instead of a fatal.
(function () {
	$store_and_hook_notice = function (string $message, array $context = []): void {
		$payload = [
			'timestamp' => gmdate('Y-m-d H:i:s'),
			'php' => PHP_VERSION,
			'wp' => defined('WP_VERSION') ? WP_VERSION : (isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : ''),
			'file' => __FILE__,
			'context' => $context,
			'message' => $message,
		];
		update_option('fp_exp_boot_error', $payload, false);
		add_action('admin_notices', static function () use ($payload): void {
			if (! current_user_can('activate_plugins')) {
				return;
			}
			$summary = isset($payload['message']) ? (string) $payload['message'] : 'FP Experiences: boot error';
			echo '<div class="notice notice-error"><p>' . esc_html($summary) . '</p></div>';
		});
	};

	if (! class_exists(Plugin::class)) {
		$store_and_hook_notice('Impossibile avviare FP Experiences: classe principale assente. Verifica autoload/vendor e struttura dello ZIP.', [
			'class' => Plugin::class,
		]);
		return;
	}

	$boot = static function () use ($store_and_hook_notice): void {
		try {
			Plugin::instance()->boot();
		} catch (\Throwable $e) {
			$message = 'Errore in avvio FP Experiences: ' . ($e->getMessage() ?: get_class($e));
			$context = [
				'exception' => get_class($e),
				'file' => method_exists($e, 'getFile') ? $e->getFile() : '',
				'line' => method_exists($e, 'getLine') ? (string) $e->getLine() : '',
			];
			if (! empty($context['file']) && ! empty($context['line'])) {
				$message .= ' in ' . $context['file'] . ':' . $context['line'];
			}
			$store_and_hook_notice($message, $context);
		}
	};

	if (\did_action('wp_loaded')) {
		$boot();
	} else {
		\add_action('wp_loaded', $boot, 0);
	}
})();
