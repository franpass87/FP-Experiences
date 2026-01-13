<?php
/**
 * Script per assegnare la lingua italiana alle esperienze esistenti senza lingua WPML
 * 
 * Esegui da WP-CLI: wp eval-file wp-content/plugins/FP-Experiences/tools/fix-wpml-language.php
 * Oppure accedi via browser: /wp-content/plugins/FP-Experiences/tools/fix-wpml-language.php
 */

// Load WordPress
$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!defined('ABSPATH') && file_exists($wp_load)) {
    require_once $wp_load;
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Check admin permissions
if (!current_user_can('manage_options') && php_sapi_name() !== 'cli') {
    die('Accesso negato. Devi essere amministratore.');
}

global $wpdb, $sitepress;

echo "<h1>Fix WPML Language for FP Experiences</h1>\n";

// Check if WPML is active
if (!defined('ICL_SITEPRESS_VERSION') || !$sitepress) {
    die('WPML non è attivo.');
}

// Get default language
$default_lang = $sitepress->get_default_language();
echo "<p>Lingua predefinita: <strong>$default_lang</strong></p>\n";

// Get all fp_experience posts without WPML language
$experiences = $wpdb->get_results("
    SELECT p.ID, p.post_title 
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->prefix}icl_translations t 
        ON p.ID = t.element_id 
        AND t.element_type = 'post_fp_experience'
    WHERE p.post_type = 'fp_experience'
    AND p.post_status IN ('publish', 'draft', 'pending', 'private')
    AND t.element_id IS NULL
");

echo "<p>Esperienze senza lingua assegnata: <strong>" . count($experiences) . "</strong></p>\n";

if (empty($experiences)) {
    echo "<p style='color:green'>✅ Tutte le esperienze hanno già una lingua assegnata!</p>\n";
    
    // Show current assignments
    $assigned = $wpdb->get_results("
        SELECT p.ID, p.post_title, t.language_code
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->prefix}icl_translations t 
            ON p.ID = t.element_id 
            AND t.element_type = 'post_fp_experience'
        WHERE p.post_type = 'fp_experience'
        AND p.post_status IN ('publish', 'draft', 'pending', 'private')
    ");
    
    echo "<h2>Esperienze con lingua assegnata:</h2>\n<ul>\n";
    foreach ($assigned as $exp) {
        echo "<li>{$exp->post_title} (ID: {$exp->ID}) - Lingua: {$exp->language_code}</li>\n";
    }
    echo "</ul>\n";
    exit;
}

echo "<h2>Assegnazione lingua in corso...</h2>\n<ul>\n";

$count = 0;
foreach ($experiences as $exp) {
    // Get a unique trid for this post
    $max_trid = $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");
    $new_trid = $max_trid + 1;
    
    // Insert language information
    $result = $wpdb->insert(
        $wpdb->prefix . 'icl_translations',
        [
            'element_type' => 'post_fp_experience',
            'element_id' => $exp->ID,
            'trid' => $new_trid,
            'language_code' => $default_lang,
            'source_language_code' => null,
        ],
        ['%s', '%d', '%d', '%s', '%s']
    );
    
    if ($result) {
        echo "<li style='color:green'>✅ {$exp->post_title} (ID: {$exp->ID}) - Assegnata lingua: {$default_lang}</li>\n";
        $count++;
    } else {
        echo "<li style='color:red'>❌ {$exp->post_title} (ID: {$exp->ID}) - Errore nell'assegnazione</li>\n";
    }
}

echo "</ul>\n";
echo "<p><strong>Completato! {$count} esperienze aggiornate.</strong></p>\n";
echo "<p><a href='" . admin_url('edit.php?post_type=fp_experience') . "'>Torna alla lista esperienze</a></p>\n";
