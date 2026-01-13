<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Services\Cache\CacheInterface;
use FP_Exp\Services\Cache\TransientCache;
use FP_Exp\Services\Database\Database;
use FP_Exp\Services\Database\DatabaseInterface;
use FP_Exp\Services\Logger\Logger;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Options\Options;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Services\Sanitization\Sanitizer;
use FP_Exp\Services\Sanitization\SanitizerInterface;
use FP_Exp\Services\Validation\Validator;
use FP_Exp\Services\Validation\ValidatorInterface;
use FP_Exp\Services\HTTP\HttpClientInterface;
use FP_Exp\Services\HTTP\WordPressHttpClient;
use FP_Exp\Services\Security\NonceManager;
use FP_Exp\Services\Security\CapabilityChecker;
use FP_Exp\Services\Security\InputSanitizer;
use FP_Exp\Core\Bootstrap\LifecycleManager;
use FP_Exp\Services\Logger\MigrationLogger;
use FP_Exp\Booking\Cart;
use FP_Exp\Utils\DatabaseTables;
use FP_Exp\Compatibility\Multilanguage;

/**
 * Core service provider - registers fundamental services.
 */
final class CoreServiceProvider extends AbstractServiceProvider
{
    /**
     * Register core services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Logger
        $container->singleton(LoggerInterface::class, Logger::class);

        // Cache
        $container->singleton(CacheInterface::class, static function (ContainerInterface $container): TransientCache {
            return new TransientCache('fp_exp_');
        });

        // Options
        $container->singleton(OptionsInterface::class, Options::class);

        // Database
        $container->singleton(DatabaseInterface::class, Database::class);

        // Validation
        $container->singleton(ValidatorInterface::class, Validator::class);

        // Sanitization
        $container->singleton(SanitizerInterface::class, Sanitizer::class);

        // HTTP Client
        $container->singleton(HttpClientInterface::class, WordPressHttpClient::class);

        // Security Services
        $container->singleton(NonceManager::class, NonceManager::class);
        $container->singleton(CapabilityChecker::class, CapabilityChecker::class);
        $container->singleton(InputSanitizer::class, InputSanitizer::class);

        // Lifecycle Manager
        $container->singleton(LifecycleManager::class, static function (ContainerInterface $container): LifecycleManager {
            $logger = $container->has(LoggerInterface::class) ? $container->make(LoggerInterface::class) : null;
            return new LifecycleManager($logger);
        });

        // Migration Logger (for tracking refactoring progress)
        $container->singleton(MigrationLogger::class, static function (ContainerInterface $container): MigrationLogger {
            $logger = $container->make(LoggerInterface::class);
            return new MigrationLogger($logger);
        });

        // Multilanguage Compatibility Layer (WPML, Polylang, FP-Multilanguage, etc.)
        $container->singleton(Multilanguage::class, Multilanguage::class);

        // Cart is now registered in BookingServiceProvider
        // Keeping this for backward compatibility during migration
        // Will be removed once all code uses BookingServiceProvider
    }

    /**
     * Boot core services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Register database tables early on plugins_loaded
        add_action('plugins_loaded', [DatabaseTables::class, 'register'], 5);
        
        // Load textdomain on init (WP 6.7+ timing requirement)
        // For WPML compatibility, we also load the short locale file (e.g., fp-experiences-en.mo)
        add_action('init', static function (): void {
            $domain = 'fp-experiences';
            $plugin_dir = dirname(plugin_basename(FP_EXP_PLUGIN_FILE ?? __FILE__));
            $languages_path = WP_PLUGIN_DIR . '/' . $plugin_dir . '/languages';
            
            // First, try to load WPML-specific locale file (short code like 'en', 'de')
            $wpml_loaded = false;
            if (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE) {
                $wpml_locale = ICL_LANGUAGE_CODE; // e.g., 'en', 'de', 'it'
                $wpml_mo_file = $languages_path . '/' . $domain . '-' . $wpml_locale . '.mo';
                if (file_exists($wpml_mo_file)) {
                    $wpml_loaded = load_textdomain($domain, $wpml_mo_file);
                }
            }
            
            // Fallback to standard WordPress locale loading if WPML didn't load
            if (!$wpml_loaded) {
                load_plugin_textdomain($domain, false, $plugin_dir . '/languages');
            }
        });

        // Boot Multilanguage Compatibility Layer
        if ($container->has(Multilanguage::class)) {
            $multilanguage = $container->make(Multilanguage::class);
            $multilanguage->register_hooks();
        }
    }

    /**
     * Get list of services provided.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            LoggerInterface::class,
            CacheInterface::class,
            OptionsInterface::class,
            DatabaseInterface::class,
            ValidatorInterface::class,
            SanitizerInterface::class,
            HttpClientInterface::class,
            NonceManager::class,
            CapabilityChecker::class,
            InputSanitizer::class,
            LifecycleManager::class,
            MigrationLogger::class,
            Multilanguage::class,
            Cart::class,
        ];
    }
}

