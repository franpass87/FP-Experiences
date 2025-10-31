<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;
use WP_Error;

use function absint;
use function add_action;
use function add_settings_error;
use function admin_url;
use function check_admin_referer;
use function delete_transient;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function get_settings_errors;
use function get_transient;
use function header;
use function is_wp_error;
use function preg_match;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function set_transient;
use function settings_errors;
use function wp_die;
use function wp_insert_post;
use function wp_kses_post;
use function wp_nonce_url;
use function wp_redirect;
use function wp_set_object_terms;
use function update_post_meta;

/**
 * Importer page for bulk importing experiences from CSV files.
 */
final class ImporterPage
{
    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_init', [$this, 'handle_download_template']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function handle_form_submission(): void
    {
        if (! isset($_POST['fp_exp_import_experiences']) || ! isset($_POST['_wpnonce'])) {
            return;
        }

        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per importare esperienze.', 'fp-experiences'));
        }

        check_admin_referer('fp_exp_import_experiences');

        if (empty($_FILES['csv_file']['tmp_name'])) {
            add_settings_error(
                'fp_exp_importer',
                'no_file',
                __('Nessun file caricato. Per favore seleziona un file CSV.', 'fp-experiences'),
                'error'
            );
            set_transient('fp_exp_settings_errors', get_settings_errors('fp_exp_importer'), 30);
            wp_redirect(admin_url('admin.php?page=fp_exp_importer'));
            exit;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $result = $this->process_csv_import($file);

        if (is_wp_error($result)) {
            add_settings_error(
                'fp_exp_importer',
                'import_error',
                $result->get_error_message(),
                'error'
            );
        } else {
            $message = sprintf(
                __('Import completato con successo! %d esperienze importate.', 'fp-experiences'),
                $result['imported']
            );
            
            if (! empty($result['skipped'])) {
                $message .= ' ' . sprintf(
                    __('%d righe saltate per errori (vedi log).', 'fp-experiences'),
                    $result['skipped']
                );
            }
            
            if (! empty($result['details'])) {
                $message .= '<br><strong>' . __('Dettagli:', 'fp-experiences') . '</strong> ';
                $message .= esc_html($result['details']);
            }
            
            add_settings_error(
                'fp_exp_importer',
                'import_success',
                $message,
                'success'
            );
        }

        set_transient('fp_exp_settings_errors', get_settings_errors('fp_exp_importer'), 30);
        wp_redirect(admin_url('admin.php?page=fp_exp_importer'));
        exit;
    }

    public function handle_download_template(): void
    {
        if (! isset($_GET['fp_exp_download_template']) || ! isset($_GET['_wpnonce'])) {
            return;
        }

        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per scaricare il template.', 'fp-experiences'));
        }

        check_admin_referer('fp_exp_download_template');

