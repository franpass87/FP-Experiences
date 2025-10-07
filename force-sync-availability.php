<?php
/**
 * Script per forzare la ri-sincronizzazione dei dati di availability
 * per tutte le esperienze o per un'esperienza specifica.
 * 
 * Questo script è utile quando:
 * - Il calendario non mostra disponibilità
 * - I dati di _fp_exp_availability non sono sincronizzati
 * - Dopo aver applicato fix al codice
 * 
 * Uso:
 * 1. Via WP-CLI (raccomandato):
 *    wp eval-file force-sync-availability.php [experience_id]
 * 
 * 2. Via browser (solo in ambienti di sviluppo):
 *    https://tuosito.com/force-sync-availability.php?id=[experience_id]
 *    https://tuosito.com/force-sync-availability.php?all=1
 */

// Se viene eseguito da WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    $experience_id = isset($args[0]) ? absint($args[0]) : 0;
    
    if ($experience_id > 0) {
        force_sync_single_experience($experience_id);
    } else {
        force_sync_all_experiences();
    }
} 
// Se viene eseguito via browser
else {
    // Carica WordPress se non è già caricato
    if (!defined('ABSPATH')) {
        require_once(__DIR__ . '/wp-load.php');
    }
    
    // SICUREZZA: Verifica che sia un admin loggato
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Accesso negato. Devi essere un amministratore per eseguire questo script.');
    }
    
    echo '<style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
        .success { color: #0a0; font-weight: bold; }
        .error { color: #d00; font-weight: bold; }
        .warning { color: #f80; font-weight: bold; }
        .log { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
        h1 { color: #333; }
    </style>';
    
    echo '<h1>🔄 Force Sync Availability</h1>';
    
    $experience_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $all = isset($_GET['all']) && $_GET['all'] == '1';
    
    if ($experience_id > 0) {
        force_sync_single_experience($experience_id);
    } elseif ($all) {
        force_sync_all_experiences();
    } else {
        echo '<p>Uso:</p>';
        echo '<ul>';
        echo '<li><a href="?id=[ID]">Sincronizza una singola esperienza</a></li>';
        echo '<li><a href="?all=1">Sincronizza tutte le esperienze</a></li>';
        echo '</ul>';
        
        // Mostra lista di esperienze
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        if (!empty($experiences)) {
            echo '<h2>Esperienze disponibili:</h2>';
            echo '<ul>';
            foreach ($experiences as $exp) {
                echo '<li>';
                echo '<a href="?id=' . $exp->ID . '">' . esc_html($exp->post_title) . ' (ID: ' . $exp->ID . ')</a>';
                echo ' - <a href="' . get_edit_post_link($exp->ID) . '" target="_blank">Modifica</a>';
                echo ' - <a href="' . get_permalink($exp->ID) . '" target="_blank">Visualizza</a>';
                echo '</li>';
            }
            echo '</ul>';
        }
    }
}

/**
 * Forza la sincronizzazione per una singola esperienza
 */
function force_sync_single_experience($experience_id) {
    $experience_id = absint($experience_id);
    
    if ($experience_id <= 0) {
        output_error('ID esperienza non valido');
        return;
    }
    
    $post = get_post($experience_id);
    
    if (!$post || $post->post_type !== 'fp_experience') {
        output_error('Esperienza non trovata (ID: ' . $experience_id . ')');
        return;
    }
    
    output_log('Inizio sincronizzazione per: ' . $post->post_title . ' (ID: ' . $experience_id . ')');
    
    // Leggi i dati di ricorrenza
    $recurrence = get_post_meta($experience_id, '_fp_exp_recurrence', true);
    
    if (empty($recurrence) || !is_array($recurrence)) {
        output_warning('Nessun dato di ricorrenza trovato. L\'esperienza non ha una ricorrenza configurata.');
        
        // Cancella availability per coerenza
        delete_post_meta($experience_id, '_fp_exp_availability');
        output_log('_fp_exp_availability cancellato (nessuna ricorrenza configurata)');
        return;
    }
    
    output_log('Dati di ricorrenza trovati: ' . count($recurrence) . ' campi');
    
    // Leggi altri parametri necessari
    $slot_capacity = absint(get_post_meta($experience_id, '_fp_slot_capacity', true));
    $lead_time = absint(get_post_meta($experience_id, '_fp_lead_time_hours', true));
    $buffer_before = absint(get_post_meta($experience_id, '_fp_buffer_before_minutes', true));
    $buffer_after = absint(get_post_meta($experience_id, '_fp_buffer_after_minutes', true));
    
    output_log('Parametri: Capienza=' . $slot_capacity . ', Lead time=' . $lead_time . 'h, Buffer before=' . $buffer_before . 'm, Buffer after=' . $buffer_after . 'm');
    
    // Estrai orari e giorni dai time_sets (duplica la logica di sync_recurrence_to_availability)
    $all_times = [];
    $all_days = [];
    
    if (isset($recurrence['time_sets']) && is_array($recurrence['time_sets'])) {
        output_log('Time sets trovati: ' . count($recurrence['time_sets']));
        
        foreach ($recurrence['time_sets'] as $idx => $set) {
            if (!is_array($set)) {
                continue;
            }
            
            output_log('  Time set #' . ($idx + 1) . ':');
            
            // Raccogli gli orari
            if (isset($set['times']) && is_array($set['times'])) {
                foreach ($set['times'] as $time) {
                    $time_str = trim((string) $time);
                    if ($time_str !== '' && !in_array($time_str, $all_times, true)) {
                        $all_times[] = $time_str;
                        output_log('    Orario aggiunto: ' . $time_str);
                    }
                }
            }
            
            // Raccogli i giorni
            if (isset($set['days']) && is_array($set['days'])) {
                foreach ($set['days'] as $day) {
                    $day_str = trim((string) $day);
                    if ($day_str !== '' && !in_array($day_str, $all_days, true)) {
                        $all_days[] = $day_str;
                        output_log('    Giorno aggiunto: ' . $day_str);
                    }
                }
            }
        }
    } else {
        output_warning('Nessun time_sets trovato nella ricorrenza');
    }
    
    // Se non ci sono giorni nei time_sets, usa i giorni globali
    if (empty($all_days) && isset($recurrence['days']) && is_array($recurrence['days'])) {
        $all_days = $recurrence['days'];
        output_log('Usati giorni globali: ' . implode(', ', $all_days));
    }
    
    output_log('Totale orari estratti: ' . count($all_times) . ' (' . implode(', ', $all_times) . ')');
    output_log('Totale giorni estratti: ' . count($all_days) . ' (' . implode(', ', $all_days) . ')');
    
    // Determina frequenza
    $frequency = isset($recurrence['frequency']) ? (string) $recurrence['frequency'] : 'weekly';
    if (!in_array($frequency, ['daily', 'weekly', 'custom'], true)) {
        $frequency = 'weekly';
    }
    
    if ($frequency === 'specific') {
        $frequency = 'custom';
    }
    
    output_log('Frequenza: ' . $frequency);
    
    // Costruisci availability
    $availability = [
        'frequency' => $frequency,
        'times' => $all_times,
        'days_of_week' => $all_days,
        'custom_slots' => [],
        'slot_capacity' => $slot_capacity,
        'lead_time_hours' => $lead_time,
        'buffer_before_minutes' => $buffer_before,
        'buffer_after_minutes' => $buffer_after,
        'start_date' => isset($recurrence['start_date']) ? sanitize_text_field((string) $recurrence['start_date']) : '',
        'end_date' => isset($recurrence['end_date']) ? sanitize_text_field((string) $recurrence['end_date']) : '',
    ];
    
    // Pulisci campi non necessari
    if ($frequency !== 'weekly') {
        $availability['days_of_week'] = [];
    }
    
    if ($frequency === 'custom') {
        $availability['times'] = [];
    }
    
    // Salva
    if (!empty($availability['times']) || !empty($availability['custom_slots']) || $slot_capacity > 0) {
        update_post_meta($experience_id, '_fp_exp_availability', $availability);
        output_success('✅ Availability sincronizzato con successo!');
        
        // Test generazione slot
        output_log('Test generazione slot virtuali...');
        
        if (class_exists('FP_Exp\Booking\AvailabilityService')) {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+30 days'));
            
            try {
                $slots = \FP_Exp\Booking\AvailabilityService::get_virtual_slots($experience_id, $start_date, $end_date);
                
                if (empty($slots)) {
                    output_warning('⚠️ Nessuno slot generato. Verifica date, giorni e orari.');
                } else {
                    output_success('✅ ' . count($slots) . ' slot generati per i prossimi 30 giorni');
                    
                    // Mostra primi 3 slot
                    output_log('Primi 3 slot:');
                    foreach (array_slice($slots, 0, 3) as $slot) {
                        output_log('  - ' . $slot['start'] . ' → ' . $slot['end'] . ' (' . $slot['capacity_remaining'] . '/' . $slot['capacity_total'] . ' posti)');
                    }
                }
            } catch (Exception $e) {
                output_error('Errore durante la generazione degli slot: ' . $e->getMessage());
            }
        }
    } else {
        delete_post_meta($experience_id, '_fp_exp_availability');
        output_warning('⚠️ Nessun dato valido da sincronizzare. Availability cancellato.');
    }
    
    output_log('Sincronizzazione completata per esperienza ' . $experience_id);
}

/**
 * Forza la sincronizzazione per tutte le esperienze
 */
function force_sync_all_experiences() {
    output_log('Inizio sincronizzazione di tutte le esperienze...');
    
    $experiences = get_posts([
        'post_type' => 'fp_experience',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    
    if (empty($experiences)) {
        output_warning('Nessuna esperienza trovata');
        return;
    }
    
    output_log('Trovate ' . count($experiences) . ' esperienze da sincronizzare');
    
    $synced = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($experiences as $exp_id) {
        output_log('');
        output_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        try {
            force_sync_single_experience($exp_id);
            $synced++;
        } catch (Exception $e) {
            output_error('Errore durante la sincronizzazione dell\'esperienza ' . $exp_id . ': ' . $e->getMessage());
            $errors++;
        }
    }
    
    output_log('');
    output_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    output_success('✅ Sincronizzazione completata!');
    output_log('Totale esperienze: ' . count($experiences));
    output_log('Sincronizzate: ' . $synced);
    output_log('Errori: ' . $errors);
}

/**
 * Helper functions per output
 */
function output_log($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        echo '<div class="log">' . esc_html($message) . '</div>';
    }
}

function output_success($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::success($message);
    } else {
        echo '<div class="log success">' . esc_html($message) . '</div>';
    }
}

function output_warning($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::warning($message);
    } else {
        echo '<div class="log warning">' . esc_html($message) . '</div>';
    }
}

function output_error($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::error($message, false);
    } else {
        echo '<div class="log error">' . esc_html($message) . '</div>';
    }
}
