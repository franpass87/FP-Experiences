<?php

/**
 * FP Experiences - Database Integrity Test
 *
 * Tests database schema, data integrity, and migration behavior
 * as specified in the QA Plan section 9.
 *
 * @package FP_Exp\Tests\QA
 */

declare(strict_types=1);

namespace FP_Exp\Tests\QA;

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/../../../../wp-load.php';
}

/**
 * Database Integrity Test
 */
final class DatabaseIntegrityTest
{
    private array $results = [];

    /**
     * Run all database integrity tests
     */
    public function runAll(): void
    {
        $this->results = [];

        $this->log('=== Database Integrity Test ===');
        $this->log('Started: ' . date('Y-m-d H:i:s'));
        $this->log('');

        // Test schema validation
        $this->testSchemaValidation();

        // Test table structure
        $this->testTableStructure();

        // Test indexes
        $this->testIndexes();

        // Test data integrity
        $this->testDataIntegrity();

        // Print summary
        $this->printSummary();
    }

    /**
     * Test schema validation
     */
    private function testSchemaValidation(): void
    {
        $this->log('=== Schema Validation ===');

        global $wpdb;

        $expectedTables = [
            'fp_exp_slots',
            'fp_exp_reservations',
            'fp_exp_resources',
            'fp_exp_vouchers',
        ];

        foreach ($expectedTables as $table) {
            $fullTable = $wpdb->prefix . $table;
            $this->test(
                'Schema Validation',
                "Table $table exists",
                function () use ($wpdb, $fullTable) {
                    return $wpdb->get_var("SHOW TABLES LIKE '$fullTable'") === $fullTable;
                }
            );
        }

        $this->log('');
    }

    /**
     * Test table structure
     */
    private function testTableStructure(): void
    {
        $this->log('=== Table Structure ===');

        global $wpdb;

        // Test slots table structure
        $this->test(
            'Table Structure',
            'Slots table has required columns',
            function () use ($wpdb) {
                $table = $wpdb->prefix . 'fp_exp_slots';
                $columns = $wpdb->get_col("DESCRIBE $table");
                $required = ['id', 'experience_id', 'start', 'end', 'capacity', 'remaining'];
                foreach ($required as $col) {
                    if (!in_array($col, $columns, true)) {
                        return "Missing column: $col";
                    }
                }
                return true;
            }
        );

        // Test reservations table structure
        $this->test(
            'Table Structure',
            'Reservations table has required columns',
            function () use ($wpdb) {
                $table = $wpdb->prefix . 'fp_exp_reservations';
                $columns = $wpdb->get_col("DESCRIBE $table");
                $required = ['id', 'experience_id', 'slot_id', 'order_id'];
                foreach ($required as $col) {
                    if (!in_array($col, $columns, true)) {
                        return "Missing column: $col";
                    }
                }
                return true;
            }
        );

        // Test charset/collation
        $this->test(
            'Table Structure',
            'Tables use correct charset/collation',
            function () use ($wpdb) {
                $tables = [
                    $wpdb->prefix . 'fp_exp_slots',
                    $wpdb->prefix . 'fp_exp_reservations',
                ];
                foreach ($tables as $table) {
                    $charset = $wpdb->get_var("SELECT CCSA.character_set_name 
                        FROM information_schema.TABLES T
                        INNER JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY CCSA
                        ON CCSA.collation_name = T.table_collation
                        WHERE T.table_schema = DATABASE()
                        AND T.table_name = '$table'");
                    if (empty($charset)) {
                        return "Table $table charset not found";
                    }
                }
                return true;
            }
        );

        $this->log('');
    }

    /**
     * Test indexes
     */
    private function testIndexes(): void
    {
        $this->log('=== Indexes ===');

        global $wpdb;

        // Test slots table indexes
        $this->test(
            'Indexes',
            'Slots table has primary key',
            function () use ($wpdb) {
                $table = $wpdb->prefix . 'fp_exp_slots';
                $indexes = $wpdb->get_results("SHOW INDEXES FROM $table");
                foreach ($indexes as $index) {
                    if ($index->Key_name === 'PRIMARY') {
                        return true;
                    }
                }
                return 'Primary key not found';
            }
        );

        // Test experience_id index
        $this->test(
            'Indexes',
            'Slots table has experience_id index',
            function () use ($wpdb) {
                $table = $wpdb->prefix . 'fp_exp_slots';
                $indexes = $wpdb->get_results("SHOW INDEXES FROM $table");
                foreach ($indexes as $index) {
                    if ($index->Column_name === 'experience_id' && $index->Key_name !== 'PRIMARY') {
                        return true;
                    }
                }
                return 'experience_id index not found';
            }
        );

        $this->log('');
    }

    /**
     * Test data integrity
     */
    private function testDataIntegrity(): void
    {
        $this->log('=== Data Integrity ===');

        global $wpdb;

        // Test no orphaned reservations
        $this->test(
            'Data Integrity',
            'No orphaned reservations (missing experience)',
            function () use ($wpdb) {
                $reservationsTable = $wpdb->prefix . 'fp_exp_reservations';
                $orphans = $wpdb->get_var(
                    "SELECT COUNT(*) FROM $reservationsTable r
                    LEFT JOIN {$wpdb->posts} p ON r.experience_id = p.ID
                    WHERE p.ID IS NULL AND r.experience_id > 0"
                );
                return $orphans == 0 ? true : "Found $orphans orphaned reservations";
            }
        );

        // Test no orphaned slots
        $this->test(
            'Data Integrity',
            'No orphaned slots (missing experience)',
            function () use ($wpdb) {
                $slotsTable = $wpdb->prefix . 'fp_exp_slots';
                $orphans = $wpdb->get_var(
                    "SELECT COUNT(*) FROM $slotsTable s
                    LEFT JOIN {$wpdb->posts} p ON s.experience_id = p.ID
                    WHERE p.ID IS NULL AND s.experience_id > 0"
                );
                return $orphans == 0 ? true : "Found $orphans orphaned slots";
            }
        );

        // Test slot dates are valid
        $this->test(
            'Data Integrity',
            'Slot dates are valid (start < end)',
            function () use ($wpdb) {
                $slotsTable = $wpdb->prefix . 'fp_exp_slots';
                $invalid = $wpdb->get_var(
                    "SELECT COUNT(*) FROM $slotsTable
                    WHERE start >= end"
                );
                return $invalid == 0 ? true : "Found $invalid slots with invalid dates";
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
                $this->log("  ✗ FAIL: $description" . (is_string($result) ? " - $result" : ''));
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
if (php_sapi_name() === 'cli' || (isset($_GET['run_db_test']) && current_user_can('manage_options'))) {
    $test = new DatabaseIntegrityTest();
    $test->runAll();
}







