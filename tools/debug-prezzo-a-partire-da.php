<?php
/**
 * Debug Prezzo "A Partire Da"
 * 
 * Carica questo file nella root del sito WordPress in produzione
 * URL: https://www.villadianella.it/debug-prezzo-a-partire-da.php?post_id=XXX
 * 
 * Sostituisci XXX con l'ID dell'esperienza (es: ?post_id=123)
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Access denied. You must be an administrator.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Prezzo "A Partire Da"</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        .info { margin: 15px 0; padding: 15px; border-left: 4px solid #0073aa; background: #f0f8ff; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; overflow-x: auto; margin: 10px 0; }
        pre { margin: 0; white-space: pre-wrap; }
        .warning { border-left-color: #ffb900; background: #fffbf0; }
        .error { border-left-color: #dc3232; background: #fff0f0; }
        .success { border-left-color: #46b450; background: #f0f8f0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .primary { background: #d4edda; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Prezzo "A Partire Da"</h1>
        <p><strong>Data:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        
        if ($post_id <= 0) {
            echo '<div class="info warning">';
            echo '<p>⚠️ <strong>Inserisci un Post ID nell\'URL:</strong></p>';
            echo '<p><code>?post_id=123</code></p>';
            echo '<p>Per trovare l\'ID dell\'esperienza, vai su WordPress Admin → Esperienze e guarda l\'URL quando modifichi un\'esperienza.</p>';
            echo '</div>';
        } else {
            $post = get_post($post_id);
            
            if (!$post || $post->post_type !== 'fp_experience') {
                echo '<div class="info error">';
                echo '<p>❌ <strong>Errore:</strong> Post ID ' . $post_id . ' non trovato o non è un\'esperienza.</p>';
                echo '</div>';
            } else {
                echo '<div class="info success">';
                echo '<h2>📋 Esperienza: ' . esc_html($post->post_title) . '</h2>';
                echo '<p><strong>Post ID:</strong> ' . $post_id . '</p>';
                echo '</div>';
                
                // Leggi i ticket dal database
                $tickets = get_post_meta($post_id, '_fp_ticket_types', true);
                
                echo '<div class="info">';
                echo '<h3>🎫 Ticket Types (Meta: _fp_ticket_types)</h3>';
                
                if (!is_array($tickets) || empty($tickets)) {
                    echo '<p class="warning">⚠️ Nessun ticket trovato nel database.</p>';
                } else {
                    echo '<table>';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>Index</th>';
                    echo '<th>Label</th>';
                    echo '<th>Price</th>';
                    echo '<th>use_as_price_from</th>';
                    echo '<th>Type</th>';
                    echo '<th>Is Primary?</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    $found_primary = false;
                    $primary_price = null;
                    $min_price = null;
                    $min_ticket = null;
                    
                    foreach ($tickets as $index => $ticket) {
                        if (!is_array($ticket)) {
                            continue;
                        }
                        
                        $label = $ticket['label'] ?? 'N/A';
                        $price = isset($ticket['price']) ? (float) $ticket['price'] : 0;
                        $use_as_price_from = $ticket['use_as_price_from'] ?? null;
                        $use_as_price_from_type = gettype($use_as_price_from);
                        $use_as_price_from_value = var_export($use_as_price_from, true);
                        
                        // Check if primary
                        $is_primary = false;
                        if (isset($ticket['use_as_price_from'])) {
                            if ($use_as_price_from === true 
                                || $use_as_price_from === '1' 
                                || $use_as_price_from === 1
                                || (is_string($use_as_price_from) && strtolower(trim($use_as_price_from)) === 'true')
                                || (is_string($use_as_price_from) && strtolower(trim($use_as_price_from)) === 'yes')
                            ) {
                                $is_primary = true;
                                $found_primary = true;
                                if ($price > 0 && $primary_price === null) {
                                    $primary_price = $price;
                                }
                            }
                        }
                        
                        // Track minimum price
                        if ($price > 0 && ($min_price === null || $price < $min_price)) {
                            $min_price = $price;
                            $min_ticket = $label;
                        }
                        
                        $row_class = $is_primary ? 'primary' : '';
                        
                        echo '<tr class="' . $row_class . '">';
                        echo '<td>' . $index . '</td>';
                        echo '<td><strong>' . esc_html($label) . '</strong></td>';
                        echo '<td>€' . number_format($price, 2, ',', '.') . '</td>';
                        echo '<td>' . esc_html($use_as_price_from_value) . '</td>';
                        echo '<td>' . esc_html($use_as_price_from_type) . '</td>';
                        echo '<td>' . ($is_primary ? '✅ SÌ' : '❌ NO') . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    
                    echo '<div class="info">';
                    echo '<h4>📊 Risultato Calcolo Prezzo</h4>';
                    
                    if ($found_primary && $primary_price !== null) {
                        echo '<p class="success">✅ <strong>Prezzo selezionato:</strong> €' . number_format($primary_price, 2, ',', '.') . ' (da ticket con flag use_as_price_from)</p>';
                    } else {
                        echo '<p class="warning">⚠️ <strong>Nessun ticket con flag use_as_price_from trovato!</strong></p>';
                        if ($min_price !== null) {
                            echo '<p>📉 <strong>Prezzo minimo usato:</strong> €' . number_format($min_price, 2, ',', '.') . ' (da ticket: ' . esc_html($min_ticket) . ')</p>';
                        }
                    }
                    echo '</div>';
                }
                
                // Verifica anche il meta _fp_exp_pricing
                $pricing_meta = get_post_meta($post_id, '_fp_exp_pricing', true);
                if (is_array($pricing_meta) && isset($pricing_meta['tickets'])) {
                    echo '<div class="info">';
                    echo '<h3>💰 Pricing Meta (_fp_exp_pricing)</h3>';
                    echo '<div class="code"><pre>' . esc_html(print_r($pricing_meta, true)) . '</pre></div>';
                    echo '</div>';
                }
                
                // Test calcolo prezzo usando la funzione del plugin
                echo '<div class="info">';
                echo '<h3>🧪 Test Calcolo Prezzo</h3>';
                
                // Simula il calcolo come fa ExperienceShortcode
                $calculated_price = null;
                if (is_array($tickets) && !empty($tickets)) {
                    // Cerca ticket con flag
                    foreach ($tickets as $ticket) {
                        if (!is_array($ticket) || !isset($ticket['price'])) {
                            continue;
                        }
                        
                        $use_as_price_from = $ticket['use_as_price_from'] ?? false;
                        $is_primary = false;
                        
                        if (isset($ticket['use_as_price_from'])) {
                            if ($use_as_price_from === true 
                                || $use_as_price_from === '1' 
                                || $use_as_price_from === 1
                                || (is_string($use_as_price_from) && strtolower(trim($use_as_price_from)) === 'true')
                                || (is_string($use_as_price_from) && strtolower(trim($use_as_price_from)) === 'yes')
                            ) {
                                $is_primary = true;
                            }
                        }
                        
                        if ($is_primary) {
                            $price = (float) $ticket['price'];
                            if ($price > 0) {
                                $calculated_price = $price;
                                break;
                            }
                        }
                    }
                    
                    // Fallback al minimo
                    if ($calculated_price === null) {
                        foreach ($tickets as $ticket) {
                            if (!is_array($ticket) || !isset($ticket['price'])) {
                                continue;
                            }
                            $price = (float) $ticket['price'];
                            if ($price > 0 && ($calculated_price === null || $price < $calculated_price)) {
                                $calculated_price = $price;
                            }
                        }
                    }
                }
                
                if ($calculated_price !== null) {
                    echo '<p class="success">✅ <strong>Prezzo calcolato:</strong> €' . number_format($calculated_price, 2, ',', '.') . '</p>';
                } else {
                    echo '<p class="error">❌ <strong>Nessun prezzo calcolato</strong></p>';
                }
                echo '</div>';
                
                echo '</div>';
            }
        }
        ?>
        
        <hr>
        <p><small><strong>Nota:</strong> Dopo aver risolto il problema, elimina questo file per motivi di sicurezza.</small></p>
    </div>
</body>
</html>
