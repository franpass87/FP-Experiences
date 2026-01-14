<?php
/**
 * Fix shortcode IDs in translated pages
 * 
 * This script updates [fp_exp_page id="X"] shortcodes in translated pages
 * to use the correct translated experience ID instead of the original Italian ID.
 * 
 * Run via: https://yoursite.com/wp-admin/admin.php?page=fp_exp_tools&action=fix_shortcode_ids
 */

namespace FP_Exp\Tools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fix shortcode IDs in translated pages
 */
function fix_translated_page_shortcodes(): array {
    global $wpdb;
    
    $results = [
        'checked' => 0,
        'updated' => 0,
        'errors' => [],
        'details' => [],
    ];
    
    // Check if WPML is active
    if (!function_exists('icl_object_id')) {
        $results['errors'][] = 'WPML non è attivo';
        return $results;
    }
    
    // Get all pages that contain fp_exp_page shortcode
    $pages_with_shortcode = $wpdb->get_results(
        "SELECT ID, post_title, post_content 
         FROM {$wpdb->posts} 
         WHERE post_type = 'page' 
         AND post_status = 'publish' 
         AND post_content LIKE '%[fp_exp_page%'"
    );
    
    if (empty($pages_with_shortcode)) {
        $results['details'][] = 'Nessuna pagina con shortcode fp_exp_page trovata';
        return $results;
    }
    
    foreach ($pages_with_shortcode as $page) {
        $results['checked']++;
        
        // Get the language of this page
        $page_language = apply_filters('wpml_post_language_details', null, $page->ID);
        $page_lang_code = $page_language['language_code'] ?? 'it';
        
        // Skip Italian pages (original)
        if ($page_lang_code === 'it') {
            $results['details'][] = sprintf(
                'Pagina "%s" (ID %d) - Italiano (originale), saltata',
                $page->post_title,
                $page->ID
            );
            continue;
        }
        
        // Find shortcode and extract experience ID
        if (preg_match('/\[fp_exp_page\s+id=["\']?(\d+)["\']?\]/', $page->post_content, $matches)) {
            $current_exp_id = (int) $matches[1];
            
            // Get the experience post
            $experience = get_post($current_exp_id);
            if (!$experience || $experience->post_type !== 'fp_experience') {
                $results['errors'][] = sprintf(
                    'Pagina "%s" (ID %d) - Esperienza ID %d non trovata',
                    $page->post_title,
                    $page->ID,
                    $current_exp_id
                );
                continue;
            }
            
            // Check if the experience is in the same language as the page
            $exp_language = apply_filters('wpml_post_language_details', null, $current_exp_id);
            $exp_lang_code = $exp_language['language_code'] ?? 'it';
            
            if ($exp_lang_code === $page_lang_code) {
                $results['details'][] = sprintf(
                    'Pagina "%s" (ID %d, %s) - Già usa l\'esperienza corretta (ID %d)',
                    $page->post_title,
                    $page->ID,
                    strtoupper($page_lang_code),
                    $current_exp_id
                );
                continue;
            }
            
            // Get the translated experience ID
            $translated_exp_id = apply_filters('wpml_object_id', $current_exp_id, 'fp_experience', false, $page_lang_code);
            
            if (!$translated_exp_id || $translated_exp_id === $current_exp_id) {
                $results['errors'][] = sprintf(
                    'Pagina "%s" (ID %d, %s) - Nessuna traduzione trovata per esperienza ID %d',
                    $page->post_title,
                    $page->ID,
                    strtoupper($page_lang_code),
                    $current_exp_id
                );
                continue;
            }
            
            // Update the shortcode with the correct ID
            $old_shortcode = $matches[0];
            $new_shortcode = sprintf('[fp_exp_page id="%d"]', $translated_exp_id);
            $new_content = str_replace($old_shortcode, $new_shortcode, $page->post_content);
            
            // Update the page
            $update_result = wp_update_post([
                'ID' => $page->ID,
                'post_content' => $new_content,
            ], true);
            
            if (is_wp_error($update_result)) {
                $results['errors'][] = sprintf(
                    'Pagina "%s" (ID %d) - Errore aggiornamento: %s',
                    $page->post_title,
                    $page->ID,
                    $update_result->get_error_message()
                );
            } else {
                $results['updated']++;
                $results['details'][] = sprintf(
                    '✅ Pagina "%s" (ID %d, %s) - Aggiornato ID da %d a %d',
                    $page->post_title,
                    $page->ID,
                    strtoupper($page_lang_code),
                    $current_exp_id,
                    $translated_exp_id
                );
            }
        }
    }
    
    return $results;
}

// Execute if called directly from admin
add_action('admin_init', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_GET['page']) && $_GET['page'] === 'fp_exp_tools' && isset($_GET['action']) && $_GET['action'] === 'fix_shortcode_ids') {
        $results = fix_translated_page_shortcodes();
        
        // Store results in transient for display
        set_transient('fp_exp_fix_shortcode_results', $results, 60);
        
        // Redirect back to tools page
        wp_redirect(admin_url('admin.php?page=fp_exp_tools&shortcode_fix=done'));
        exit;
    }
});
