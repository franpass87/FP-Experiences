<?php
/**
 * Template per la sezione di aiuto dell'importer
 * 
 * @package FP_Exp
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="fp-exp-help-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 16px; margin: 20px 0; border-radius: 6px;">
    <h3 style="margin-top: 0; color: #2271b1;">💡 <?php esc_html_e('Suggerimenti Rapidi', 'fp-experiences'); ?></h3>
    
    <ul style="margin: 12px 0; line-height: 1.8;">
        <li><strong><?php esc_html_e('Prima volta?', 'fp-experiences'); ?></strong> <?php esc_html_e('Scarica il template, compila con 1-2 esperienze di test e prova l\'import.', 'fp-experiences'); ?></li>
        <li><strong><?php esc_html_e('Caratteri speciali:', 'fp-experiences'); ?></strong> <?php esc_html_e('Assicurati che il file sia salvato con codifica UTF-8 per evitare problemi con accenti e simboli.', 'fp-experiences'); ?></li>
        <li><strong><?php esc_html_e('Liste multiple:', 'fp-experiences'); ?></strong> <?php esc_html_e('Usa il carattere pipe ( | ) per separare elementi: Cultura|Arte|Storia', 'fp-experiences'); ?></li>
        <li><strong><?php esc_html_e('Dimensione file:', 'fp-experiences'); ?></strong> <?php esc_html_e('Per file grandi (100+ righe), considera di dividerli in batch più piccoli.', 'fp-experiences'); ?></li>
    </ul>
    
    <p style="margin-bottom: 0;">
        <strong><?php esc_html_e('Documentazione completa:', 'fp-experiences'); ?></strong> 
        <a href="<?php echo esc_url(admin_url('admin.php?page=fp_exp_help')); ?>"><?php esc_html_e('Guida & Shortcodes', 'fp-experiences'); ?></a>
    </p>
</div>

<div class="fp-exp-help-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; margin: 20px 0; border-radius: 6px;">
    <h3 style="margin-top: 0; color: #856404;">⚠️ <?php esc_html_e('Importante', 'fp-experiences'); ?></h3>
    
    <ul style="margin: 12px 0 0; line-height: 1.8;">
        <li><?php esc_html_e('L\'import crea sempre NUOVE esperienze, non aggiorna quelle esistenti.', 'fp-experiences'); ?></li>
        <li><?php esc_html_e('Immagini, calendari e biglietti devono essere configurati manualmente dopo l\'import.', 'fp-experiences'); ?></li>
        <li><?php esc_html_e('Fai sempre un backup prima di importare un grande numero di esperienze.', 'fp-experiences'); ?></li>
    </ul>
</div>
