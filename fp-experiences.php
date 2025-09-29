<?php
/**
 * Plugin Name: FP Experiences
 * Description: Booking esperienze stile GetYourGuide — shortcode/Elementor only, carrello/checkout isolati, Brevo opzionale, Google Calendar opzionale, tracking marketing opzionale.
 * Version: 0.1.0
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
    define('FP_EXP_VERSION', '0.1.0');
}

// Simple PSR-4 autoloader for the plugin.
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

use FP_Exp\Activation;
use FP_Exp\Plugin;

register_activation_hook(__FILE__, [Activation::class, 'activate']);
register_deactivation_hook(__FILE__, [Activation::class, 'deactivate']);

Plugin::instance()->boot();
