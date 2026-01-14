<?php

/**
 * FP Experiences - Run All QA Tests
 *
 * Master script to run all QA test suites.
 * Can be executed via CLI or web interface.
 *
 * @package FP_Exp\Tests\QA
 */

declare(strict_types=1);

namespace FP_Exp\Tests\QA;

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/../../../../wp-load.php';
}

// Check permissions for web access
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['run_all_qa']) || !current_user_can('manage_options')) {
        if (function_exists('wp_die')) {
            wp_die('Unauthorized access');
        } else {
            die('Unauthorized access');
        }
    }
}

/**
 * Run all QA tests
 */
function run_all_qa_tests(): void
{
    $startTime = microtime(true);
    $allResults = [];

    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║   FP Experiences - Complete QA Test Suite Runner     ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n\n";

    // Test 1: Complete QA Test Suite
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Test Suite 1: Complete QA Test Suite\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $suite1 = new CompleteQATestSuite();
    $suite1->runAll();
    $allResults['complete'] = $suite1->getResults();

    echo "\n\n";

    // Test 2: Hook Registration Test
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Test Suite 2: Hook Registration Test\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $suite2 = new HookRegistrationTest();
    $suite2->runAll();
    $allResults['hooks'] = $suite2->getResults();

    echo "\n\n";

    // Test 3: Database Integrity Test
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Test Suite 3: Database Integrity Test\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $suite3 = new DatabaseIntegrityTest();
    $suite3->runAll();
    $allResults['database'] = $suite3->getResults();

    echo "\n\n";

    // Test 4: Frontend QA Test
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Test Suite 4: Frontend QA Test\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $suite4 = new FrontendQATest();
    $suite4->runAll();
    $allResults['frontend'] = $suite4->getResults();

    echo "\n\n";

    // Final Summary
    $duration = round((microtime(true) - $startTime), 2);
    
    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║              FINAL SUMMARY - ALL TEST SUITES          ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n\n";

    $totalTests = 0;
    $totalPassed = 0;
    $totalFailed = 0;
    $totalErrors = 0;

    foreach ($allResults as $suiteName => $results) {
        if (isset($results['summary'])) {
            $totalTests += $results['summary']['total'] ?? 0;
            $totalPassed += $results['summary']['passed'] ?? 0;
            $totalFailed += $results['summary']['failed'] ?? 0;
            if (isset($results['summary']['errors'])) {
                $totalErrors += $results['summary']['errors'];
            }
        } elseif (isset($results['results'])) {
            $suiteTotal = count($results['results']);
            $suitePassed = count(array_filter($results['results'], fn($r) => ($r['status'] ?? '') === 'PASS'));
            $suiteFailed = count(array_filter($results['results'], fn($r) => ($r['status'] ?? '') === 'FAIL'));
            $suiteErrors = count(array_filter($results['results'], fn($r) => ($r['status'] ?? '') === 'ERROR'));
            
            $totalTests += $suiteTotal;
            $totalPassed += $suitePassed;
            $totalFailed += $suiteFailed;
            $totalErrors += $suiteErrors;
        }
    }

    echo "Total Tests Executed: $totalTests\n";
    echo "Passed: $totalPassed\n";
    echo "Failed: $totalFailed\n";
    echo "Errors: $totalErrors\n";
    
    $successRate = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 2) : 0;
    echo "Success Rate: {$successRate}%\n";
    echo "Total Duration: {$duration}s\n\n";

    // Save comprehensive report
    $report = [
        'timestamp' => date('Y-m-d H:i:s'),
        'duration' => $duration,
        'summary' => [
            'total' => $totalTests,
            'passed' => $totalPassed,
            'failed' => $totalFailed,
            'errors' => $totalErrors,
            'success_rate' => $successRate,
        ],
        'suites' => $allResults,
    ];

    $reportFile = dirname(__FILE__) . '/qa-complete-report-' . date('Y-m-d-His') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    
    echo "Complete report saved to: $reportFile\n";
    echo "\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
}

// Run if executed directly
if (php_sapi_name() === 'cli' || (isset($_GET['run_all_qa']) && current_user_can('manage_options'))) {
    run_all_qa_tests();
}

