<?php
/**
 * Plugin Name: FP Experiences
 * Description: Booking esperienze stile GetYourGuide — shortcode/Elementor only, carrello/checkout isolati, Brevo opzionale, Google Calendar opzionale, tracking marketing opzionale.
 * Version: 1.2.0
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
    define('FP_EXP_VERSION', '1.2.0');
}

// Compatibility check using new Bootstrap system
require_once __DIR__ . '/src/Core/Bootstrap/CompatibilityCheck.php';
\FP_Exp\Core\Bootstrap\CompatibilityCheck::validate();

// Ensure HookableInterface is loaded early (critical for all service classes)
$hookable_interface = __DIR__ . '/src/Core/Hook/HookableInterface.php';
if (is_readable($hookable_interface)) {
    require_once $hookable_interface;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (is_readable($autoload)) {
    require $autoload;
} else {
    // Simple PSR-4 autoloader for the plugin when Composer autoload is unavailable.
    spl_autoload_register(function (string $class): void {
        // Handle both classes and interfaces
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

// Bootstrap plugin using new architecture
require_once __DIR__ . '/src/Core/Bootstrap/Bootstrap.php';
\FP_Exp\Core\Bootstrap\Bootstrap::init();

// Register activation/deactivation hooks (backward compatible)
register_activation_hook(__FILE__, [\FP_Exp\Core\Bootstrap\Bootstrap::class, 'activate']);
register_deactivation_hook(__FILE__, [\FP_Exp\Core\Bootstrap\Bootstrap::class, 'deactivate']);

// Legacy boot: Keep existing Plugin class boot for backward compatibility
// This ensures all existing hooks and functionality continue to work
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
			// Kernel architecture handles all boot logic now
			// Legacy Plugin boot is handled by LegacyServiceProvider
			// No need to manually boot Plugin here - Kernel does it automatically
			$kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
			if ($kernel === null) {
				$store_and_hook_notice('FP Experiences: Kernel not initialized. Plugin may not function correctly.');
				return;
			}
			// Kernel boot is already scheduled in Bootstrap::init()
			// Just verify it's ready
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
