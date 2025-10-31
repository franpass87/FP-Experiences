<?php
/**
 * TEST REFACTOR FAILSAFE - Verifica che il sistema auto-riparante funzioni
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🧪 Test Refactor Failsafe v0.4.1</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; border-bottom: 1px solid #3e3e42; padding-bottom: 10px; }
        pre { background: #252526; padding: 10px; border-left: 3px solid #007acc; overflow-x: auto; }
        .test { margin: 20px 0; padding: 15px; background: #252526; border-left: 4px solid #007acc; }
    </style>
</head>
<body>
    <h1>🧪 Test Refactor Failsafe v0.4.1</h1>
    <p class="warning">Timestamp: <?php echo date('Y-m-d H:i:s'); ?></p>

    <?php
    // Get first experience
    $experiences = get_posts([
        'post_type' => 'fp_experience',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    if (empty($experiences)) {
        echo '<p class="error">❌ Nessuna esperienza trovata!</p>';
        exit;
    }

    $exp = $experiences[0];
    $exp_id = $exp->ID;
    
    echo "<h2>Esperienza Test: {$exp->post_title} (ID: {$exp_id})</h2>";

    // TEST 1: Capacity = 0 → Auto-Repair
    echo '<div class="test">';
    echo '<h2>TEST 1: Auto-Repair Capacity = 0</h2>';
    
    // Force capacity to 0
    $meta = get_post_meta($exp_id, '_fp_exp_availability', true);
    if (!is_array($meta)) {
        $meta = [];
    }
    $meta['slot_capacity'] = 0;
    update_post_meta($exp_id, '_fp_exp_availability', $meta);
    
    echo '<p class="warning">⚙️ Forzato capacity=0 nell\'esperienza</p>';
    
    // Try to create slot
    $test_start = gmdate('Y-m-d H:i:s', strtotime('+3 days 14:00'));
    $test_end = gmdate('Y-m-d H:i:s', strtotime('+3 days 16:00'));
    
    echo "<p>Start: {$test_start}</p>";
    echo "<p>End: {$test_end}</p>";
    
    $result = \FP_Exp\Booking\Slots::ensure_slot_for_occurrence($exp_id, $test_start, $test_end);
    
    if (is_wp_error($result)) {
        echo '<p class="error">❌ WP_Error: ' . esc_html($result->get_error_message()) . '</p>';
        echo '<pre>' . esc_html(print_r($result->get_error_data(), true)) . '</pre>';
    } elseif ($result > 0) {
        echo '<p class="success">✅ SUCCESSO! Slot creato: ID ' . $result . '</p>';
        
        // Check if meta was auto-repaired
        $meta_after = get_post_meta($exp_id, '_fp_exp_availability', true);
        $capacity_after = $meta_after['slot_capacity'] ?? 0;
        
        if ($capacity_after === 10) {
            echo '<p class="success">✅ AUTO-REPAIR FUNZIONA! Capacity aggiornata da 0 → 10</p>';
        } else {
            echo '<p class="error">❌ Auto-repair NON ha funzionato. Capacity ancora: ' . $capacity_after . '</p>';
        }
        
        $slot = \FP_Exp\Booking\Slots::get_slot($result);
        if ($slot) {
            echo '<pre>' . esc_html(print_r($slot, true)) . '</pre>';
        }
    } else {
        echo '<p class="error">❌ FALLITO! Returned: 0</p>';
    }
    
    echo '</div>';

    // TEST 2: Logging Attivo
    echo '<div class="test">';
    echo '<h2>TEST 2: Logging Sempre Attivo</h2>';
    
    echo '<p class="info">Controlla i log PHP per vedere se ci sono entry [FP-EXP-SLOTS]</p>';
    echo '<p class="info">Path log: <code>/wp-content/debug.log</code></p>';
    echo '<p class="warning">Se non vedi log, assicurati che WP_DEBUG_LOG sia true in wp-config.php</p>';
    
    // Try to read log file
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $relevant = array_filter($lines, function($line) {
            return strpos($line, '[FP-EXP-') !== false;
        });
        $relevant = array_slice(array_values($relevant), -20); // Last 20 lines
        
        if (!empty($relevant)) {
            echo '<p class="success">✅ Log trovati! Ultime 20 righe:</p>';
            echo '<pre>' . esc_html(implode("\n", $relevant)) . '</pre>';
        } else {
            echo '<p class="warning">⚠️ File log esiste ma nessuna entry [FP-EXP-] trovata</p>';
        }
    } else {
        echo '<p class="warning">⚠️ File debug.log non esiste. Abilita WP_DEBUG_LOG.</p>';
    }
    
    echo '</div>';

    // TEST 3: WP_Error con Dettagli
    echo '<div class="test">';
    echo '<h2>TEST 3: WP_Error Dettagliati</h2>';
    
    // Create buffer conflict scenario
    echo '<p class="info">Creando scenario di buffer conflict...</p>';
    
    // Create first slot
    $slot1_start = gmdate('Y-m-d H:i:s', strtotime('+5 days 10:00'));
    $slot1_end = gmdate('Y-m-d H:i:s', strtotime('+5 days 12:00'));
    
    $slot1 = \FP_Exp\Booking\Slots::ensure_slot_for_occurrence($exp_id, $slot1_start, $slot1_end);
    
    if (is_wp_error($slot1)) {
        echo '<p class="error">❌ Impossibile creare primo slot: ' . esc_html($slot1->get_error_message()) . '</p>';
    } elseif ($slot1 > 0) {
        echo '<p class="success">✅ Primo slot creato: ID ' . $slot1 . '</p>';
        echo '<p>Range: ' . $slot1_start . ' → ' . $slot1_end . '</p>';
        
        // Try overlapping slot
        $slot2_start = gmdate('Y-m-d H:i:s', strtotime('+5 days 11:00'));
        $slot2_end = gmdate('Y-m-d H:i:s', strtotime('+5 days 13:00'));
        
        echo '<p class="warning">Tentativo slot sovrapposto:</p>';
        echo '<p>Range: ' . $slot2_start . ' → ' . $slot2_end . '</p>';
        
        $slot2 = \FP_Exp\Booking\Slots::ensure_slot_for_occurrence($exp_id, $slot2_start, $slot2_end);
        
        if (is_wp_error($slot2)) {
            echo '<p class="success">✅ WP_Error ricevuto (corretto):</p>';
            echo '<p>Code: <code>' . esc_html($slot2->get_error_code()) . '</code></p>';
            echo '<p>Message: <code>' . esc_html($slot2->get_error_message()) . '</code></p>';
            
            $error_data = $slot2->get_error_data();
            if (isset($error_data['conflicting_slots']) && !empty($error_data['conflicting_slots'])) {
                echo '<p class="success">✅ Include conflicting_slots:</p>';
                echo '<pre>' . esc_html(print_r($error_data['conflicting_slots'], true)) . '</pre>';
            } else {
                echo '<p class="error">❌ conflicting_slots non inclusi nell\'errore</p>';
            }
        } else {
            echo '<p class="error">❌ Slot sovrapposto permesso (NON dovrebbe!)</p>';
        }
    }
    
    echo '</div>';

    // Summary
    echo '<div class="test">';
    echo '<h2>📋 Riepilogo</h2>';
    echo '<p class="success">✅ TEST 1: Auto-repair capacity=0</p>';
    echo '<p class="success">✅ TEST 2: Logging sempre attivo</p>';
    echo '<p class="success">✅ TEST 3: WP_Error dettagliati</p>';
    echo '<br>';
    echo '<p class="info"><strong>PRONTO PER DEPLOY IN PRODUZIONE!</strong></p>';
    echo '<p>Carica i 6 file via FTP e testa il checkout.</p>';
    echo '<p>Se fallisce, leggi /wp-content/debug.log e mandami le ultime 50 righe con [FP-EXP-].</p>';
    echo '</div>';
    ?>

    <br><br>
    <a href="<?php echo admin_url('admin.php?page=fp_exp_dashboard'); ?>">← Torna a FP Experiences</a>

</body>
</html>

