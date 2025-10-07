<?php
/**
 * Debug script per verificare i dati del calendario
 * 
 * Uso: wp eval-file debug-calendar-data.php [experience_id]
 * Oppure: accedere a questo script via browser dopo averlo caricato nella root di WordPress
 */

// Se viene eseguito da WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    $experience_id = isset($args[0]) ? absint($args[0]) : 0;
    
    if ($experience_id <= 0) {
        WP_CLI::error('Specifica un ID esperienza valido. Uso: wp eval-file debug-calendar-data.php [experience_id]');
    }
    
    debug_calendar_data($experience_id);
} 
// Se viene eseguito via browser
else {
    // Carica WordPress se non è già caricato
    if (!defined('ABSPATH')) {
        require_once(__DIR__ . '/wp-load.php');
    }
    
    $experience_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    
    if ($experience_id <= 0) {
        // Trova la prima esperienza disponibile
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        
        if (empty($experiences)) {
            echo '<h1>Nessuna esperienza trovata</h1>';
            echo '<p>Crea prima un\'esperienza nel backend WordPress.</p>';
            exit;
        }
        
        $experience_id = $experiences[0];
    }
    
    echo '<style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .ok { color: #0a0; font-weight: bold; }
        .error { color: #d00; font-weight: bold; }
        .warning { color: #f80; font-weight: bold; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 0; }
        .meta-info { background: #e8f4f8; padding: 10px; margin: 10px 0; border-radius: 3px; }
    </style>';
    
    debug_calendar_data($experience_id);
}

function debug_calendar_data($experience_id) {
    global $wpdb;
    
    $post = get_post($experience_id);
    
    if (!$post || $post->post_type !== 'fp_experience') {
        echo '<h1 class="error">❌ Esperienza non trovata (ID: ' . $experience_id . ')</h1>';
        exit;
    }
    
    echo '<h1>📊 Debug Calendario - Esperienza #' . $experience_id . '</h1>';
    echo '<div class="meta-info">';
    echo '<strong>Titolo:</strong> ' . esc_html($post->post_title) . '<br>';
    echo '<strong>Stato:</strong> ' . $post->post_status . '<br>';
    echo '<strong>URL esperienza:</strong> <a href="' . get_permalink($experience_id) . '" target="_blank">' . get_permalink($experience_id) . '</a><br>';
    echo '<strong>URL admin:</strong> <a href="' . get_edit_post_link($experience_id) . '" target="_blank">Modifica esperienza</a>';
    echo '</div>';
    
    // 1. Verifica _fp_exp_recurrence
    echo '<div class="section">';
    echo '<h2>1️⃣ Ricorrenza (_fp_exp_recurrence)</h2>';
    $recurrence = get_post_meta($experience_id, '_fp_exp_recurrence', true);
    
    if (empty($recurrence) || !is_array($recurrence)) {
        echo '<p class="warning">⚠️ NESSUN DATO DI RICORRENZA TROVATO</p>';
        echo '<p>Vai nel backend e configura la ricorrenza nella tab "Calendario & Slot".</p>';
    } else {
        echo '<p class="ok">✅ Dati di ricorrenza trovati</p>';
        echo '<pre>' . print_r($recurrence, true) . '</pre>';
        
        // Verifica campi chiave
        echo '<h3>Analisi campi:</h3>';
        echo '<ul>';
        echo '<li><strong>Frequenza:</strong> ' . (isset($recurrence['frequency']) ? $recurrence['frequency'] : '<span class="error">NON IMPOSTATA</span>') . '</li>';
        echo '<li><strong>Data inizio:</strong> ' . (isset($recurrence['start_date']) && !empty($recurrence['start_date']) ? $recurrence['start_date'] : '<span class="warning">NON IMPOSTATA</span>') . '</li>';
        echo '<li><strong>Data fine:</strong> ' . (isset($recurrence['end_date']) && !empty($recurrence['end_date']) ? $recurrence['end_date'] : 'Nessuna (infinito)') . '</li>';
        echo '<li><strong>Giorni:</strong> ' . (isset($recurrence['days']) && is_array($recurrence['days']) ? implode(', ', $recurrence['days']) : '<span class="error">NESSUN GIORNO</span>') . '</li>';
        echo '<li><strong>Durata:</strong> ' . (isset($recurrence['duration']) ? $recurrence['duration'] . ' minuti' : '<span class="error">NON IMPOSTATA</span>') . '</li>';
        
        // Time sets
        if (isset($recurrence['time_sets']) && is_array($recurrence['time_sets'])) {
            $time_sets_count = count($recurrence['time_sets']);
            echo '<li><strong>Time Sets:</strong> ' . $time_sets_count . ' configurati</li>';
            
            if ($time_sets_count === 0) {
                echo '<li class="error">⚠️ NESSUN TIME SET CONFIGURATO - Il calendario sarà vuoto!</li>';
            } else {
                foreach ($recurrence['time_sets'] as $idx => $set) {
                    $times = isset($set['times']) && is_array($set['times']) ? $set['times'] : [];
                    $days = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
                    $capacity = isset($set['capacity']) ? $set['capacity'] : 0;
                    
                    echo '<ul>';
                    echo '<li>Set #' . ($idx + 1) . ':</li>';
                    echo '<ul>';
                    echo '<li><strong>Orari:</strong> ' . (count($times) > 0 ? implode(', ', $times) : '<span class="error">NESSUN ORARIO</span>') . '</li>';
                    echo '<li><strong>Giorni:</strong> ' . (count($days) > 0 ? implode(', ', $days) : 'Usa giorni globali') . '</li>';
                    echo '<li><strong>Capienza:</strong> ' . $capacity . '</li>';
                    echo '</ul>';
                    echo '</ul>';
                }
            }
        } else {
            echo '<li class="error">⚠️ NESSUN TIME SET TROVATO - Campo "time_sets" mancante!</li>';
        }
        
        echo '</ul>';
    }
    echo '</div>';
    
    // 2. Verifica _fp_exp_availability (formato legacy)
    echo '<div class="section">';
    echo '<h2>2️⃣ Availability (_fp_exp_availability) - Formato Legacy</h2>';
    $availability = get_post_meta($experience_id, '_fp_exp_availability', true);
    
    if (empty($availability) || !is_array($availability)) {
        echo '<p class="error">❌ NESSUN DATO DI AVAILABILITY TROVATO</p>';
        echo '<p class="warning">Questo è il problema! Il calendario frontend usa questo campo.</p>';
        echo '<p><strong>Possibili cause:</strong></p>';
        echo '<ul>';
        echo '<li>Il metodo sync_recurrence_to_availability() non è stato eseguito</li>';
        echo '<li>I time_sets nella ricorrenza sono vuoti</li>';
        echo '<li>La capienza è 0</li>';
        echo '</ul>';
    } else {
        echo '<p class="ok">✅ Dati di availability trovati</p>';
        echo '<pre>' . print_r($availability, true) . '</pre>';
        
        // Verifica campi chiave
        echo '<h3>Analisi campi:</h3>';
        echo '<ul>';
        echo '<li><strong>Frequenza:</strong> ' . (isset($availability['frequency']) ? $availability['frequency'] : '<span class="error">NON IMPOSTATA</span>') . '</li>';
        echo '<li><strong>Orari (times):</strong> ';
        if (isset($availability['times']) && is_array($availability['times']) && count($availability['times']) > 0) {
            echo '<span class="ok">' . count($availability['times']) . ' orari: ' . implode(', ', $availability['times']) . '</span>';
        } else {
            echo '<span class="error">NESSUN ORARIO - Il calendario sarà vuoto!</span>';
        }
        echo '</li>';
        
        echo '<li><strong>Giorni (days_of_week):</strong> ';
        if (isset($availability['days_of_week']) && is_array($availability['days_of_week']) && count($availability['days_of_week']) > 0) {
            echo '<span class="ok">' . implode(', ', $availability['days_of_week']) . '</span>';
        } else {
            echo '<span class="warning">NESSUN GIORNO (potrebbe essere daily o custom)</span>';
        }
        echo '</li>';
        
        echo '<li><strong>Capienza:</strong> ' . (isset($availability['slot_capacity']) ? $availability['slot_capacity'] : '<span class="error">0</span>') . '</li>';
        echo '<li><strong>Data inizio:</strong> ' . (isset($availability['start_date']) && !empty($availability['start_date']) ? $availability['start_date'] : '<span class="warning">NON IMPOSTATA</span>') . '</li>';
        echo '<li><strong>Data fine:</strong> ' . (isset($availability['end_date']) && !empty($availability['end_date']) ? $availability['end_date'] : 'Nessuna (infinito)') . '</li>';
        echo '<li><strong>Lead time:</strong> ' . (isset($availability['lead_time_hours']) ? $availability['lead_time_hours'] . ' ore' : '0 ore') . '</li>';
        echo '</ul>';
    }
    echo '</div>';
    
    // 3. Test generazione slot virtuali
    echo '<div class="section">';
    echo '<h2>3️⃣ Test Generazione Slot Virtuali</h2>';
    
    if (class_exists('FP_Exp\Booking\AvailabilityService')) {
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));
        
        echo '<p>Tentativo di generare slot virtuali per i prossimi 30 giorni...</p>';
        echo '<p><strong>Range:</strong> ' . $start_date . ' → ' . $end_date . '</p>';
        
        try {
            $slots = \FP_Exp\Booking\AvailabilityService::get_virtual_slots($experience_id, $start_date, $end_date);
            
            if (empty($slots)) {
                echo '<p class="error">❌ NESSUNO SLOT GENERATO</p>';
                echo '<p class="warning">Questo è il problema! Il calendario sarà vuoto perché AvailabilityService non genera slot.</p>';
                echo '<p><strong>Possibili cause:</strong></p>';
                echo '<ul>';
                echo '<li>Nessun orario configurato nel campo "times" di _fp_exp_availability</li>';
                echo '<li>La data di inizio è nel passato o troppo lontana nel futuro</li>';
                echo '<li>I giorni della settimana non corrispondono ai giorni nel periodo</li>';
                echo '<li>Il lead_time è troppo alto e filtra tutti gli slot</li>';
                echo '</ul>';
            } else {
                echo '<p class="ok">✅ ' . count($slots) . ' slot generati con successo!</p>';
                echo '<h3>Primi 5 slot:</h3>';
                echo '<pre>' . print_r(array_slice($slots, 0, 5), true) . '</pre>';
                
                // Raggruppa per giorno
                $days_with_slots = [];
                foreach ($slots as $slot) {
                    $date = substr($slot['start'], 0, 10);
                    if (!isset($days_with_slots[$date])) {
                        $days_with_slots[$date] = 0;
                    }
                    $days_with_slots[$date]++;
                }
                
                echo '<h3>Slot per giorno (primi 10 giorni):</h3>';
                echo '<ul>';
                $count = 0;
                foreach ($days_with_slots as $date => $num_slots) {
                    echo '<li><strong>' . $date . ':</strong> ' . $num_slots . ' slot</li>';
                    $count++;
                    if ($count >= 10) break;
                }
                echo '</ul>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ ERRORE durante la generazione degli slot:</p>';
            echo '<pre>' . $e->getMessage() . '</pre>';
        }
    } else {
        echo '<p class="error">❌ Classe AvailabilityService non trovata</p>';
    }
    echo '</div>';
    
    // 4. Verifica slot persistenti nel database
    echo '<div class="section">';
    echo '<h2>4️⃣ Slot Persistenti nel Database</h2>';
    
    $slots_table = $wpdb->prefix . 'fp_exp_slots';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$slots_table'") === $slots_table;
    
    if (!$table_exists) {
        echo '<p class="warning">⚠️ Tabella degli slot non trovata</p>';
    } else {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $slots_table WHERE experience_id = %d",
            $experience_id
        ));
        
        echo '<p>Slot persistenti nel database: <strong>' . $count . '</strong></p>';
        
        if ($count > 0) {
            $upcoming = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $slots_table WHERE experience_id = %d AND start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 5",
                $experience_id
            ), ARRAY_A);
            
            echo '<h3>Prossimi 5 slot nel database:</h3>';
            echo '<pre>' . print_r($upcoming, true) . '</pre>';
        } else {
            echo '<p class="warning">⚠️ Nessuno slot persistente trovato. Usa il pulsante "Genera/Rigenera Slot" nell\'admin se vuoi creare slot persistenti.</p>';
        }
    }
    echo '</div>';
    
    // 5. Raccomandazioni
    echo '<div class="section">';
    echo '<h2>5️⃣ Raccomandazioni</h2>';
    
    $has_recurrence = !empty($recurrence) && is_array($recurrence);
    $has_availability = !empty($availability) && is_array($availability);
    $has_times = $has_availability && isset($availability['times']) && count($availability['times']) > 0;
    $has_capacity = $has_availability && isset($availability['slot_capacity']) && $availability['slot_capacity'] > 0;
    
    if (!$has_recurrence) {
        echo '<p class="error">❌ PROBLEMA: Nessun dato di ricorrenza</p>';
        echo '<p><strong>Soluzione:</strong> Vai nell\'admin → Modifica esperienza → Tab "Calendario & Slot" → Configura la ricorrenza</p>';
    }
    
    if (!$has_availability) {
        echo '<p class="error">❌ PROBLEMA: Nessun dato di availability (formato legacy)</p>';
        echo '<p><strong>Soluzione:</strong> Ri-salva l\'esperienza nell\'admin per forzare la sincronizzazione</p>';
    }
    
    if ($has_availability && !$has_times) {
        echo '<p class="error">❌ PROBLEMA: Campo "times" vuoto in _fp_exp_availability</p>';
        echo '<p><strong>Possibili cause:</strong></p>';
        echo '<ul>';
        echo '<li>I time_sets nella ricorrenza sono vuoti</li>';
        echo '<li>Il metodo sync_recurrence_to_availability() non ha estratto correttamente gli orari</li>';
        echo '</ul>';
        echo '<p><strong>Soluzione:</strong> Verifica che nella tab "Calendario & Slot" → "Ricorrenza slot" → "Set di orari e capienza" ci siano orari configurati (es. 09:00, 14:00)</p>';
    }
    
    if ($has_availability && !$has_capacity) {
        echo '<p class="warning">⚠️ ATTENZIONE: Capienza è 0</p>';
        echo '<p><strong>Soluzione:</strong> Imposta una capienza maggiore di 0 nella sezione "Set di orari e capienza"</p>';
    }
    
    if ($has_recurrence && $has_availability && $has_times && $has_capacity) {
        echo '<p class="ok">✅ TUTTO OK! La configurazione sembra corretta.</p>';
        echo '<p>Se il calendario frontend è ancora vuoto:</p>';
        echo '<ul>';
        echo '<li>Svuota la cache del sito</li>';
        echo '<li>Svuota la cache del browser (Ctrl+Shift+R)</li>';
        echo '<li>Verifica che lo shortcode includa l\'ID corretto: <code>[fp_exp_calendar id="' . $experience_id . '"]</code></li>';
        echo '<li>Controlla la console del browser per errori JavaScript</li>';
        echo '</ul>';
    }
    
    echo '</div>';
    
    // Link utili
    echo '<div class="section">';
    echo '<h2>🔗 Link Utili</h2>';
    echo '<ul>';
    echo '<li><a href="' . get_edit_post_link($experience_id) . '" target="_blank">Modifica questa esperienza nell\'admin</a></li>';
    echo '<li><a href="' . get_permalink($experience_id) . '" target="_blank">Visualizza questa esperienza nel frontend</a></li>';
    
    // Trova una pagina con lo shortcode
    $pages_with_shortcode = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = 'publish' AND post_type IN ('page', 'post')",
        '%[fp_exp_calendar id="' . $experience_id . '"]%'
    ));
    
    if (!empty($pages_with_shortcode)) {
        echo '<li>Pagine con il calendario di questa esperienza:';
        echo '<ul>';
        foreach ($pages_with_shortcode as $page) {
            echo '<li><a href="' . get_permalink($page->ID) . '" target="_blank">' . esc_html($page->post_title) . '</a></li>';
        }
        echo '</ul>';
        echo '</li>';
    }
    
    echo '</ul>';
    echo '</div>';
}
