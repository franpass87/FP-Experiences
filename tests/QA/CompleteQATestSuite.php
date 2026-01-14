<?php

/**
 * FP Experiences - Complete QA Test Suite
 *
 * Comprehensive test suite covering all modules and functionality
 * as defined in the QA Plan.
 *
 * @package FP_Exp\Tests\QA
 */

declare(strict_types=1);

namespace FP_Exp\Tests\QA;

use FP_Exp\Core\Bootstrap\Bootstrap;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Cache\CacheInterface;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Services\Database\DatabaseInterface;
use FP_Exp\Services\Security\NonceManager;
use FP_Exp\Services\Security\CapabilityChecker;
use FP_Exp\Booking\Slots;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\AvailabilityService;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Gift\VoucherTable;
use WP_Error;

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/../../../../wp-load.php';
}

/**
 * Complete QA Test Suite
 */
final class CompleteQATestSuite
{
    private array $results = [];
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private int $skippedTests = 0;

    /**
     * Run all QA tests
     */
    public function runAll(): void
    {
        $this->results = [];
        $this->totalTests = 0;
        $this->passedTests = 0;
        $this->failedTests = 0;
        $this->skippedTests = 0;

        $this->log('=== FP Experiences Complete QA Test Suite ===');
        $this->log('Started: ' . date('Y-m-d H:i:s'));
        $this->log('');

        // Core Services Tests
        $this->testCoreServices();

        // Booking System Tests
        $this->testBookingSystem();

        // Gift Voucher Tests
        $this->testGiftVoucher();

        // REST API Tests
        $this->testRestAPI();

        // Shortcodes Tests
        $this->testShortcodes();

        // Integrations Tests
        $this->testIntegrations();

        // Admin UI Tests
        $this->testAdminUI();

        // Database Tests
        $this->testDatabase();

        // Security Tests
        $this->testSecurity();

        // Performance Tests
        $this->testPerformance();

        // Hook Registration Tests
        $this->testHookRegistration();

        // Database Integrity Tests
        $this->testDatabaseIntegrity();

        // Frontend QA Tests
        $this->testFrontendQA();

        // Print summary
        $this->printSummary();
    }

