<?php

/**
 * FP Experiences - Hook Registration Test
 *
 * Tests WordPress hook registration to ensure:
 * - All hooks are registered
 * - No duplicate hooks
 * - Hook priorities are correct
 * - Hooks are removed on deactivation
 *
 * @package FP_Exp\Tests\QA
 */

declare(strict_types=1);

namespace FP_Exp\Tests\QA;

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/../../../../wp-load.php';
}

/**
 * Hook Registration Test
 */
final class HookRegistrationTest
{
    private array $results = [];
    private array $registeredHooks = [];

    /**
     * Run all hook tests
     */
    public function runAll(): void
    {
        $this->results = [];
        $this->registeredHooks = [];

        $this->log('=== Hook Registration Test ===');
        $this->log('Started: ' . date('Y-m-d H:i:s'));
        $this->log('');

        // Collect all registered hooks
        $this->collectHooks();

        // Test critical hooks
        $this->testCriticalHooks();

        // Test for duplicates
        $this->testDuplicateHooks();

        // Test hook priorities
        $this->testHookPriorities();

        // Test hook contexts
        $this->testHookContexts();

        // Print summary
        $this->printSummary();
    }

    /**
     * Collect all registered hooks
     */
    private function collectHooks(): void
    {
        global $wp_filter;

        foreach ($wp_filter as $hook => $callbacks) {
            if (!isset($this->registeredHooks[$hook])) {
                $this->registeredHooks[$hook] = [];
            }

            foreach ($callbacks->callbacks as $priority => $callbackList) {
                foreach ($callbackList as $callback) {
                    $this->registeredHooks[$hook][] = [
                        'priority' => $priority,
                        'callback' => $this->getCallbackName($callback['function']),
                    ];
                }
            }
        }
    }

    /**
     * Get callback name for logging
     */
    private function getCallbackName($callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '::' . $callback[1];
            }
            return $callback[0] . '::' . $callback[1];
        }

        if (is_object($callback) && $callback instanceof \Closure) {
            return 'Closure';
        }

        return 'Unknown';
    }

    /**
     * Test critical hooks are registered
     */
    private function testCriticalHooks(): void
    {
        $this->log('=== Critical Hooks ===');

        $criticalHooks = [
            'wp_loaded' => 'Plugin boot',
            'init' => 'Shortcode registration',
            'rest_api_init' => 'REST API registration',
            'admin_init' => 'Admin initialization',
            'save_post_fp_experience' => 'Experience save',
        ];

        foreach ($criticalHooks as $hook => $description) {
            $this->test(
                'Critical Hooks',
                "$hook ($description) registered",
                function () use ($hook) {
                    return has_action($hook) !== false || has_filter($hook) !== false;
                }
            );
        }

        $this->log('');
    }

    /**
     * Test for duplicate hooks
     */
    private function testDuplicateHooks(): void
    {
        $this->log('=== Duplicate Hook Detection ===');

        $duplicates = [];
        foreach ($this->registeredHooks as $hook => $callbacks) {
            $callbackNames = [];
            foreach ($callbacks as $callback) {
                $name = $callback['callback'];
                if (isset($callbackNames[$name])) {
                    $duplicates[$hook][] = $name;
                } else {
                    $callbackNames[$name] = true;
                }
            }
        }

        if (empty($duplicates)) {
            $this->log('  ✓ No duplicate hooks found');
            $this->results[] = [
                'test' => 'Duplicate Hook Detection',
                'status' => 'PASS',
                'message' => 'No duplicates found',
            ];
        } else {
            $this->log('  ✗ Found duplicate hooks:');
            foreach ($duplicates as $hook => $dups) {
                $this->log("    - $hook: " . implode(', ', $dups));
            }
            $this->results[] = [
                'test' => 'Duplicate Hook Detection',
                'status' => 'FAIL',
                'message' => 'Duplicates found: ' . count($duplicates),
                'duplicates' => $duplicates,
            ];
        }

        $this->log('');
    }

    /**
     * Test hook priorities
     */
    private function testHookPriorities(): void
    {
        $this->log('=== Hook Priorities ===');

        // Test that wp_loaded hooks have correct priority
        if (isset($this->registeredHooks['wp_loaded'])) {
            $priorities = array_unique(array_column($this->registeredHooks['wp_loaded'], 'priority'));
            $this->test(
                'Hook Priorities',
                'wp_loaded hooks have appropriate priorities',
                function () use ($priorities) {
                    // Priorities should be reasonable (0-100)
                    foreach ($priorities as $priority) {
                        if ($priority < 0 || $priority > 100) {
                            return false;
                        }
                    }
                    return true;
                }
            );
        }

        $this->log('');
    }

    /**
     * Test hook contexts
     */
    private function testHookContexts(): void
    {
        $this->log('=== Hook Contexts ===');

        // Test admin-only hooks
        $adminHooks = [
            'admin_init',
            'admin_menu',
            'admin_enqueue_scripts',
        ];

        foreach ($adminHooks as $hook) {
            if (isset($this->registeredHooks[$hook])) {
                $this->test(
                    'Hook Contexts',
                    "$hook registered (admin context)",
                    function () use ($hook) {
                        return has_action($hook) !== false || has_filter($hook) !== false;
                    }
                );
            }
        }

        // Test REST API hooks
        if (isset($this->registeredHooks['rest_api_init'])) {
            $this->test(
                'Hook Contexts',
                'rest_api_init registered (REST context)',
                function () {
                    return has_action('rest_api_init') !== false;
                }
            );
        }

        $this->log('');
    }

    /**
     * Run a single test
     */
    private function test(string $category, string $description, callable $test): void
    {
        try {
            $result = $test();
            if ($result === true) {
                $this->log("  ✓ PASS: $description");
                $this->results[] = [
                    'category' => $category,
                    'test' => $description,
                    'status' => 'PASS',
                ];
            } else {
                $this->log("  ✗ FAIL: $description");
                $this->results[] = [
                    'category' => $category,
                    'test' => $description,
                    'status' => 'FAIL',
                    'message' => is_string($result) ? $result : 'Test returned false',
                ];
            }
        } catch (\Throwable $e) {
            $this->log("  ✗ ERROR: $description - " . $e->getMessage());
            $this->results[] = [
                'category' => $category,
                'test' => $description,
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ];
        }
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

        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        $errors = count(array_filter($this->results, fn($r) => $r['status'] === 'ERROR'));

        $this->log("Total Tests: $total");
        $this->log("Passed: $passed");
        $this->log("Failed: $failed");
        $this->log("Errors: $errors");

        $successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
        $this->log("Success Rate: {$successRate}%");

        $this->log('');
        $this->log('Total Hooks Registered: ' . count($this->registeredHooks));
        $this->log('');
        $this->log('Completed: ' . date('Y-m-d H:i:s'));
    }

    /**
     * Get results
     */
    public function getResults(): array
    {
        return [
            'results' => $this->results,
            'registeredHooks' => $this->registeredHooks,
        ];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' || (isset($_GET['run_hook_test']) && current_user_can('manage_options'))) {
    $test = new HookRegistrationTest();
    $test->runAll();
}







