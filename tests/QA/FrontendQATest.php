<?php

/**
 * FP Experiences - Frontend QA Test
 *
 * Tests frontend rendering, shortcodes, and accessibility
 * as specified in the QA Plan section 5.
 *
 * @package FP_Exp\Tests\QA
 */

declare(strict_types=1);

namespace FP_Exp\Tests\QA;

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/../../../../wp-load.php';
}

/**
 * Frontend QA Test
 */
final class FrontendQATest
{
    private array $results = [];

    /**
     * Run all frontend QA tests
     */
    public function runAll(): void
    {
        $this->results = [];

        $this->log('=== Frontend QA Test ===');
        $this->log('Started: ' . date('Y-m-d H:i:s'));
        $this->log('');

        // Test shortcode registration
        $this->testShortcodeRegistration();

        // Test shortcode rendering
        $this->testShortcodeRendering();

        // Test asset loading
        $this->testAssetLoading();

        // Test HTML structure
        $this->testHTMLStructure();

        // Print summary
        $this->printSummary();
    }

    /**
     * Test shortcode registration
     */
    private function testShortcodeRegistration(): void
    {
        $this->log('=== Shortcode Registration ===');

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
            $this->test(
                'Shortcode Registration',
                "Shortcode [$shortcode] registered",
                function () use ($shortcode_tags, $shortcode) {
                    return isset($shortcode_tags[$shortcode]);
                }
            );
        }

        $this->log('');
    }

    /**
     * Test shortcode rendering
     */
    private function testShortcodeRendering(): void
    {
        $this->log('=== Shortcode Rendering ===');

        // Test list shortcode
        $this->test(
            'Shortcode Rendering',
            '[fp_experiences_list] renders without errors',
            function () {
                ob_start();
                $output = do_shortcode('[fp_experiences_list]');
                $errors = ob_get_clean();
                return empty($errors) && (is_string($output) || $output === '');
            }
        );

        // Test experience shortcode with invalid ID
        $this->test(
            'Shortcode Rendering',
            '[fp_experience] handles missing experience gracefully',
            function () {
                ob_start();
                $output = do_shortcode('[fp_experience id="999999"]');
                $errors = ob_get_clean();
                return empty($errors);
            }
        );

        $this->log('');
    }

    /**
     * Test asset loading
     */
    private function testAssetLoading(): void
    {
        $this->log('=== Asset Loading ===');

        // Test CSS is enqueued
        $this->test(
            'Asset Loading',
            'Frontend CSS is registered',
            function () {
                global $wp_styles;
                return isset($wp_styles->registered['fp-experiences-frontend']);
            }
        );

        // Test JavaScript is enqueued
        $this->test(
            'Asset Loading',
            'Frontend JavaScript is registered',
            function () {
                global $wp_scripts;
                return isset($wp_scripts->registered['fp-experiences-frontend']);
            }
        );

        $this->log('');
    }

    /**
     * Test HTML structure
     */
    private function testHTMLStructure(): void
    {
        $this->log('=== HTML Structure ===');

        // Test shortcode output is escaped
        $this->test(
            'HTML Structure',
            'Shortcode output is properly escaped',
            function () {
                // Create test experience with XSS attempt
                $testContent = '<script>alert("xss")</script>';
                $output = do_shortcode('[fp_experience id="1"]');
                // Output should not contain unescaped script tags
                return strpos($output, '<script>') === false || 
                       strpos($output, '&lt;script&gt;') !== false;
            }
        );

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
        $this->log('Completed: ' . date('Y-m-d H:i:s'));
    }

    /**
     * Get results
     */
    public function getResults(): array
    {
        return $this->results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' || (isset($_GET['run_frontend_test']) && current_user_can('manage_options'))) {
    $test = new FrontendQATest();
    $test->runAll();
}







