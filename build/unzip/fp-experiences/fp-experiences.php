<?php
/**
 * Plugin Name: FP Experiences
 * Description: Booking esperienze stile GetYourGuide — shortcode/Elementor only, carrello/checkout isolati, Brevo opzionale, Google Calendar opzionale, tracking marketing opzionale.
 * Version: 0.3.4
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-experiences
 * Domain Path: /languages
 * License: GPLv2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
    define('FP_EXP_VERSION', '0.3.4');
}

$autoload_guard = (function () {
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

	if (version_compare(PHP_VERSION, '8.0', '<')) {
		$store_and_hook_notice('FP Experiences richiede PHP >= 8.0. Versione attuale: ' . PHP_VERSION);
		return false;
	}

	global $wp_version;
	if (is_string($wp_version) && $wp_version !== '' && version_compare($wp_version, '6.0', '<')) {
		$store_and_hook_notice('FP Experiences richiede WordPress >= 6.0. Versione attuale: ' . $wp_version);
		return false;
	}

	if (! is_dir(__DIR__ . '/src')) {
		$store_and_hook_notice("Struttura plugin non valida: cartella 'src' mancante. Verifica lo ZIP caricato.");
		return false;
	}

	return true;
})();

$autoload = __DIR__ . '/vendor/autoload.php';
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

register_activation_hook(__FILE__, [Activation::class, 'activate']);
register_deactivation_hook(__FILE__, [Activation::class, 'deactivate']);

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
})();
