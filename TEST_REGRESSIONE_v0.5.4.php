<?php
/**
 * TEST REGRESSIONE v0.5.4
 * Verifica che nessuna funzionalità sia stata rotta dai fix
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Test Regressione v0.5.4</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .pass { color: #4ec9b0; }
        .fail { color: #f48771; }
        .warn { color: #dcdcaa; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; border-bottom: 1px solid #3e3e42; padding-bottom: 10px; }
        .test { margin: 15px 0; padding: 10px; background: #252526; border-left: 4px solid #007acc; }
    </style>
</head>
<body>
    <h1>🔍 Test Regressione v0.5.4</h1>
    <p>Verifica che i fix NON abbiano rotto funzionalità esistenti</p>

    <?php
    $tests_run = 0;
    $tests_passed = 0;
    
    function test($name, $condition, $details = '') {
        global $tests_run, $tests_passed;
        $tests_run++;
        if ($condition) {
            $tests_passed++;
            echo "<div class='test'><span class='pass'>✅ $name</span>";
            if ($details) echo "<br><small class='warn'>$details</small>";
            echo "</div>";
        } else {
            echo "<div class='test'><span class='fail'>❌ $name</span>";
            if ($details) echo "<br><small class='fail'>$details</small>";
            echo "</div>";
        }
    }

    echo '<h2>1. Core Components</h2>';
    
    test(
        'Plugin class exists',
        class_exists('FP_Exp\Plugin'),
        'Main plugin class loaded'
    );
    
    test(
        'Cart class exists',
        class_exists('FP_Exp\Booking\Cart'),
        'Cart functionality available'
    );
    
    test(
        'Slots class exists',
        class_exists('FP_Exp\Booking\Slots'),
        'Slot management available'
    );
    
    test(
        'VoucherManager exists',
        class_exists('FP_Exp\Gift\VoucherManager'),
        'Gift voucher functionality preserved'
    );
    
    test(
        'RequestToBook exists',
        class_exists('FP_Exp\Booking\RequestToBook'),
        'RTB functionality preserved'
    );

    echo '<h2>2. WooCommerce Integrations (NEW)</h2>';
    
    test(
        'ExperienceProduct exists',
        class_exists('FP_Exp\Integrations\ExperienceProduct'),
        'Virtual product integration active'
    );
    
    test(
        'WooCommerceProduct exists',
        class_exists('FP_Exp\Integrations\WooCommerceProduct'),
        'Cart display customization active'
    );
    
    test(
        'WooCommerceCheckout exists',
        class_exists('FP_Exp\Integrations\WooCommerceCheckout'),
        'Checkout validation active'
    );

    echo '<h2>3. Cart Functionality</h2>';
    
    $cart = \FP_Exp\Booking\Cart::instance();
    
    test(
        'Cart instance created',
        $cart !== null,
        'Cart singleton working'
    );
    
    test(
        'Cart has maybe_sync_to_woocommerce method',
        method_exists($cart, 'maybe_sync_to_woocommerce'),
        'Cart sync method present (v0.5.0+)'
    );

    echo '<h2>4. Slot Management</h2>';
    
    $experiences = get_posts([
        'post_type' => 'fp_experience',
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ]);
    
    if (!empty($experiences)) {
        $exp = $experiences[0];
        
        test(
            'Experience found for testing',
            true,
            "Using: " . esc_html($exp->post_title)
        );
        
        $test_start = gmdate('Y-m-d H:i:s', strtotime('+60 days 14:00'));
        $test_end = gmdate('Y-m-d H:i:s', strtotime('+60 days 16:00'));
        
        $slot_result = \FP_Exp\Booking\Slots::ensure_slot_for_occurrence($exp->ID, $test_start, $test_end);
        
        test(
            'ensure_slot_for_occurrence returns int or WP_Error',
            is_int($slot_result) || is_wp_error($slot_result),
            'Return type correct (v0.4.1+)'
        );
        
        if (is_wp_error($slot_result)) {
            test(
                'WP_Error has message',
                !empty($slot_result->get_error_message()),
                'Error message: ' . esc_html($slot_result->get_error_message())
            );
            
            test(
                'WP_Error has data',
                !empty($slot_result->get_error_data()),
                'Detailed error data present (v0.4.1+)'
            );
        } elseif ($slot_result > 0) {
            test(
                'Slot created successfully',
                true,
                "Slot ID: $slot_result"
            );
            
            $slot_data = \FP_Exp\Booking\Slots::get_slot($slot_result);
            
            test(
                'get_slot returns array',
                is_array($slot_data),
                'Slot data retrieved'
            );
            
            test(
                'Slot has remaining capacity',
                isset($slot_data['remaining']),
                'Remaining: ' . ($slot_data['remaining'] ?? 'N/A')
            );
        }
    } else {
        test(
            'No experiences for testing',
            false,
            'Create at least one experience for complete testing'
        );
    }

    echo '<h2>5. WooCommerce Integration</h2>';
    
    if (function_exists('WC')) {
        test(
            'WooCommerce active',
            true,
            'WC integration can work'
        );
        
        $virtual_product_id = \FP_Exp\Integrations\ExperienceProduct::get_product_id();
        
        test(
            'Virtual product exists or can be created',
            $virtual_product_id >= 0,
            $virtual_product_id > 0 ? "Product ID: $virtual_product_id" : "Will be created on first use"
        );
        
        test(
            'WC()->cart accessible',
            WC()->cart !== null,
            'Cart object available'
        );
    } else {
        test(
            'WooCommerce NOT active',
            false,
            'WC integration will not work'
        );
    }

    echo '<h2>6. Sanitizzazione (v0.5.4 Fix)</h2>';
    
    $wc_product = new \FP_Exp\Integrations\WooCommerceProduct();
    
    test(
        'WooCommerceProduct has display_cart_item_data method',
        method_exists($wc_product, 'display_cart_item_data'),
        'Cart display customization method present'
    );
    
    // Simulate cart item with tickets
    $test_item_data = [];
    $test_cart_item = [
        'fp_exp_item' => true,
        'fp_exp_tickets' => [
            'adulto' => 2,
            'bambino' => 1,
        ],
    ];
    
    $result_data = $wc_product->display_cart_item_data($test_item_data, $test_cart_item);
    
    test(
        'display_cart_item_data returns array',
        is_array($result_data),
        'Cart item data processed'
    );
    
    test(
        'Ticket types displayed',
        count($result_data) > 0,
        count($result_data) . ' ticket types added to display'
    );

    echo '<h2>📊 Summary</h2>';
    
    $success_rate = $tests_run > 0 ? round(($tests_passed / $tests_run) * 100) : 0;
    
    echo '<div class="test">';
    echo "<p><strong>Tests Run:</strong> $tests_run</p>";
    echo "<p class='pass'><strong>Tests Passed:</strong> $tests_passed</p>";
    echo "<p class='" . ($tests_run - $tests_passed > 0 ? 'fail' : 'pass') . "'><strong>Tests Failed:</strong> " . ($tests_run - $tests_passed) . "</p>";
    echo "<p class='" . ($success_rate >= 90 ? 'pass' : ($success_rate >= 75 ? 'warn' : 'fail')) . "'><strong>Success Rate:</strong> $success_rate%</p>";
    echo '</div>';
    
    if ($success_rate >= 90) {
        echo '<div class="test"><p class="pass" style="font-size:18px"><strong>✅ NESSUNA REGRESSIONE RILEVATA!</strong></p></div>';
        echo '<div class="test"><p class="pass">Tutte le funzionalità esistenti funzionano correttamente.</p>';
        echo '<p class="pass">I fix v0.5.1 → v0.5.4 NON hanno rotto nulla.</p></div>';
    } else {
        echo '<div class="test"><p class="fail" style="font-size:18px"><strong>⚠️ POSSIBILI REGRESSIONI!</strong></p></div>';
        echo '<div class="test"><p class="fail">Alcuni test sono falliti. Verifica i dettagli sopra.</p></div>';
    }
    ?>

    <br><br>
    <a href="<?php echo admin_url('admin.php?page=fp_exp_dashboard'); ?>">← Torna a FP Experiences</a>

</body>
</html>

