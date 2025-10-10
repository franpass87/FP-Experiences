<?php
/**
 * Script per cancellare cache e forzare il ricaricamento dei badge
 */

// Trova wp-load.php risalendo le directory
$wp_load = __DIR__;
for ($i = 0; $i < 5; $i++) {
    if (file_exists($wp_load . '/wp-load.php')) {
        require_once $wp_load . '/wp-load.php';
        break;
    }
    $wp_load = dirname($wp_load);
}

if (!defined('ABSPATH')) {
    echo "Errore: Impossibile trovare WordPress\n";
    exit(1);
}

echo "=== Pulizia cache badge di fiducia ===\n\n";

// Cancella transient
$deleted_transients = 0;
global $wpdb;
$transients = $wpdb->get_results(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
);

foreach ($transients as $transient) {
    delete_option($transient->option_name);
    $deleted_transients++;
}

echo "✓ Eliminati $deleted_transients transient\n";

// Cancella cache oggetti se disponibile
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "✓ Cache oggetti svuotata\n";
}

// Forza ricaricamento template
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "✓ Cache file system svuotata\n";
}

echo "\n✅ Cache pulita con successo!\n";
echo "Ricarica la pagina del frontend per vedere i badge.\n";
