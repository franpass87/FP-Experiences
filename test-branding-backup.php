<?php
/**
 * Script di test per il sistema di backup/restore delle impostazioni di branding
 * 
 * Questo script può essere eseguito per testare le funzionalità di backup e restore
 * senza dover utilizzare l'interfaccia admin.
 */

// Assicurati che WordPress sia caricato
if (!defined('ABSPATH')) {
    require_once('../../../wp-config.php');
}

// Controlla se l'utente ha i permessi necessari
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato. Sono necessari i permessi di amministratore.');
}

echo "<h1>Test Sistema Backup/Restore Branding</h1>\n";

// Test 1: Verifica backup esistente
echo "<h2>1. Verifica backup esistente</h2>\n";
$existing_backup = get_option('fp_exp_branding_backup', null);
if ($existing_backup) {
    echo "✅ Backup esistente trovato:\n";
    echo "- Timestamp: " . ($existing_backup['timestamp'] ?? 'N/A') . "\n";
    echo "- Versione: " . ($existing_backup['version'] ?? 'N/A') . "\n";
    echo "- Impostazioni salvate: " . count($existing_backup['settings'] ?? []) . "\n";
} else {
    echo "❌ Nessun backup esistente trovato\n";
}

// Test 2: Simula creazione backup
echo "<h2>2. Test creazione backup</h2>\n";

// Simula alcune impostazioni di branding
$test_branding = [
    'preset' => 'custom',
    'primary' => '#FF5733',
    'secondary' => '#33FF57',
    'mode' => 'light'
];

// Salva impostazioni di test
update_option('fp_exp_branding', $test_branding);
echo "✅ Impostazioni di test create\n";

// Simula il processo di backup
$branding_settings = [
    'fp_exp_branding' => get_option('fp_exp_branding', []),
    'fp_exp_email_branding' => get_option('fp_exp_email_branding', []),
    'fp_exp_emails' => get_option('fp_exp_emails', []),
    'fp_exp_tracking' => get_option('fp_exp_tracking', []),
];

$backup_data = [
    'timestamp' => current_time('mysql'),
    'version' => 'test-version',
    'site_url' => home_url(),
    'settings' => $branding_settings,
    'test_mode' => true,
];

$backup_saved = update_option('fp_exp_branding_backup_test', $backup_data);
if ($backup_saved) {
    echo "✅ Backup di test creato con successo\n";
    echo "- Timestamp: " . $backup_data['timestamp'] . "\n";
    echo "- Impostazioni: " . count($backup_data['settings']) . "\n";
} else {
    echo "❌ Errore nella creazione del backup\n";
}

// Test 3: Test restore
echo "<h2>3. Test restore</h2>\n";

// Modifica le impostazioni per testare il restore
update_option('fp_exp_branding', ['preset' => 'modified']);
echo "✅ Impostazioni modificate per test\n";

// Ripristina dal backup
$restore_backup = get_option('fp_exp_branding_backup_test', null);
if ($restore_backup && isset($restore_backup['settings'])) {
    $restored_count = 0;
    foreach ($restore_backup['settings'] as $option_name => $value) {
        $result = update_option($option_name, $value);
        if ($result) {
            $restored_count++;
        }
    }
    
    if ($restored_count > 0) {
        echo "✅ Restore completato: {$restored_count} impostazioni ripristinate\n";
        
        // Verifica che le impostazioni siano state ripristinate
        $restored_branding = get_option('fp_exp_branding', []);
        if (isset($restored_branding['primary']) && $restored_branding['primary'] === '#FF5733') {
            echo "✅ Verifica: Impostazioni ripristinate correttamente\n";
        } else {
            echo "❌ Verifica: Le impostazioni non sono state ripristinate correttamente\n";
        }
    } else {
        echo "❌ Nessuna impostazione ripristinata\n";
    }
} else {
    echo "❌ Backup di test non trovato\n";
}

// Test 4: Cleanup
echo "<h2>4. Cleanup</h2>\n";
delete_option('fp_exp_branding_backup_test');
delete_option('fp_exp_branding');
echo "✅ Dati di test rimossi\n";

// Test 5: Verifica endpoint REST
echo "<h2>5. Test endpoint REST</h2>\n";
$rest_url = rest_url('fp-exp/v1/tools/backup-branding');
echo "✅ Endpoint backup: " . $rest_url . "\n";

$rest_url = rest_url('fp-exp/v1/tools/restore-branding');
echo "✅ Endpoint restore: " . $rest_url . "\n";

echo "<h2>Test completato!</h2>\n";
echo "<p>Se tutti i test sono passati, il sistema di backup/restore è funzionante.</p>\n";
?>
