<?php
/**
 * DIAGNOSI PLUGIN STATUS - Verifica se il plugin è caricato e le REST routes registrate
 * 
 * Uso: Carica questo file via FTP in produzione e apri via browser:
 * https://www.ilpoderedimarfisa.it/wp-content/plugins/FP-Experiences/DIAGNOSI_PLUGIN_STATUS.php
 */

// Load WordPress
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Diagnosi FP Experiences - Plugin Status</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; border-bottom: 1px solid #3e3e42; padding-bottom: 10px; }
        pre { background: #252526; padding: 10px; border-left: 3px solid #007acc; overflow-x: auto; }
        .check { margin: 10px 0; padding: 10px; background: #252526; border-left: 3px solid #007acc; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #3e3e42; }
        th { background: #2d2d30; color: #4ec9b0; }
    </style>
</head>
<body>
    <h1>🔍 Diagnosi FP Experiences - Plugin Status</h1>
    <p class="info">Timestamp: <?php echo date('Y-m-d H:i:s'); ?> | PHP: <?php echo PHP_VERSION; ?> | WP: <?php echo get_bloginfo('version'); ?></p>

    <?php
    // CHECK 1: Plugin attivo?
    echo '<div class="check">';
    echo '<h2>1️⃣ Plugin Attivo?</h2>';
    
    $plugin_file = 'FP-Experiences/fp-experiences.php';
    $active_plugins = get_option('active_plugins', []);
    $is_active = in_array($plugin_file, $active_plugins, true);
    
    if ($is_active) {
        echo '<p class="success">✅ Plugin ATTIVO in Dashboard → Plugin</p>';
    } else {
        echo '<p class="error">❌ Plugin NON ATTIVO! Attivalo in Dashboard → Plugin</p>';
        echo '</div>';
        echo '</body></html>';
        exit;
    }
    echo '</div>';

    // CHECK 2: Classe principale caricata?
    echo '<div class="check">';
    echo '<h2>2️⃣ Classe Principale Caricata?</h2>';
    
    if (class_exists('FP_Exp\Plugin')) {
        echo '<p class="success">✅ Classe FP_Exp\\Plugin caricata</p>';
        
        try {
            $plugin = FP_Exp\Plugin::instance();
            echo '<p class="success">✅ Plugin::instance() funziona</p>';
        } catch (Throwable $e) {
            echo '<p class="error">❌ Errore in Plugin::instance(): ' . esc_html($e->getMessage()) . '</p>';
            echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
        }
    } else {
        echo '<p class="error">❌ Classe FP_Exp\\Plugin NON caricata! Fatal error durante bootstrap.</p>';
        echo '</div>';
        echo '</body></html>';
        exit;
    }
    echo '</div>';

    // CHECK 3: Boot error?
    echo '<div class="check">';
    echo '<h2>3️⃣ Boot Error?</h2>';
    
    $boot_error = get_option('fp_exp_boot_error', null);
    if ($boot_error) {
        echo '<p class="error">❌ ERRORE BOOT RILEVATO!</p>';
        echo '<pre>' . print_r($boot_error, true) . '</pre>';
    } else {
        echo '<p class="success">✅ Nessun boot error nel database</p>';
    }
    echo '</div>';

    // CHECK 4: REST Routes registrate?
    echo '<div class="check">';
    echo '<h2>4️⃣ REST Routes Registrate?</h2>';
    
    // Forza il trigger di rest_api_init per assicurarsi che le routes siano registrate
    do_action('rest_api_init');
    
    $rest_server = rest_get_server();
    $routes = $rest_server->get_routes();
    
    $fp_exp_routes = [];
    foreach ($routes as $route => $handlers) {
        if (strpos($route, '/fp-exp/') === 0) {
            $fp_exp_routes[$route] = $handlers;
        }
    }
    
    if (empty($fp_exp_routes)) {
        echo '<p class="error">❌ NESSUNA REST ROUTE REGISTRATA per fp-exp!</p>';
        echo '<p class="warning">💡 Possibili cause:</p>';
        echo '<ul>';
        echo '<li>RestRoutes::register_hooks() non è stato chiamato</li>';
        echo '<li>rest_api_init hook non è stato triggerato</li>';
        echo '<li>Errore silenzioso durante la registrazione</li>';
        echo '</ul>';
    } else {
        echo '<p class="success">✅ ' . count($fp_exp_routes) . ' REST routes registrate:</p>';
        echo '<table>';
        echo '<tr><th>Route</th><th>Methods</th></tr>';
        foreach ($fp_exp_routes as $route => $handlers) {
            $methods = [];
            foreach ($handlers as $handler) {
                if (isset($handler['methods'])) {
                    $methods = array_merge($methods, array_keys($handler['methods']));
                }
            }
            $methods = array_unique($methods);
            echo '<tr><td>' . esc_html($route) . '</td><td>' . esc_html(implode(', ', $methods)) . '</td></tr>';
        }
        echo '</table>';
        
        // Verifica endpoint specifici
        echo '<h3>📍 Verifica Endpoint Specifici:</h3>';
        $endpoints_to_check = [
            '/fp-exp/v1/checkout' => 'POST',
            '/fp-exp/v1/availability' => 'GET',
            '/fp-exp/v1/gift/purchase' => 'POST',
        ];
        
        foreach ($endpoints_to_check as $endpoint => $method) {
            $exists = false;
            foreach ($fp_exp_routes as $route => $handlers) {
                if ($route === $endpoint || preg_match('#' . str_replace('/', '\/', $endpoint) . '#', $route)) {
                    foreach ($handlers as $handler) {
                        if (isset($handler['methods'][$method])) {
                            $exists = true;
                            break 2;
                        }
                    }
                }
            }
            
            if ($exists) {
                echo '<p class="success">✅ ' . esc_html($endpoint) . ' [' . esc_html($method) . '] registrato</p>';
            } else {
                echo '<p class="error">❌ ' . esc_html($endpoint) . ' [' . esc_html($method) . '] NON registrato</p>';
            }
        }
    }
    echo '</div>';

    // CHECK 5: File chiave esistenti?
    echo '<div class="check">';
    echo '<h2>5️⃣ File Chiave Esistenti?</h2>';
    
    $plugin_dir = WP_PLUGIN_DIR . '/FP-Experiences/';
    $key_files = [
        'fp-experiences.php',
        'src/Plugin.php',
        'src/Api/RestRoutes.php',
        'src/Booking/Checkout.php',
        'src/Integrations/PerformanceIntegration.php',
    ];
    
    echo '<table>';
    echo '<tr><th>File</th><th>Esiste?</th><th>Data Modifica</th></tr>';
    foreach ($key_files as $file) {
        $full_path = $plugin_dir . $file;
        $exists = file_exists($full_path);
        if ($exists) {
            $mtime = filemtime($full_path);
            echo '<tr><td>' . esc_html($file) . '</td><td class="success">✅</td><td>' . date('Y-m-d H:i:s', $mtime) . '</td></tr>';
        } else {
            echo '<tr><td>' . esc_html($file) . '</td><td class="error">❌</td><td>-</td></tr>';
        }
    }
    echo '</table>';
    echo '</div>';

    // CHECK 6: Test chiamata REST
    echo '<div class="check">';
    echo '<h2>6️⃣ Test Chiamata REST (Checkout Endpoint)</h2>';
    
    $test_url = home_url('/wp-json/fp-exp/v1/checkout');
    echo '<p class="info">URL test: ' . esc_html($test_url) . '</p>';
    
    $response = wp_remote_post($test_url, [
        'method' => 'POST',
        'body' => json_encode([]),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout' => 5,
    ]);
    
    if (is_wp_error($response)) {
        echo '<p class="error">❌ Errore chiamata: ' . esc_html($response->get_error_message()) . '</p>';
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code === 404) {
            echo '<p class="error">❌ 404 Not Found - L\'endpoint NON è accessibile!</p>';
        } elseif ($code === 403 || $code === 401) {
            echo '<p class="warning">⚠️ ' . $code . ' - Endpoint accessibile ma permessi negati (NORMALE per POST senza nonce)</p>';
            echo '<pre>' . esc_html(substr($body, 0, 500)) . '</pre>';
        } elseif ($code === 400 || $code === 500) {
            echo '<p class="success">✅ ' . $code . ' - Endpoint ACCESSIBILE (errore logico, non routing)</p>';
            echo '<pre>' . esc_html(substr($body, 0, 500)) . '</pre>';
        } else {
            echo '<p class="success">✅ Status ' . $code . ' - Endpoint ACCESSIBILE</p>';
            echo '<pre>' . esc_html(substr($body, 0, 500)) . '</pre>';
        }
    }
    echo '</div>';

    // CHECK 7: OpCache
    echo '<div class="check">';
    echo '<h2>7️⃣ OpCache Status</h2>';
    
    if (function_exists('opcache_get_status')) {
        $status = opcache_get_status();
        if ($status) {
            echo '<p class="info">OpCache: ATTIVO</p>';
            echo '<p class="warning">💡 Se hai modificato i file, SVUOTA OPCACHE!</p>';
        } else {
            echo '<p class="info">OpCache: Configurato ma non attivo</p>';
        }
    } else {
        echo '<p class="info">OpCache: Non disponibile</p>';
    }
    echo '</div>';

    // Riepilogo
    echo '<div class="check">';
    echo '<h2>📋 Riepilogo</h2>';
    echo '<p class="info">Se vedi "❌ NESSUNA REST ROUTE REGISTRATA", il problema è nel bootstrap del plugin.</p>';
    echo '<p class="info">Controlla Dashboard → Plugin per vedere se ci sono errori o notice.</p>';
    echo '<p class="info">Se le routes sono registrate ma l\'endpoint dà 404, potrebbe essere un problema di cache (OpCache, plugin cache, CDN).</p>';
    echo '</div>';
    ?>

</body>
</html>