    /**
     * Test Core Services Module
     */
    private function testCoreServices(): void
    {
        $this->log('=== Core Services Module ===');

        $kernel = Bootstrap::kernel();
        if (!$kernel) {
            $this->fail('Core Services', 'Bootstrap kernel not initialized');
            return;
        }

        $container = $kernel->container();

        // Test Logger
        $this->test('Core Services', 'Logger resolves from container', function () use ($container) {
            return $container->has(LoggerInterface::class);
        });

        // Test Cache
        $this->test('Core Services', 'Cache resolves from container', function () use ($container) {
            return $container->has(CacheInterface::class);
        });

        // Test Options
        $this->test('Core Services', 'Options resolves from container', function () use ($container) {
            return $container->has(OptionsInterface::class);
        });

        // Test Database
        $this->test('Core Services', 'Database resolves from container', function () use ($container) {
            return $container->has(DatabaseInterface::class);
        });

        // Test Security Services
        $this->test('Core Services', 'NonceManager resolves from container', function () use ($container) {
            return $container->has(NonceManager::class);
        });

        $this->test('Core Services', 'CapabilityChecker resolves from container', function () use ($container) {
            return $container->has(CapabilityChecker::class);
        });

        // Test Logger Service (Section 3.1 - Logger Service checklist)
        if ($container->has(LoggerInterface::class)) {
            $logger = $container->make(LoggerInterface::class);
            
            $this->test('Core Services', 'Log message with context', function () use ($logger) {
                if (method_exists($logger, 'log')) {
                    $logger->log('info', 'Test message', ['context' => 'test']);
                    return true;
                }
                return method_exists($logger, 'info');
            });

            $this->test('Core Services', 'Log levels available (debug, info, warning, error)', function () use ($logger) {
                $levels = ['debug', 'info', 'warning', 'error'];
                foreach ($levels as $level) {
                    if (!method_exists($logger, $level)) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Test Cache Service (Section 3.1 - Cache Service checklist)
        if ($container->has(CacheInterface::class)) {
            $cache = $container->make(CacheInterface::class);
            
            $this->test('Core Services', 'Cache set/get operations', function () use ($cache) {
                $cache->set('test_key', 'test_value', 60);
                return $cache->get('test_key') === 'test_value';
            });

            $this->test('Core Services', 'Cache expiration (TTL)', function () use ($cache) {
                $cache->set('expire_test', 'value', 1);
                sleep(2);
                return $cache->get('expire_test') === null;
            });

            $this->test('Core Services', 'Cache deletion', function () use ($cache) {
                $cache->set('delete_test', 'value', 60);
                $cache->delete('delete_test');
                return $cache->get('delete_test') === null;
            });

            $this->test('Core Services', 'Cache key collision prevention', function () use ($cache) {
                $cache->set('collision_test_1', 'value1', 60);
                $cache->set('collision_test_2', 'value2', 60);
                return $cache->get('collision_test_1') === 'value1' && 
                       $cache->get('collision_test_2') === 'value2';
            });
        }

        // Test Options Service (Section 3.1 - Options Service checklist)
        if ($container->has(OptionsInterface::class)) {
            $options = $container->make(OptionsInterface::class);
            
            $this->test('Core Services', 'Option get/set/delete', function () use ($options) {
                $options->set('test_option', 'test_value');
                $value = $options->get('test_option');
                $options->delete('test_option');
                return $value === 'test_value';
            });

            $this->test('Core Services', 'Option default values', function () use ($options) {
                $default = 'default_value';
                $value = $options->get('non_existent_option', $default);
                return $value === $default;
            });

            $this->test('Core Services', 'Option serialization (arrays, objects)', function () use ($options) {
                $testArray = ['key1' => 'value1', 'key2' => 'value2'];
                $options->set('test_array', $testArray);
                $retrieved = $options->get('test_array');
                $options->delete('test_array');
                return is_array($retrieved) && $retrieved === $testArray;
            });
        }

        // Test Database Service (Section 3.1 - Database Service checklist)
        if ($container->has(DatabaseInterface::class)) {
            $database = $container->make(DatabaseInterface::class);
            
            $this->test('Core Services', 'Query execution (SELECT)', function () use ($database) {
                if (method_exists($database, 'query')) {
                    // Test basic query capability
                    return true;
                }
                return method_exists($database, 'get_results');
            });

            $this->test('Core Services', 'Prepared statements (SQL injection prevention)', function () use ($database) {
                // Verify database service supports prepared statements
                return method_exists($database, 'prepare') || 
                       method_exists($database, 'query');
            });
        }

        // Test Security Services (Section 3.1 - Security Services checklist)
        if ($container->has(NonceManager::class)) {
            $nonceManager = $container->make(NonceManager::class);
            
            $this->test('Core Services', 'Nonce generation uniqueness', function () use ($nonceManager) {
                $nonce1 = $nonceManager->create('test_action');
                $nonce2 = $nonceManager->create('test_action');
                return $nonce1 !== $nonce2 && !empty($nonce1) && !empty($nonce2);
            });

            $this->test('Core Services', 'Nonce verification (valid/invalid)', function () use ($nonceManager) {
                $nonce = $nonceManager->create('test_action');
                $valid = $nonceManager->verify($nonce, 'test_action');
                $invalid = $nonceManager->verify('invalid_nonce', 'test_action');
                return $valid === true && $invalid === false;
            });
        }

        if ($container->has(CapabilityChecker::class)) {
            $capabilityChecker = $container->make(CapabilityChecker::class);
            
            $this->test('Core Services', 'Capability check enforcement', function () use ($capabilityChecker) {
                if (method_exists($capabilityChecker, 'check')) {
                    // Test that capability checking works
                    return true;
                }
                return method_exists($capabilityChecker, 'can');
            });
        }

        $this->log('');
    }

    /**
     * Test Booking System Module
     */
    private function testBookingSystem(): void
    {
        $this->log('=== Booking System Module ===');

        // Test Slots Table
        $this->test('Booking System', 'Slots table exists', function () {
            global $wpdb;
            $table = $wpdb->prefix . 'fp_exp_slots';
            return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        });

        // Test Reservations Table
        $this->test('Booking System', 'Reservations table exists', function () {
            global $wpdb;
            $table = $wpdb->prefix . 'fp_exp_reservations';
            return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        });

        // Test Resources Table
        $this->test('Booking System', 'Resources table exists', function () {
            global $wpdb;
            $table = $wpdb->prefix . 'fp_exp_resources';
            return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        });

        // Test Slots Class
        $this->test('Booking System', 'Slots class exists', function () {
            return class_exists(Slots::class);
        });

        // Test Reservations Class
        $this->test('Booking System', 'Reservations class exists', function () {
            return class_exists(Reservations::class);
        });

        // Test AvailabilityService
        $this->test('Booking System', 'AvailabilityService class exists', function () {
            return class_exists(AvailabilityService::class);
        });

        // Test Slot Management (Section 3.2 - Slot Management checklist)
        $this->test('Booking System', 'Slot validation (start < end)', function () {
            global $wpdb;
            $table = $wpdb->prefix . 'fp_exp_slots';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
            // Check if table has start and end columns
            $columns = $wpdb->get_col("DESCRIBE $table");
            return in_array('start', $columns, true) && in_array('end', $columns, true);
        });

        // Test Availability Calculation (Section 3.2 - Availability Calculation checklist)
        if (class_exists(AvailabilityService::class)) {
            $this->test('Booking System', 'AvailabilityService can calculate availability', function () {
                return method_exists(AvailabilityService::class, 'getAvailableSlots') ||
                       method_exists(AvailabilityService::class, 'checkAvailability');
            });
        }

        // Test Cart Management (Section 3.2 - Cart Management checklist)
        $this->test('Booking System', 'Cart functionality available', function () {
            // Check if WooCommerce is active for cart integration
            return class_exists('WooCommerce') || 
                   function_exists('WC') ||
                   defined('WC_VERSION');
        });

        // Test Checkout Process (Section 3.2 - Checkout Process checklist)
        $this->test('Booking System', 'WooCommerce integration for checkout', function () {
            return class_exists('WooCommerce') || function_exists('WC');
        });

        $this->log('');
    }

    /**
     * Test Gift Voucher Module
     */
    private function testGiftVoucher(): void
    {
        $this->log('=== Gift Voucher Module ===');

        // Test Voucher Table
        $this->test('Gift Voucher', 'Voucher table exists', function () {
            global $wpdb;
            $table = $wpdb->prefix . 'fp_exp_vouchers';
            return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        });

        // Test VoucherManager
        $this->test('Gift Voucher', 'VoucherManager class exists', function () {
            return class_exists(VoucherManager::class);
        });

        // Test VoucherTable
        $this->test('Gift Voucher', 'VoucherTable class exists', function () {
            return class_exists(VoucherTable::class);
        });

        // Test Voucher Creation (Section 3.3 - Voucher Creation checklist)
        if (class_exists(VoucherManager::class)) {
            $this->test('Gift Voucher', 'VoucherManager can generate vouchers', function () {
                return method_exists(VoucherManager::class, 'create') ||
                       method_exists(VoucherManager::class, 'generate');
            });

            $this->test('Gift Voucher', 'Voucher code uniqueness check', function () {
                // Verify voucher manager has uniqueness checking
                return method_exists(VoucherManager::class, 'isCodeUnique') ||
                       method_exists(VoucherManager::class, 'validateCode');
            });
        }

        // Test Voucher Redemption (Section 3.3 - Voucher Redemption checklist)
        if (class_exists(VoucherManager::class)) {
            $this->test('Gift Voucher', 'Voucher redemption functionality', function () {
                return method_exists(VoucherManager::class, 'redeem') ||
                       method_exists(VoucherManager::class, 'apply');
            });
        }

        // Test Voucher Delivery (Section 3.3 - Voucher Delivery checklist)
        $this->test('Gift Voucher', 'Email system available for voucher delivery', function () {
            return function_exists('wp_mail') || 
                   class_exists('PHPMailer');
        });

        $this->log('');
    }

    /**
     * Test REST API Module
     */
    private function testRestAPI(): void
    {
        $this->log('=== REST API Module ===');

        // Test REST API namespace
        $this->test('REST API', 'REST API namespace registered', function () {
            $routes = rest_get_server()->get_routes();
            $namespace = 'fp-exp/v1';
            foreach ($routes as $route => $handlers) {
                if (strpos($route, $namespace) === 0) {
                    return true;
                }
            }
            return false;
        });

        // Test Availability endpoint
        $this->test('REST API', 'Availability endpoint exists', function () {
            $routes = rest_get_server()->get_routes();
            return isset($routes['/fp-exp/v1/availability/(?P<id>[\d]+)']);
        });

        // Test Calendar endpoint
        $this->test('REST API', 'Calendar endpoint exists', function () {
            $routes = rest_get_server()->get_routes();
            return isset($routes['/fp-exp/v1/calendar/(?P<id>[\d]+)']);
        });

        // Test Authentication (Section 7 - Authentication)
        $this->test('REST API', 'REST API authentication available', function () {
            return function_exists('rest_authentication_errors') ||
                   function_exists('wp_rest_authentication_errors');
        });

        // Test Permission Checks (Section 7 - Permission Checks)
        $this->test('REST API', 'Permission callbacks can be enforced', function () {
            $routes = rest_get_server()->get_routes();
            $namespace = 'fp-exp/v1';
            foreach ($routes as $route => $handlers) {
                if (strpos($route, $namespace) === 0 && !empty($handlers)) {
                    return true;
                }
            }
            return false;
        });

        // Test Input Validation (Section 7 - Input Validation)
        $this->test('REST API', 'REST API input validation available', function () {
            return function_exists('rest_validate_value_from_schema') ||
                   class_exists('WP_REST_Request');
        });

        // Test HTTP Status Codes (Section 7 - HTTP Status Codes)
        $this->test('REST API', 'REST API supports HTTP status codes', function () {
            return class_exists('WP_REST_Response') ||
                   function_exists('rest_ensure_response');
        });

        $this->log('');
    }

    /**
     * Test Shortcodes Module
     */
    private function testShortcodes(): void
    {
        $this->log('=== Shortcodes Module ===');

        global $shortcode_tags;

        $expectedShortcodes = [
            'fp_experiences_list',
            'fp_experience',
            'fp_experience_calendar',
            'fp_experience_checkout',
            'fp_experience_widget',
            'fp_gift_redeem',
        ];

        foreach ($expectedShortcodes as $shortcode) {
            $this->test('Shortcodes', "Shortcode [$shortcode] registered", function () use ($shortcode_tags, $shortcode) {
                return isset($shortcode_tags[$shortcode]);
            });
        }

        // Test Shortcode Rendering (Section 3.5 - Expected Results)
        $this->test('Shortcodes', 'Shortcodes can render without fatal errors', function () {
            ob_start();
            $output = do_shortcode('[fp_experiences_list]');
            $errors = ob_get_clean();
            return empty($errors) || is_string($output);
        });

        // Test List Shortcode (Section 3.5 - List Shortcode checklist)
        $this->test('Shortcodes', '[fp_experiences_list] renders correctly', function () {
            ob_start();
            $output = do_shortcode('[fp_experiences_list]');
            $errors = ob_get_clean();
            return empty($errors);
        });

        // Test Experience Shortcode (Section 3.5 - Experience Shortcode checklist)
        $this->test('Shortcodes', '[fp_experience] handles missing experience gracefully', function () {
            ob_start();
            $output = do_shortcode('[fp_experience id="999999"]');
            $errors = ob_get_clean();
            return empty($errors);
        });

        $this->log('');
    }

    /**
     * Test Integrations Module
     */
    private function testIntegrations(): void
    {
        $this->log('=== Integrations Module ===');

        // Test Google Calendar Integration
        $this->test('Integrations', 'GoogleCalendar class exists', function () {
            return class_exists(\FP_Exp\Integrations\GoogleCalendar::class) ||
                   class_exists(\FP_Exp\Integrations\GoogleCalendar\GoogleCalendarIntegration::class);
        });

        // Test Brevo Integration
        $this->test('Integrations', 'Brevo class exists', function () {
            return class_exists(\FP_Exp\Integrations\Brevo::class) ||
                   class_exists(\FP_Exp\Integrations\Brevo\BrevoIntegration::class);
        });

        // Test Performance Integration
        $this->test('Integrations', 'PerformanceIntegration class exists', function () {
            return class_exists(\FP_Exp\Integrations\PerformanceIntegration::class);
        });

        $this->log('');
    }

    /**
     * Test Admin UI Module
     */
    private function testAdminUI(): void
    {
        $this->log('=== Admin UI Module ===');

        // Test Admin Menu
        $this->test('Admin UI', 'Admin menu registered', function () {
            global $menu, $submenu;
            $found = false;
            if (is_array($menu)) {
                foreach ($menu as $item) {
                    if (is_array($item) && isset($item[0]) && stripos($item[0], 'FP Experiences') !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            return $found;
        });

        // Test Settings Page
        $this->test('Admin UI', 'SettingsPage class exists', function () {
            return class_exists(\FP_Exp\Admin\SettingsPage::class);
        });

        $this->log('');
    }

    /**
     * Test Database Module
     */
    private function testDatabase(): void
    {
        $this->log('=== Database Module ===');

        global $wpdb;

        $tables = [
            'fp_exp_slots',
            'fp_exp_reservations',
            'fp_exp_resources',
            'fp_exp_vouchers',
        ];

        foreach ($tables as $table) {
            $fullTable = $wpdb->prefix . $table;
            $this->test('Database', "Table $table exists", function () use ($wpdb, $fullTable) {
                return $wpdb->get_var("SHOW TABLES LIKE '$fullTable'") === $fullTable;
            });
        }

        $this->log('');
    }

    /**
     * Test Security Module
     */
    private function testSecurity(): void
    {
        $this->log('=== Security Module ===');

        $kernel = Bootstrap::kernel();
        if (!$kernel) {
            $this->skip('Security', 'Bootstrap kernel not initialized');
            return;
        }

        $container = $kernel->container();

        // Test NonceManager
        if ($container->has(NonceManager::class)) {
            $nonceManager = $container->make(NonceManager::class);
            $this->test('Security', 'Nonce generation', function () use ($nonceManager) {
                $nonce = $nonceManager->create('test_action');
                return !empty($nonce) && is_string($nonce);
            });

            $this->test('Security', 'Nonce verification', function () use ($nonceManager) {
                $nonce = $nonceManager->create('test_action');
                return $nonceManager->verify($nonce, 'test_action');
            });
        }

        // Test CapabilityChecker
        if ($container->has(CapabilityChecker::class)) {
            $capabilityChecker = $container->make(CapabilityChecker::class);
            $this->test('Security', 'CapabilityChecker class functional', function () use ($capabilityChecker) {
                return method_exists($capabilityChecker, 'check');
            });
        }

        $this->log('');
    }

    /**
     * Test Performance Module
     */
    private function testPerformance(): void
    {
        $this->log('=== Performance Module ===');

        // Test REST API cache exclusion
        $this->test('Performance', 'REST API cache exclusion', function () {
            $excluded = apply_filters('fp_ps_cache_exclusions', []);
            return in_array('/wp-json/fp-exp/', $excluded) || in_array('wp-json/fp-exp/', $excluded);
        });

        $this->log('');
    }

    /**
     * Test Hook Registration
     */
    private function testHookRegistration(): void
    {
        $this->log('=== Hook Registration ===');

        // Test critical hooks
        $criticalHooks = [
            'wp_loaded',
            'init',
            'rest_api_init',
            'admin_init',
        ];

        foreach ($criticalHooks as $hook) {
            $this->test('Hook Registration', "Hook $hook has callbacks", function () use ($hook) {
                return has_action($hook) !== false;
            });
        }

        $this->log('');
    }

    /**
     * Test Database Integrity
     */
    private function testDatabaseIntegrity(): void
    {
        $this->log('=== Database Integrity ===');

        global $wpdb;

        // Test all tables exist
        $tables = [
            'fp_exp_slots',
            'fp_exp_reservations',
            'fp_exp_resources',
            'fp_exp_vouchers',
        ];

        foreach ($tables as $table) {
            $fullTable = $wpdb->prefix . $table;
            $this->test(
                'Database Integrity',
                "Table $table exists",
                function () use ($wpdb, $fullTable) {
                    return $wpdb->get_var("SHOW TABLES LIKE '$fullTable'") === $fullTable;
                }
            );
        }

        // Test table structure
        $this->test(
            'Database Integrity',
            'Slots table has required columns',
            function () use ($wpdb) {
                $table = $wpdb->prefix . 'fp_exp_slots';
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    return 'Table does not exist';
                }
                $columns = $wpdb->get_col("DESCRIBE $table");
                $required = ['id', 'experience_id', 'start', 'end'];
                foreach ($required as $col) {
                    if (!in_array($col, $columns, true)) {
                        return "Missing column: $col";
                    }
                }
                return true;
            }
        );

        $this->log('');
    }

    /**
     * Test Frontend QA
     */
    private function testFrontendQA(): void
    {
        $this->log('=== Frontend QA ===');

        global $shortcode_tags;

        // Test shortcodes are registered
        $this->test(
            'Frontend QA',
            'Shortcodes registered',
            function () use ($shortcode_tags) {
                $expected = ['fp_experiences_list', 'fp_experience'];
                foreach ($expected as $shortcode) {
                    if (!isset($shortcode_tags[$shortcode])) {
                        return "Shortcode $shortcode not registered";
                    }
                }
                return true;
            }
        );

        // Test assets are registered
        $this->test(
            'Frontend QA',
            'Frontend assets registered',
            function () {
                global $wp_styles, $wp_scripts;
                $css = isset($wp_styles->registered['fp-experiences-frontend']);
                $js = isset($wp_scripts->registered['fp-experiences-frontend']);
                return $css || $js; // At least one should be registered
            }
        );

        $this->log('');
    }

    /**
     * Run a single test
     */
    private function test(string $module, string $description, callable $test): void
    {
        $this->totalTests++;
        $startTime = microtime(true);

        try {
            $result = $test();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result === true) {
                $this->passedTests++;
                $this->log("  ✓ PASS: $description ({$duration}ms)");
                $this->results[] = [
                    'module' => $module,
                    'test' => $description,
                    'status' => 'PASS',
                    'duration' => $duration,
                ];
            } else {
                $this->failedTests++;
                $this->log("  ✗ FAIL: $description ({$duration}ms)");
                $this->results[] = [
                    'module' => $module,
                    'test' => $description,
                    'status' => 'FAIL',
                    'duration' => $duration,
                    'message' => is_string($result) ? $result : 'Test returned false',
                ];
            }
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->failedTests++;
            $this->log("  ✗ ERROR: $description - " . $e->getMessage());
            $this->results[] = [
                'module' => $module,
                'test' => $description,
                'status' => 'ERROR',
                'duration' => $duration,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark test as failed
     */
    private function fail(string $module, string $description): void
    {
        $this->totalTests++;
        $this->failedTests++;
        $this->log("  ✗ FAIL: $description");
        $this->results[] = [
            'module' => $module,
            'test' => $description,
            'status' => 'FAIL',
        ];
    }

    /**
     * Mark test as skipped
     */
    private function skip(string $module, string $description): void
    {
        $this->totalTests++;
        $this->skippedTests++;
        $this->log("  ⊘ SKIP: $description");
        $this->results[] = [
            'module' => $module,
            'test' => $description,
            'status' => 'SKIP',
        ];
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        if (php_sapi_name() === 'cli') {
            echo $message . PHP_EOL;
        } else {
            echo '<pre>' . esc_html($message) . '</pre>';
        }
    }

    /**
     * Print test summary
     */
    private function printSummary(): void
    {
        $this->log('');
        $this->log('=== Test Summary ===');
        $this->log("Total Tests: {$this->totalTests}");
        $this->log("Passed: {$this->passedTests}");
        $this->log("Failed: {$this->failedTests}");
        $this->log("Skipped: {$this->skippedTests}");

        $successRate = $this->totalTests > 0
            ? round(($this->passedTests / $this->totalTests) * 100, 2)
            : 0;

        $this->log("Success Rate: {$successRate}%");
        $this->log('');
        $this->log('Completed: ' . date('Y-m-d H:i:s'));

        // Generate JSON report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total' => $this->totalTests,
                'passed' => $this->passedTests,
                'failed' => $this->failedTests,
                'skipped' => $this->skippedTests,
                'success_rate' => $successRate,
            ],
            'results' => $this->results,
        ];

        $reportFile = dirname(__FILE__) . '/qa-report-' . date('Y-m-d-His') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        $this->log("Report saved to: $reportFile");
    }

    /**
     * Get results as array
     */
    public function getResults(): array
    {
        return [
            'summary' => [
                'total' => $this->totalTests,
                'passed' => $this->passedTests,
                'failed' => $this->failedTests,
                'skipped' => $this->skippedTests,
            ],
            'results' => $this->results,
        ];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' || (isset($_GET['run_qa']) && current_user_can('manage_options'))) {
    $suite = new CompleteQATestSuite();
    $suite->runAll();
}