        $csv = $this->generate_template_csv();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fp-experiences-template.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo $csv;
        exit;
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if (! $screen || 'fp-exp-dashboard_page_fp_exp_importer' !== $screen->id) {
            return;
        }

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);
        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['wp-api-fetch', 'wp-i18n'],
            Helpers::asset_version($admin_js),
            true
        );

        wp_enqueue_script(
            'fp-exp-importer',
            FP_EXP_PLUGIN_URL . 'assets/js/importer.js',
            ['jquery'],
            Helpers::asset_version('assets/js/importer.js'),
            true
        );
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per accedere all\'importer di esperienze.', 'fp-experiences'));
        }

        $errors = get_transient('fp_exp_settings_errors');
        if ($errors) {
            delete_transient('fp_exp_settings_errors');
            foreach ($errors as $error) {
                add_settings_error(
                    $error['setting'],
                    $error['code'],
                    $error['message'],
                    $error['type']
                );
            }
        }

        echo '<div class="wrap fp-exp-importer-page">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Importer Esperienze', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Importer Esperienze', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Importa velocemente più esperienze utilizzando un file CSV. Scarica il template per iniziare.', 'fp-experiences') . '</p>';
        echo '</header>';

        settings_errors('fp_exp_importer');

        $this->render_guide();
        $this->render_import_form();

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_guide(): void
    {
        $download_url = wp_nonce_url(
            admin_url('admin.php?page=fp_exp_importer&fp_exp_download_template=1'),
            'fp_exp_download_template'
        );

        $example_csv_url = FP_EXP_PLUGIN_URL . 'templates/admin/csv-examples/esperienze-esempio.csv';

        echo '<div class="fp-exp-card" style="margin-bottom: 20px;">';
        echo '<h2>' . esc_html__('Come usare l\'importer', 'fp-experiences') . '</h2>';
        
        echo '<div class="fp-exp-guide-section">';
        echo '<h3>📋 ' . esc_html__('1. Scarica il Template', 'fp-experiences') . '</h3>';
        echo '<p>' . esc_html__('Inizia scaricando il file template CSV che contiene tutte le colonne necessarie con esempi.', 'fp-experiences') . '</p>';
        echo '<div style="display: flex; gap: 12px; flex-wrap: wrap;">';
        echo '<a href="' . esc_url($download_url) . '" class="button button-secondary">';
        echo '⬇️ ' . esc_html__('Scarica Template CSV', 'fp-experiences');
        echo '</a>';
        echo '<a href="' . esc_url($example_csv_url) . '" class="button button-secondary" download>';
        echo '📋 ' . esc_html__('Scarica Esempi Completi', 'fp-experiences');
        echo '</a>';
        echo '</div>';
        echo '</div>';

        echo '<div class="fp-exp-guide-section" style="margin-top: 20px;">';
        echo '<h3>✏️ ' . esc_html__('2. Compila il File', 'fp-experiences') . '</h3>';
        echo '<p>' . esc_html__('Apri il file CSV con Excel, Google Sheets o qualsiasi editor di fogli di calcolo e compila i dati delle tue esperienze.', 'fp-experiences') . '</p>';
        
        echo '<h4>' . esc_html__('Campi Obbligatori:', 'fp-experiences') . '</h4>';
        echo '<ul class="fp-exp-guide-list">';
        echo '<li><strong>title</strong>: ' . esc_html__('Il nome dell\'esperienza', 'fp-experiences') . '</li>';
        echo '<li><strong>status</strong>: ' . esc_html__('Stato della pubblicazione (publish, draft, pending)', 'fp-experiences') . '</li>';
        echo '</ul>';

        echo '<h4>' . esc_html__('Campi Opzionali:', 'fp-experiences') . '</h4>';
        echo '<ul class="fp-exp-guide-list">';
        echo '<li><strong>description</strong>: ' . esc_html__('Descrizione completa dell\'esperienza (supporta HTML)', 'fp-experiences') . '</li>';
        echo '<li><strong>excerpt</strong>: ' . esc_html__('Breve estratto', 'fp-experiences') . '</li>';
        echo '<li><strong>short_desc</strong>: ' . esc_html__('Descrizione breve per overview', 'fp-experiences') . '</li>';
        echo '<li><strong>duration_minutes</strong>: ' . esc_html__('Durata in minuti', 'fp-experiences') . '</li>';
        echo '<li><strong>base_price</strong>: ' . esc_html__('Prezzo base (es: 49.99)', 'fp-experiences') . '</li>';
        echo '<li><strong>min_party</strong>: ' . esc_html__('Numero minimo di partecipanti', 'fp-experiences') . '</li>';
        echo '<li><strong>capacity_slot</strong>: ' . esc_html__('Capacità massima per slot', 'fp-experiences') . '</li>';
        echo '<li><strong>age_min</strong>: ' . esc_html__('Età minima', 'fp-experiences') . '</li>';
        echo '<li><strong>age_max</strong>: ' . esc_html__('Età massima', 'fp-experiences') . '</li>';
        echo '<li><strong>meeting_point</strong>: ' . esc_html__('Punto d\'incontro (testo libero)', 'fp-experiences') . '</li>';
        echo '<li><strong>highlights</strong>: ' . esc_html__('Punti salienti, separati da pipe |', 'fp-experiences') . '</li>';
        echo '<li><strong>inclusions</strong>: ' . esc_html__('Cosa è incluso, separato da pipe |', 'fp-experiences') . '</li>';
        echo '<li><strong>exclusions</strong>: ' . esc_html__('Cosa NON è incluso, separato da pipe |', 'fp-experiences') . '</li>';
        echo '<li><strong>what_to_bring</strong>: ' . esc_html__('Cosa portare', 'fp-experiences') . '</li>';
        echo '<li><strong>notes</strong>: ' . esc_html__('Note importanti', 'fp-experiences') . '</li>';
        echo '<li><strong>policy_cancel</strong>: ' . esc_html__('Politica di cancellazione', 'fp-experiences') . '</li>';
        echo '<li><strong>themes</strong>: ' . esc_html__('Temi dell\'esperienza, separati da pipe |', 'fp-experiences') . '</li>';
        echo '<li><strong>languages</strong>: ' . esc_html__('Lingue disponibili, separate da pipe | (es: Italiano|English|Français)', 'fp-experiences') . '</li>';
        echo '<li><strong>family_friendly</strong>: ' . esc_html__('Adatto alle famiglie (yes/no)', 'fp-experiences') . '</li>';
        echo '</ul>';

        echo '<h4>' . esc_html__('Campi Calendario e Slot:', 'fp-experiences') . '</h4>';
        echo '<ul class="fp-exp-guide-list">';
        echo '<li><strong>recurrence_frequency</strong>: ' . esc_html__('Frequenza ricorrenza (daily, weekly, custom)', 'fp-experiences') . '</li>';
        echo '<li><strong>recurrence_times</strong>: ' . esc_html__('Orari degli slot, separati da pipe | (es: 09:00|14:00|16:00)', 'fp-experiences') . '</li>';
        echo '<li><strong>recurrence_days</strong>: ' . esc_html__('Giorni della settimana (solo per weekly), separati da pipe | (es: monday|wednesday|friday)', 'fp-experiences') . '</li>';
        echo '<li><strong>recurrence_start_date</strong>: ' . esc_html__('Data inizio ricorrenza nel formato YYYY-MM-DD (es: 2025-01-01)', 'fp-experiences') . '</li>';
        echo '<li><strong>recurrence_end_date</strong>: ' . esc_html__('Data fine ricorrenza nel formato YYYY-MM-DD (es: 2025-12-31)', 'fp-experiences') . '</li>';
        echo '<li><strong>buffer_before</strong>: ' . esc_html__('Buffer prima dello slot in minuti (es: 15)', 'fp-experiences') . '</li>';
        echo '<li><strong>buffer_after</strong>: ' . esc_html__('Buffer dopo lo slot in minuti (es: 15)', 'fp-experiences') . '</li>';
        echo '<li><strong>lead_time_hours</strong>: ' . esc_html__('Ore di preavviso minimo per prenotare (es: 24)', 'fp-experiences') . '</li>';
        echo '</ul>';

        echo '<div class="fp-exp-guide-tip" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 15px;">';
        echo '<strong>💡 ' . esc_html__('Suggerimento:', 'fp-experiences') . '</strong> ';
        echo esc_html__('Per campi multipli come highlights, inclusions, exclusions, themes, languages, recurrence_times e recurrence_days, usa il separatore pipe ( | ) senza spazi prima o dopo.', 'fp-experiences');
        echo '</div>';
        
        echo '<div class="fp-exp-guide-tip" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin-top: 15px;">';
        echo '<strong>📅 ' . esc_html__('Calendario e Slot:', 'fp-experiences') . '</strong> ';
        echo esc_html__('I campi di ricorrenza permettono di configurare quando l\'esperienza è disponibile. Se vuoi slot giornalieri, usa "daily". Per giorni specifici della settimana, usa "weekly" con recurrence_days. I buffer sono opzionali e utili per dare tempo di preparazione tra gli slot.', 'fp-experiences');
        echo '</div>';
        echo '</div>';

        echo '<div class="fp-exp-guide-section" style="margin-top: 20px;">';
        echo '<h3>⬆️ ' . esc_html__('3. Carica il File', 'fp-experiences') . '</h3>';
        echo '<p>' . esc_html__('Usa il form qui sotto per caricare il file CSV compilato. Il sistema importerà automaticamente tutte le esperienze valide.', 'fp-experiences') . '</p>';
        echo '</div>';

        echo '<div class="fp-exp-guide-section" style="margin-top: 20px;">';
        echo '<h3>⚠️ ' . esc_html__('Note Importanti', 'fp-experiences') . '</h3>';
        echo '<ul class="fp-exp-guide-list">';
        echo '<li>' . esc_html__('Il file deve essere in formato CSV (Comma-Separated Values)', 'fp-experiences') . '</li>';
        echo '<li>' . esc_html__('La codifica del file deve essere UTF-8', 'fp-experiences') . '</li>';
        echo '<li>' . esc_html__('La prima riga deve contenere i nomi delle colonne', 'fp-experiences') . '</li>';
        echo '<li>' . esc_html__('Righe con errori verranno saltate e registrate nel log', 'fp-experiences') . '</li>';
        echo '<li>' . esc_html__('L\'import NON sovrascrive esperienze esistenti, crea sempre nuove esperienze', 'fp-experiences') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';
    }

    private function render_import_form(): void
    {
        echo '<div class="fp-exp-card">';
        echo '<h2>' . esc_html__('Carica File CSV', 'fp-experiences') . '</h2>';
        
        echo '<form method="post" enctype="multipart/form-data" class="fp-exp-import-form">';
        wp_nonce_field('fp_exp_import_experiences');
        
        echo '<table class="form-table" role="presentation">';
        echo '<tbody>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="csv_file">' . esc_html__('File CSV', 'fp-experiences') . '</label></th>';
        echo '<td>';
        echo '<input type="file" name="csv_file" id="csv_file" accept=".csv" required class="regular-text" />';
        echo '<p class="description">' . esc_html__('Seleziona il file CSV delle esperienze da importare.', 'fp-experiences') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<button type="submit" name="fp_exp_import_experiences" class="button button-primary button-large">';
        echo '🚀 ' . esc_html__('Importa Esperienze', 'fp-experiences');
        echo '</button>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }

    /**
     * Generate template CSV with all columns and examples.
     */
    private function generate_template_csv(): string
    {
        $columns = [
            'title',
            'status',
            'description',
            'excerpt',
            'short_desc',
            'duration_minutes',
            'base_price',
            'min_party',
            'capacity_slot',
            'age_min',
            'age_max',
            'meeting_point',
            'highlights',
            'inclusions',
            'exclusions',
            'what_to_bring',
            'notes',
            'policy_cancel',
            'themes',
            'languages',
            'family_friendly',
            'recurrence_frequency',
            'recurrence_times',
            'recurrence_days',
            'recurrence_start_date',
            'recurrence_end_date',
            'buffer_before',
            'buffer_after',
            'lead_time_hours'
        ];

        $example_row = [
            'Tour della città storica',
            'publish',
            'Scopri i segreti della nostra bellissima città con una guida esperta. Visiteremo i monumenti più importanti e ti racconteremo storie affascinanti.',
            'Un tour imperdibile per scoprire la città',
            'Tour guidato di 2 ore nel centro storico',
            '120',
            '35.00',
            '2',
            '15',
            '8',
            '99',
            'Piazza Centrale, di fronte alla fontana',
            'Centro storico|Monumenti principali|Guida esperta|Storia affascinante',
            'Guida turistica|Biglietti d\'ingresso|Mappa della città',
            'Trasporto|Cibo e bevande|Mance',
            'Scarpe comode, acqua, macchina fotografica',
            'Il tour si tiene con qualsiasi condizione meteo. Si consiglia abbigliamento comodo.',
            'Cancellazione gratuita fino a 24 ore prima. Rimborso completo.',
            'Cultura|Storia|Arte',
            'Italiano|English|Español',
            'yes',
            'weekly',
            '09:00|14:00|16:00',
            'monday|wednesday|friday',
            '2025-01-01',
            '2025-12-31',
            '15',
            '15',
            '24'
        ];

        $rows = [$columns, $example_row];

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    /**
     * Process CSV import and create experiences.
     *
     * @param string $file_path Path to uploaded CSV file.
     * @return array<string, mixed>|WP_Error
     */
    private function process_csv_import(string $file_path)
    {
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_error', __('Impossibile leggere il file CSV.', 'fp-experiences'));
        }

        $headers = fgetcsv($handle);
        if ($headers === false || empty($headers)) {
            fclose($handle);
            return new WP_Error('invalid_csv', __('File CSV non valido o vuoto.', 'fp-experiences'));
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row_number = 1;
        $empty_rows = 0;
        $created_ids = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;

            if (empty($data) || (count($data) === 1 && empty($data[0]))) {
                $empty_rows++;
                continue; // Skip empty rows
            }

            $row_data = array_combine($headers, $data);
            if ($row_data === false) {
                $errors[] = sprintf(__('Riga %d: formato non valido', 'fp-experiences'), $row_number);
                $skipped++;
                continue;
            }

            $result = $this->import_single_experience($row_data, $row_number);
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    __('Riga %d (%s): %s', 'fp-experiences'),
                    $row_number,
                    !empty($row_data['title']) ? sanitize_text_field($row_data['title']) : 'senza titolo',
                    $result->get_error_message()
                );
                $skipped++;
            } else {
                $imported++;
                $created_ids[] = $result;
            }
        }

        fclose($handle);

        $total_rows = $row_number - 1; // Exclude header row
        $processed_rows = $total_rows - $empty_rows;

        // Generate details message
        $details = sprintf(
            __('Processate %d righe (%d vuote saltate). %d esperienze create, %d con errori.', 'fp-experiences'),
            $total_rows,
            $empty_rows,
            $imported,
            $skipped
        );

        $result = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'details' => $details,
            'created_ids' => $created_ids,
        ];

        if (! empty($errors)) {
            Helpers::log_debug('experience_import', 'Import completed with errors', [
                'total_rows' => $total_rows,
                'imported' => $imported,
                'skipped' => $skipped,
                'empty_rows' => $empty_rows,
                'errors' => $errors,
                'created_ids' => $created_ids,
            ]);
        } else {
            Helpers::log_debug('experience_import', 'Import completed successfully', [
                'total_rows' => $total_rows,
                'imported' => $imported,
                'created_ids' => $created_ids,
            ]);
        }

        // Record stats
        ImporterStats::record_import($result);

        return $result;
    }

    /**
     * Import a single experience from CSV row data.
     *
     * @param array<string, string> $data       Row data.
     * @param int                   $row_number Row number for error reporting.
     * @return int|WP_Error Post ID or error.
     */
    private function import_single_experience(array $data, int $row_number = 0)
    {
        // Validate required fields
        if (empty($data['title'])) {
            return new WP_Error('missing_title', __('Titolo mancante', 'fp-experiences'));
        }

        $status = ! empty($data['status']) ? sanitize_text_field($data['status']) : 'draft';
        $valid_statuses = ['publish', 'draft', 'pending', 'private'];
        if (! in_array($status, $valid_statuses, true)) {
            $status = 'draft';
        }

        // Create the post
        $post_data = [
            'post_title' => sanitize_text_field($data['title']),
            'post_type' => 'fp_experience',
            'post_status' => $status,
            'post_content' => ! empty($data['description']) ? wp_kses_post($data['description']) : '',
            'post_excerpt' => ! empty($data['excerpt']) ? sanitize_textarea_field($data['excerpt']) : '',
        ];

        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Update meta fields
        $this->update_experience_meta($post_id, $data);

        // Set taxonomies
        $this->set_experience_taxonomies($post_id, $data);

        return $post_id;
    }

    /**
     * Update experience metadata from CSV data.
     *
     * @param int                   $post_id Post ID.
     * @param array<string, string> $data    CSV row data.
     */
    private function update_experience_meta(int $post_id, array $data): void
    {
        $meta_map = [
            'short_desc' => '_fp_short_desc',
            'duration_minutes' => '_fp_duration_minutes',
            'base_price' => '_fp_base_price',
            'min_party' => '_fp_min_party',
            'capacity_slot' => '_fp_capacity_slot',
            'age_min' => '_fp_age_min',
            'age_max' => '_fp_age_max',
            'meeting_point' => '_fp_meeting_point',
            'what_to_bring' => '_fp_what_to_bring',
            'notes' => '_fp_notes',
            'policy_cancel' => '_fp_policy_cancel',
        ];

        foreach ($meta_map as $csv_key => $meta_key) {
            if (! empty($data[$csv_key])) {
                $value = $data[$csv_key];

                // Type casting for numeric fields
                if (in_array($meta_key, ['_fp_duration_minutes', '_fp_min_party', '_fp_capacity_slot', '_fp_age_min', '_fp_age_max'], true)) {
                    $value = (int) $value;
                } elseif ($meta_key === '_fp_base_price') {
                    $value = (float) $value;
                } else {
                    $value = sanitize_textarea_field($value);
                }

                update_post_meta($post_id, $meta_key, $value);
            }
        }

        // Array fields with pipe separator
        $array_fields = [
            'highlights' => '_fp_highlights',
            'inclusions' => '_fp_inclusions',
            'exclusions' => '_fp_exclusions',
        ];

        foreach ($array_fields as $csv_key => $meta_key) {
            if (! empty($data[$csv_key])) {
                $items = array_map('trim', explode('|', $data[$csv_key]));
                $items = array_filter($items);
                update_post_meta($post_id, $meta_key, array_values($items));
            }
        }

        // Languages field
        if (! empty($data['languages'])) {
            $languages = array_map('trim', explode('|', $data['languages']));
            $languages = array_filter($languages);
            update_post_meta($post_id, '_fp_languages', array_values($languages));
        }

        // Recurrence configuration
        $this->update_recurrence_meta($post_id, $data);

        // Availability configuration (buffer, lead time, capacity)
        $this->update_availability_meta($post_id, $data);
    }

    /**
     * Update recurrence metadata from CSV data.
     *
     * @param int                   $post_id Post ID.
     * @param array<string, string> $data    CSV row data.
     */
    private function update_recurrence_meta(int $post_id, array $data): void
    {
        $recurrence = [];

        // Frequency
        if (! empty($data['recurrence_frequency'])) {
            $frequency = sanitize_key($data['recurrence_frequency']);
            $valid_frequencies = ['daily', 'weekly', 'custom'];
            if (in_array($frequency, $valid_frequencies, true)) {
                $recurrence['frequency'] = $frequency;
            } else {
                $recurrence['frequency'] = 'weekly';
            }
        }

        // Times (time_slots format)
        if (! empty($data['recurrence_times'])) {
            $times = array_map('trim', explode('|', $data['recurrence_times']));
            $times = array_filter($times, function($time) {
                return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time);
            });
            
            if (! empty($times)) {
                $recurrence['time_slots'] = [];
                foreach ($times as $time) {
                    $recurrence['time_slots'][] = ['time' => $time];
                }
            }
        }

        // Days (for weekly frequency)
        if (! empty($data['recurrence_days'])) {
            $days = array_map(function($day) {
                return strtolower(trim($day));
            }, explode('|', $data['recurrence_days']));
            
            $valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $days = array_filter($days, function($day) use ($valid_days) {
                return in_array($day, $valid_days, true);
            });
            
            if (! empty($days)) {
                $recurrence['days'] = array_values($days);
            }
        }

        // Start date
        if (! empty($data['recurrence_start_date'])) {
            $start_date = sanitize_text_field($data['recurrence_start_date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
                $recurrence['start_date'] = $start_date;
            }
        }

        // End date
        if (! empty($data['recurrence_end_date'])) {
            $end_date = sanitize_text_field($data['recurrence_end_date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                $recurrence['end_date'] = $end_date;
            }
        }

        // Save recurrence only if we have meaningful data
        if (! empty($recurrence)) {
            update_post_meta($post_id, '_fp_exp_recurrence', $recurrence);
        }
    }

    /**
     * Update availability metadata from CSV data.
     *
     * @param int                   $post_id Post ID.
     * @param array<string, string> $data    CSV row data.
     */
    private function update_availability_meta(int $post_id, array $data): void
    {
        // Get existing availability to preserve any existing configuration
        $existing = get_post_meta($post_id, '_fp_exp_availability', true);
        $availability = is_array($existing) ? $existing : [];
        
        // Set defaults for a complete structure
        $defaults = [
            'frequency' => 'weekly',
            'times' => [],
            'days_of_week' => [],
            'custom_slots' => [],
            'slot_capacity' => 0,
            'lead_time_hours' => 0,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'start_date' => '',
            'end_date' => '',
        ];
        
        // Merge: defaults < existing < CSV data
        $availability = array_merge($defaults, $availability);

        // Update from CSV data - use isset() not empty() to allow 0 values
        if (isset($data['capacity_slot'])) {
            $availability['slot_capacity'] = absint($data['capacity_slot']);
        }

        if (isset($data['buffer_before'])) {
            $availability['buffer_before_minutes'] = absint($data['buffer_before']);
        }

        if (isset($data['buffer_after'])) {
            $availability['buffer_after_minutes'] = absint($data['buffer_after']);
        }

        if (isset($data['lead_time_hours'])) {
            $lead_time = absint($data['lead_time_hours']);
            $availability['lead_time_hours'] = $lead_time;
            // Also save as separate meta for backward compatibility
            update_post_meta($post_id, '_fp_lead_time_hours', $lead_time);
        }

        // Always save availability with complete structure
        update_post_meta($post_id, '_fp_exp_availability', $availability);
    }

    /**
     * Set experience taxonomies from CSV data.
     *
     * @param int                   $post_id Post ID.
     * @param array<string, string> $data    CSV row data.
     */
    private function set_experience_taxonomies(int $post_id, array $data): void
    {
    }
}
