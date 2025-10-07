<?php
/**
 * Script di sincronizzazione dati disponibilità
 * 
 * Converte i dati dal vecchio formato _fp_exp_availability al nuovo formato _fp_exp_recurrence
 * e verifica che tutte le esperienze abbiano una configurazione valida.
 * 
 * Uso: wp eval-file sync-availability-data.php
 * oppure: php -d display_errors=1 sync-availability-data.php (se caricato via web)
 */

// Bootstrap WordPress se necessario
if (!defined('ABSPATH')) {
    // Cerca wp-load.php
    $wp_load_paths = [
        __DIR__ . '/wp-load.php',
        __DIR__ . '/../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
    ];
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die("WordPress not found. Run this script from WordPress root or use WP-CLI.\n");
    }
}

echo "=== FP Experiences - Sync Availability Data ===\n\n";

// Query tutte le esperienze
$experiences = get_posts([
    'post_type' => 'fp_experience',
    'posts_per_page' => -1,
    'post_status' => ['publish', 'draft', 'private'],
    'fields' => 'ids',
]);

if (empty($experiences)) {
    echo "No experiences found.\n";
    exit(0);
}

echo sprintf("Found %d experiences to check.\n\n", count($experiences));

$stats = [
    'total' => count($experiences),
    'already_unified' => 0,
    'migrated' => 0,
    'has_legacy_only' => 0,
    'no_config' => 0,
    'errors' => [],
];

foreach ($experiences as $exp_id) {
    $title = get_the_title($exp_id);
    echo sprintf("Processing: [%d] %s\n", $exp_id, $title);
    
    // Controlla formato unificato
    $recurrence = get_post_meta($exp_id, '_fp_exp_recurrence', true);
    $has_unified = is_array($recurrence) && !empty($recurrence);
    
    // Controlla formato legacy
    $availability = get_post_meta($exp_id, '_fp_exp_availability', true);
    $has_legacy = is_array($availability) && !empty($availability);
    
    if ($has_unified) {
        echo "  ✓ Already has unified format (_fp_exp_recurrence)\n";
        
        // Verifica che ci siano time_sets configurati
        $time_sets = isset($recurrence['time_sets']) && is_array($recurrence['time_sets']) ? $recurrence['time_sets'] : [];
        
        if (empty($time_sets)) {
            echo "  ⚠ WARNING: No time_sets configured in unified format\n";
            
            // Se c'è il legacy, migriamo
            if ($has_legacy) {
                echo "  → Migrating from legacy format...\n";
                $migrated = migrate_to_unified($exp_id, $availability);
                if ($migrated) {
                    echo "  ✓ Migration successful\n";
                    $stats['migrated']++;
                } else {
                    echo "  ✗ Migration failed\n";
                    $stats['errors'][] = sprintf("Experience %d: migration failed", $exp_id);
                }
            }
        } else {
            $stats['already_unified']++;
            
            // Log info sui time_sets
            $total_times = 0;
            foreach ($time_sets as $set) {
                if (isset($set['times']) && is_array($set['times'])) {
                    $total_times += count($set['times']);
                }
            }
            echo sprintf("  → %d time_set(s), %d total time(s)\n", count($time_sets), $total_times);
        }
    } elseif ($has_legacy) {
        echo "  → Has legacy format only, migrating...\n";
        $migrated = migrate_to_unified($exp_id, $availability);
        if ($migrated) {
            echo "  ✓ Migration successful\n";
            $stats['migrated']++;
            $stats['has_legacy_only']++;
        } else {
            echo "  ✗ Migration failed\n";
            $stats['errors'][] = sprintf("Experience %d: migration failed", $exp_id);
        }
    } else {
        echo "  ⚠ No availability configuration found\n";
        $stats['no_config']++;
    }
    
    echo "\n";
}

echo "=== Summary ===\n";
echo sprintf("Total experiences: %d\n", $stats['total']);
echo sprintf("Already unified: %d\n", $stats['already_unified']);
echo sprintf("Migrated: %d\n", $stats['migrated']);
echo sprintf("Had legacy only: %d\n", $stats['has_legacy_only']);
echo sprintf("No config: %d\n", $stats['no_config']);

if (!empty($stats['errors'])) {
    echo "\n=== Errors ===\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\n✓ Sync completed.\n";

/**
 * Migra dal vecchio formato al nuovo formato unificato.
 */
function migrate_to_unified(int $exp_id, array $legacy): bool
{
    // Estrai campi dal legacy format
    $frequency = isset($legacy['frequency']) ? sanitize_key($legacy['frequency']) : 'weekly';
    $times = isset($legacy['times']) && is_array($legacy['times']) ? $legacy['times'] : [];
    $days = isset($legacy['days_of_week']) && is_array($legacy['days_of_week']) ? $legacy['days_of_week'] : [];
    $custom = isset($legacy['custom_slots']) && is_array($legacy['custom_slots']) ? $legacy['custom_slots'] : [];
    $capacity = isset($legacy['slot_capacity']) ? absint($legacy['slot_capacity']) : 0;
    $lead_time = isset($legacy['lead_time_hours']) ? absint($legacy['lead_time_hours']) : 0;
    $buffer_before = isset($legacy['buffer_before_minutes']) ? absint($legacy['buffer_before_minutes']) : 0;
    $buffer_after = isset($legacy['buffer_after_minutes']) ? absint($legacy['buffer_after_minutes']) : 0;
    $start_date = isset($legacy['start_date']) ? sanitize_text_field($legacy['start_date']) : '';
    $end_date = isset($legacy['end_date']) ? sanitize_text_field($legacy['end_date']) : '';
    
    // Normalizza giorni (lowercase)
    $days = array_map('strtolower', array_map('trim', $days));
    
    // Crea struttura unificata
    $unified = [
        'frequency' => $frequency,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'days' => $days,
        'time_sets' => [],
    ];
    
    // Converti times in time_sets
    if (!empty($times)) {
        $unified['time_sets'][] = [
            'times' => $times,
            'days' => $days,
            'capacity' => $capacity,
            'buffer_before' => $buffer_before,
            'buffer_after' => $buffer_after,
        ];
    }
    
    // Salva nel nuovo formato
    $saved = update_post_meta($exp_id, '_fp_exp_recurrence', $unified);
    
    // Salva anche lead_time separato
    if ($lead_time > 0) {
        update_post_meta($exp_id, '_fp_lead_time_hours', $lead_time);
    }
    
    return $saved !== false;
}
