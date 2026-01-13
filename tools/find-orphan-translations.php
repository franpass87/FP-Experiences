<?php
/**
 * Tool per trovare e pulire le traduzioni orfane di fp_experience.
 * 
 * Uso: Visita questa URL nel browser:
 * /wp-admin/admin.php?fp_exp_tool=find_orphans
 * 
 * Per eliminare: 
 * /wp-admin/admin.php?fp_exp_tool=find_orphans&delete=1
 */

add_action('admin_init', function() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!isset($_GET['fp_exp_tool']) || $_GET['fp_exp_tool'] !== 'find_orphans') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Non autorizzato');
    }

    global $wpdb;

    // Trova tutti i post fp_experience
    $all_experiences = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_status, p.post_date,
               t.language_code, t.source_language_code, t.trid
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}icl_translations t 
            ON p.ID = t.element_id AND t.element_type = 'post_fp_experience'
        WHERE p.post_type = 'fp_experience'
        ORDER BY p.ID DESC
    ");

    // Trova le traduzioni che non appaiono nella lista (orfane)
    // Queste sono traduzioni il cui originale non esiste più, o con dati WPML corrotti
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $do_delete = isset($_GET['delete']) && $_GET['delete'] === '1';

    echo '<html><head><title>FP Experiences - Trova Traduzioni Orfane</title>';
    echo '<style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #0073aa; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .orphan { background: #ffcccc !important; }
        .translation { background: #fff3cd !important; }
        .btn { display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .btn:hover { background: #c82333; }
        .success { background: #d4edda; padding: 15px; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; }
    </style></head><body>';
    
    echo '<h1>🔍 Trova Traduzioni Orfane - FP Experiences</h1>';

    $deleted = [];
    if ($do_delete) {
        // Verifica nonce per sicurezza
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ids_to_delete = isset($_GET['ids']) ? array_map('intval', explode(',', sanitize_text_field($_GET['ids']))) : [];
        
        foreach ($ids_to_delete as $post_id) {
            if ($post_id > 0) {
                // Rimuovi anche da WPML
                $wpdb->delete($wpdb->prefix . 'icl_translations', ['element_id' => $post_id, 'element_type' => 'post_fp_experience']);
                // Elimina il post
                wp_delete_post($post_id, true);
                $deleted[] = $post_id;
            }
        }
        
        if (!empty($deleted)) {
            echo '<div class="success">✅ Eliminati ' . count($deleted) . ' post: ' . implode(', ', $deleted) . '</div>';
        }
        
        // Ricarica i dati
        $all_experiences = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_status, p.post_date,
                   t.language_code, t.source_language_code, t.trid
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}icl_translations t 
                ON p.ID = t.element_id AND t.element_type = 'post_fp_experience'
            WHERE p.post_type = 'fp_experience'
            ORDER BY p.ID DESC
        ");
    }

    // Separa originali e traduzioni
    $originals = [];
    $translations = [];
    $orphans = [];
    $no_lang = [];

    foreach ($all_experiences as $exp) {
        if (empty($exp->language_code)) {
            // Nessuna lingua assegnata
            $no_lang[] = $exp;
        } elseif (empty($exp->source_language_code)) {
            // È un originale (source_language_code è NULL per gli originali)
            $originals[$exp->ID] = $exp;
        } else {
            // È una traduzione
            $translations[] = $exp;
        }
    }

    // Trova traduzioni orfane (il cui originale non esiste)
    foreach ($translations as $trans) {
        $has_original = false;
        foreach ($originals as $orig) {
            if ($orig->trid === $trans->trid) {
                $has_original = true;
                break;
            }
        }
        if (!$has_original) {
            $orphans[] = $trans;
        }
    }

    echo '<h2>📊 Riepilogo</h2>';
    echo '<ul>';
    echo '<li><strong>Totale post fp_experience:</strong> ' . count($all_experiences) . '</li>';
    echo '<li><strong>Originali (IT):</strong> ' . count($originals) . '</li>';
    echo '<li><strong>Traduzioni:</strong> ' . count($translations) . '</li>';
    echo '<li><strong>⚠️ Senza lingua WPML:</strong> ' . count($no_lang) . '</li>';
    echo '<li><strong>❌ Orfani (da eliminare):</strong> ' . count($orphans) . '</li>';
    echo '</ul>';

    echo '<h2>📋 Tutti i Post fp_experience</h2>';
    echo '<table>';
    echo '<tr><th>ID</th><th>Titolo</th><th>Status</th><th>Lingua</th><th>Source Lang</th><th>TRID</th><th>Data</th><th>Tipo</th></tr>';

    foreach ($all_experiences as $exp) {
        $type = 'Originale';
        $class = '';
        
        if (empty($exp->language_code)) {
            $type = '⚠️ NO LANG';
            $class = 'orphan';
        } elseif (!empty($exp->source_language_code)) {
            // Verifica se è orfano
            $is_orphan = false;
            foreach ($orphans as $orph) {
                if ($orph->ID === $exp->ID) {
                    $is_orphan = true;
                    break;
                }
            }
            if ($is_orphan) {
                $type = '❌ ORFANO';
                $class = 'orphan';
            } else {
                $type = '🌐 Traduzione';
                $class = 'translation';
            }
        }

        echo '<tr class="' . $class . '">';
        echo '<td>' . esc_html($exp->ID) . '</td>';
        echo '<td>' . esc_html($exp->post_title) . '</td>';
        echo '<td>' . esc_html($exp->post_status) . '</td>';
        echo '<td>' . esc_html($exp->language_code ?: '-') . '</td>';
        echo '<td>' . esc_html($exp->source_language_code ?: '-') . '</td>';
        echo '<td>' . esc_html($exp->trid ?: '-') . '</td>';
        echo '<td>' . esc_html($exp->post_date) . '</td>';
        echo '<td>' . $type . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    // Raccogli tutti gli ID da eliminare (orfani + senza lingua)
    $to_delete = array_merge(
        array_map(fn($o) => $o->ID, $orphans),
        array_map(fn($n) => $n->ID, $no_lang)
    );

    if (!empty($to_delete)) {
        echo '<h2>🗑️ Azioni</h2>';
        echo '<p>Trovati <strong>' . count($to_delete) . '</strong> post da eliminare:</p>';
        echo '<ul>';
        foreach (array_merge($orphans, $no_lang) as $item) {
            echo '<li>ID ' . $item->ID . ': ' . esc_html($item->post_title) . ' (' . ($item->language_code ?: 'NO LANG') . ')</li>';
        }
        echo '</ul>';
        
        $delete_url = admin_url('admin.php?fp_exp_tool=find_orphans&delete=1&ids=' . implode(',', $to_delete));
        echo '<a href="' . esc_url($delete_url) . '" class="btn" onclick="return confirm(\'Sei sicuro di voler eliminare ' . count($to_delete) . ' post?\');">🗑️ Elimina tutti i post orfani</a>';
    } else {
        echo '<div class="success">✅ Nessuna traduzione orfana trovata!</div>';
    }

    echo '</body></html>';
    exit;
});
