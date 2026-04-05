<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;
use FP_Exp\Utils\LanguageHelper;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\SpecialRequestsOptions;

use function absint;
use function checked;
use function selected;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function esc_url;
use function get_terms;
use function is_wp_error;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function wp_get_post_terms;
use function wp_attachment_is_image;
use function wp_json_encode;

/**
 * Handler for Details tab in Experience Meta Box.
 * 
 * Handles general information, hero image, gallery, taxonomies, and languages.
 */
final class DetailsMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-details-panel';
        $short_desc = $data['short_desc'] ?? '';
        $duration_minutes = $data['duration_minutes'] ?? 0;
        $is_event = !empty($data['is_event']);
        $event_datetime = $data['event_datetime'] ?? '';
        $event_datetime_input = $this->format_event_datetime_for_input((string) $event_datetime);
        $event_ticket_sales_end = $data['event_ticket_sales_end'] ?? '';
        $event_ticket_sales_end_input = $this->format_event_datetime_for_input((string) $event_ticket_sales_end);
        $single_event_sr_mode = isset($data['single_event_special_requests_mode']) ? (string) $data['single_event_special_requests_mode'] : 'default';
        if (! in_array($single_event_sr_mode, ['default', 'notes_only', 'hidden'], true)) {
            $single_event_sr_mode = 'default';
        }
        $single_event_sr_title = isset($data['single_event_special_requests_title']) ? (string) $data['single_event_special_requests_title'] : '';
        $single_event_sr_notes_label = isset($data['single_event_special_requests_notes_label']) ? (string) $data['single_event_special_requests_notes_label'] : '';
        $single_event_sr_help = isset($data['single_event_special_requests_help']) ? (string) $data['single_event_special_requests_help'] : '';
        $sr_enabled_presets = isset($data['special_requests_enabled_presets']) && is_array($data['special_requests_enabled_presets'])
            ? array_values(array_filter(array_map(
                static fn ($v): string => sanitize_key((string) $v),
                $data['special_requests_enabled_presets']
            )))
            : SpecialRequestsOptions::PRESET_ORDER;
        $sr_custom_rows = isset($data['special_requests_custom_rows']) && is_array($data['special_requests_custom_rows'])
            ? $data['special_requests_custom_rows']
            : [];
        $sr_preset_catalog = SpecialRequestsOptions::preset_catalog();
        $sr_groups = [
            'food' => [],
            'access' => [],
            'celebration' => [],
        ];
        foreach (SpecialRequestsOptions::PRESET_ORDER as $sr_pid) {
            if (isset($sr_preset_catalog[$sr_pid])) {
                $g = $sr_preset_catalog[$sr_pid]['group'];
                if (isset($sr_groups[$g])) {
                    $sr_groups[$g][] = $sr_pid;
                }
            }
        }
        $hero_image = $data['hero_image'] ?? ['id' => 0, 'url' => '', 'width' => 0, 'height' => 0];
        $gallery = $data['gallery'] ?? ['items' => [], 'ids' => []];
        $language_details = $data['languages'] ?? [];
        $language_choices = $language_details['choices'] ?? [];
        $language_selected = $language_details['selected'] ?? [];
        $language_quick_options = $this->get_language_quick_options();
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-details"
            data-tab-panel="details"
        >
            <?php
            $this->render_metabox_section_open(
                esc_html__('Informazioni generali', 'fp-experiences'),
                'dashicons-info',
                'details-general'
            );
            ?>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-short-desc">
                        <?php esc_html_e('Descrizione breve', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-short-desc-help', esc_html__('Testo sintetico mostrato in anteprima e nei widget.', 'fp-experiences')); ?>
                    </label>
                    <textarea
                        id="fp-exp-short-desc"
                        name="fp_exp_details[short_desc]"
                        rows="3"
                        placeholder="<?php echo esc_attr__('Es. Visita guidata alla Galleria degli Uffizi', 'fp-experiences'); ?>"
                        aria-describedby="fp-exp-short-desc-help"
                    ><?php echo esc_textarea((string) $short_desc); ?></textarea>
                    <p class="fp-exp-field__description" id="fp-exp-short-desc-help"><?php esc_html_e('Suggerito massimo 160 caratteri.', 'fp-experiences'); ?></p>
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-duration">
                            <?php esc_html_e('Durata (minuti)', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-duration-help', esc_html__("Durata media dell'esperienza utilizzata anche nello schema.", 'fp-experiences')); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-duration"
                            name="fp_exp_details[duration_minutes]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $duration_minutes); ?>"
                            aria-describedby="fp-exp-duration-help"
                        />
                        <p class="fp-exp-field__description" id="fp-exp-duration-help"><?php esc_html_e('Inserisci solo numeri interi.', 'fp-experiences'); ?></p>
                    </div>
                    <div>
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Tipo esperienza', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-event-help', esc_html__('Se attivi questa opzione, l’esperienza diventa un evento con data/ora fissa e viene generato un solo slot.', 'fp-experiences')); ?>
                        </span>
                        <label class="fp-exp-field__checkbox">
                            <input
                                type="checkbox"
                                name="fp_exp_details[is_event]"
                                value="1"
                                <?php checked($is_event); ?>
                            />
                            <span><?php esc_html_e('Evento a data fissa', 'fp-experiences'); ?></span>
                        </label>
                        <label class="fp-exp-field__label" for="fp-exp-event-datetime">
                            <?php esc_html_e('Data e ora evento', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="datetime-local"
                            id="fp-exp-event-datetime"
                            name="fp_exp_details[event_datetime]"
                            value="<?php echo esc_attr((string) $event_datetime_input); ?>"
                            class="regular-text"
                            aria-describedby="fp-exp-event-help"
                        />
                        <p class="fp-exp-field__description" id="fp-exp-event-help"><?php esc_html_e('Seleziona data e ora di partenza dell’evento.', 'fp-experiences'); ?></p>
                        <label class="fp-exp-field__label" for="fp-exp-event-ticket-sales-end">
                            <?php esc_html_e('Fine vendite biglietti (opzionale)', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="datetime-local"
                            id="fp-exp-event-ticket-sales-end"
                            name="fp_exp_details[event_ticket_sales_end]"
                            value="<?php echo esc_attr((string) $event_ticket_sales_end_input); ?>"
                            class="regular-text"
                            aria-describedby="fp-exp-event-ticket-sales-end-help"
                        />
                        <p class="fp-exp-field__description" id="fp-exp-event-ticket-sales-end-help"><?php esc_html_e('Dopo questa data/ora le vendite si chiudono automaticamente; restano chiuse anche dopo l’inizio dell’evento. Vuoto = vendite possibili fino all’inizio dell’evento.', 'fp-experiences'); ?></p>
                    </div>
                </div>

                <div class="fp-exp-field fp-exp-field--languages-row">
                    <span class="fp-exp-field__label">
                        <?php esc_html_e('Lingue disponibili', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-language-badge-help', esc_html__("Seleziona le lingue parlate durante l'esperienza: verranno mostrate nei badge pubblici e nel widget di prenotazione.", 'fp-experiences')); ?>
                    </span>
                    <?php if (!empty($language_choices)) : ?>
                        <div class="fp-exp-language-choices" aria-describedby="fp-exp-language-badge-help">
                            <?php foreach ($language_choices as $choice) :
                                if (!is_array($choice)) {
                                    continue;
                                }

                                $term_id = isset($choice['id']) ? (int) $choice['id'] : 0;
                                $label = isset($choice['label']) ? (string) $choice['label'] : '';
                                $code = isset($choice['code']) ? (string) $choice['code'] : '';
                                $sprite_id = isset($choice['sprite']) ? (string) $choice['sprite'] : '';

                                if ($term_id <= 0 || '' === $label) {
                                    continue;
                                }
                                ?>
                                <label class="fp-exp-language-option">
                                    <input
                                        type="checkbox"
                                        name="fp_exp_details[languages][]"
                                        value="<?php echo esc_attr((string) $term_id); ?>"
                                        <?php checked(in_array($term_id, $language_selected, true)); ?>
                                    />
                                    <span class="fp-exp-language-option__content">
                                        <?php if ('' !== $sprite_id) : ?>
                                            <span class="fp-exp-language-option__flag" aria-hidden="true">
                                                <svg viewBox="0 0 24 16" focusable="false">
                                                    <use href="<?php echo esc_url(LanguageHelper::get_sprite_url() . '#' . $sprite_id); ?>"></use>
                                                </svg>
                                            </span>
                                        <?php else : ?>
                                            <span class="fp-exp-language-option__flag fp-exp-language-option__flag--fallback" aria-hidden="true">
                                                <?php echo esc_html($code !== '' ? $code : '--'); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="fp-exp-language-option__label"><?php echo esc_html($label); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Non hai ancora creato lingue personalizzate: puoi iniziare dalle opzioni rapide qui sotto oppure aggiungerle manualmente.', 'fp-experiences'); ?></p>
                        <div class="fp-exp-language-choices fp-exp-language-choices--quick" aria-describedby="fp-exp-language-badge-help">
                            <?php foreach ($language_quick_options as $quick_option) :
                                if (!is_array($quick_option)) {
                                    continue;
                                }
                                $quick_code = isset($quick_option['code']) ? sanitize_key((string) $quick_option['code']) : '';
                                $quick_label = isset($quick_option['label']) ? (string) $quick_option['label'] : '';
                                if ('' === $quick_code || '' === $quick_label) {
                                    continue;
                                }
                                $quick_sprite = LanguageHelper::get_sprite_id_for_code($quick_code);
                                ?>
                                <label class="fp-exp-language-option">
                                    <input
                                        type="checkbox"
                                        name="fp_exp_details[languages_quick][]"
                                        value="<?php echo esc_attr($quick_code . '|' . $quick_label); ?>"
                                    />
                                    <span class="fp-exp-language-option__content">
                                        <span class="fp-exp-language-option__flag" aria-hidden="true">
                                            <svg viewBox="0 0 24 16" focusable="false">
                                                <use href="<?php echo esc_url(LanguageHelper::get_sprite_url() . '#' . $quick_sprite); ?>"></use>
                                            </svg>
                                        </span>
                                        <span class="fp-exp-language-option__label"><?php echo esc_html($quick_label); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="fp-exp-taxonomy-manual">
                        <label class="fp-exp-taxonomy-manual__label" for="fp-exp-languages-manual"><?php esc_html_e('Aggiungi nuove lingue', 'fp-experiences'); ?></label>
                        <input
                            type="text"
                            id="fp-exp-languages-manual"
                            name="fp_exp_details[languages_manual]"
                            class="regular-text"
                            placeholder="<?php echo esc_attr__('Es. Italiano, English, Deutsch', 'fp-experiences'); ?>"
                            autocomplete="off"
                        />
                        <p class="fp-exp-field__description"><?php esc_html_e('Separa le voci con una virgola: verranno create come termini e selezionate automaticamente.', 'fp-experiences'); ?></p>
                    </div>
                    <p class="fp-exp-field__description" id="fp-exp-language-badge-help"><?php esc_html_e('Le lingue selezionate vengono mostrate nei badge pubblici, nel widget e nei filtri.', 'fp-experiences'); ?></p>
                </div>

            <?php $this->render_metabox_section_close(); ?>

            <?php
            $this->render_metabox_section_open(
                esc_html__('Widget — Richieste speciali', 'fp-experiences'),
                'dashicons-editor-ul',
                'details-widget-special-requests'
            );
            ?>
                <div class="fp-exp-sr-widget">
                    <div class="fp-exp-sr-widget__block">
                        <h4 class="fp-exp-field__subtitle"><?php esc_html_e('Comportamento dello step', 'fp-experiences'); ?></h4>
                        <div class="fp-exp-dms-fields-grid fp-exp-sr-widget__grid">
                            <div class="fp-exp-dms-field">
                                <label for="fp-exp-widget-sr-mode"><?php esc_html_e('Modalità nel widget', 'fp-experiences'); ?></label>
                                <select id="fp-exp-widget-sr-mode" name="fp_exp_details[single_event_special_requests_mode]">
                                    <option value="default" <?php selected($single_event_sr_mode, 'default', true); ?>><?php esc_html_e('Standard — checkbox e note', 'fp-experiences'); ?></option>
                                    <option value="notes_only" <?php selected($single_event_sr_mode, 'notes_only', true); ?>><?php esc_html_e('Solo campo note libere', 'fp-experiences'); ?></option>
                                    <option value="hidden" <?php selected($single_event_sr_mode, 'hidden', true); ?>><?php esc_html_e('Nascondi lo step', 'fp-experiences'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="fp-exp-metabox-alert fp-exp-metabox-alert--info fp-exp-sr-widget__note" role="note">
                            <span class="dashicons dashicons-info-outline fp-exp-metabox-alert__icon" aria-hidden="true"></span>
                            <div class="fp-exp-metabox-alert__body">
                                <p class="fp-exp-metabox-alert__text">
                                    <?php esc_html_e('Le opzioni valgono per il widget di prenotazione di questa esperienza (eventi a data fissa e ricorrenti). Se nascondi lo step, il riepilogo aggiorna automaticamente la numerazione.', 'fp-experiences'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="fp-exp-sr-widget__block">
                        <h4 class="fp-exp-field__subtitle"><?php esc_html_e('Testi nel widget', 'fp-experiences'); ?></h4>
                        <div class="fp-exp-dms-fields-grid fp-exp-sr-widget__grid">
                            <div class="fp-exp-dms-field">
                                <label for="fp-exp-widget-sr-title"><?php esc_html_e('Titolo dello step (opzionale)', 'fp-experiences'); ?></label>
                                <input
                                    type="text"
                                    class="regular-text"
                                    id="fp-exp-widget-sr-title"
                                    name="fp_exp_details[single_event_special_requests_title]"
                                    value="<?php echo esc_attr($single_event_sr_title); ?>"
                                    placeholder="<?php echo esc_attr__('Richieste speciali', 'fp-experiences'); ?>"
                                />
                            </div>
                            <div class="fp-exp-dms-field">
                                <label for="fp-exp-widget-sr-notes-label"><?php esc_html_e('Etichetta campo note (opzionale)', 'fp-experiences'); ?></label>
                                <input
                                    type="text"
                                    class="regular-text"
                                    id="fp-exp-widget-sr-notes-label"
                                    name="fp_exp_details[single_event_special_requests_notes_label]"
                                    value="<?php echo esc_attr($single_event_sr_notes_label); ?>"
                                    placeholder="<?php echo esc_attr__('Altre richieste o note', 'fp-experiences'); ?>"
                                />
                            </div>
                            <div class="fp-exp-dms-field fp-exp-sr-widget__field--full">
                                <label for="fp-exp-widget-sr-help"><?php esc_html_e('Testo di aiuto sotto le note (opzionale)', 'fp-experiences'); ?></label>
                                <textarea id="fp-exp-widget-sr-help" name="fp_exp_details[single_event_special_requests_help]" rows="3" class="large-text"><?php echo esc_textarea($single_event_sr_help); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="fp_exp_details[special_requests_items_touched]" value="1" />

                    <div class="fp-exp-sr-widget__block fp-exp-sr-widget__block--presets">
                        <h4 class="fp-exp-field__subtitle"><?php esc_html_e('Opzioni in checkbox (solo modalità «Standard»)', 'fp-experiences'); ?></h4>
                        <p class="fp-exp-dms-hint fp-exp-sr-widget__intro">
                            <?php esc_html_e('Se non modifichi nulla, restano tutte le voci predefinite. Deseleziona ciò che non serve e aggiungi righe personalizzate (etichetta obbligatoria; slug opzionale, generato dall’etichetta se vuoto).', 'fp-experiences'); ?>
                        </p>

                        <?php foreach ($sr_groups as $sr_gkey => $sr_pids) : ?>
                            <?php if (empty($sr_pids)) {
                                continue;
                            } ?>
                            <div class="fp-exp-sr-widget__preset-group" role="group" aria-label="<?php echo esc_attr(SpecialRequestsOptions::group_title($sr_gkey)); ?>">
                                <span class="fp-exp-sr-widget__group-title"><?php echo esc_html(SpecialRequestsOptions::group_title($sr_gkey)); ?></span>
                                <div class="fp-exp-sr-widget__checkbox-grid">
                                    <?php foreach ($sr_pids as $sr_pid) :
                                        $sr_plabel = $sr_preset_catalog[$sr_pid]['label'] ?? $sr_pid;
                                        ?>
                                        <label class="fp-exp-sr-widget__checkbox-card">
                                            <input
                                                type="checkbox"
                                                name="fp_exp_details[special_requests_enabled_presets][]"
                                                value="<?php echo esc_attr($sr_pid); ?>"
                                                <?php checked(in_array($sr_pid, $sr_enabled_presets, true)); ?>
                                            />
                                            <span class="fp-exp-sr-widget__checkbox-card-text"><?php echo esc_html($sr_plabel); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="fp-exp-sr-widget__preset-group fp-exp-sr-widget__preset-group--custom">
                            <span class="fp-exp-sr-widget__group-title"><?php echo esc_html(SpecialRequestsOptions::group_title('custom')); ?></span>
                            <p class="fp-exp-dms-hint"><?php esc_html_e('Slug tecnico (opzionale): solo lettere, numeri e trattini; usato nel dato inviato al carrello.', 'fp-experiences'); ?></p>
                            <?php
                            $sr_cr_index = 0;
                            foreach ($sr_custom_rows as $sr_cr) :
                                if (! is_array($sr_cr)) {
                                    continue;
                                }
                                $sr_cr_label = isset($sr_cr['label']) ? (string) $sr_cr['label'] : '';
                                $sr_cr_slug = isset($sr_cr['slug']) ? (string) $sr_cr['slug'] : '';
                                ?>
                                <div class="fp-exp-sr-widget__custom-row fp-exp-dms-fields-grid fp-exp-sr-widget__grid">
                                    <div class="fp-exp-dms-field">
                                        <label for="fp-exp-sr-custom-label-<?php echo esc_attr((string) $sr_cr_index); ?>"><?php esc_html_e('Etichetta', 'fp-experiences'); ?></label>
                                        <input
                                            type="text"
                                            class="regular-text"
                                            id="fp-exp-sr-custom-label-<?php echo esc_attr((string) $sr_cr_index); ?>"
                                            name="fp_exp_details[special_requests_custom_rows][<?php echo esc_attr((string) $sr_cr_index); ?>][label]"
                                            value="<?php echo esc_attr($sr_cr_label); ?>"
                                        />
                                    </div>
                                    <div class="fp-exp-dms-field">
                                        <label for="fp-exp-sr-custom-slug-<?php echo esc_attr((string) $sr_cr_index); ?>"><?php esc_html_e('Slug (opz.)', 'fp-experiences'); ?></label>
                                        <input
                                            type="text"
                                            class="regular-text"
                                            id="fp-exp-sr-custom-slug-<?php echo esc_attr((string) $sr_cr_index); ?>"
                                            name="fp_exp_details[special_requests_custom_rows][<?php echo esc_attr((string) $sr_cr_index); ?>][slug]"
                                            value="<?php echo esc_attr($sr_cr_slug); ?>"
                                            placeholder="<?php echo esc_attr__('auto', 'fp-experiences'); ?>"
                                        />
                                    </div>
                                </div>
                                <?php
                                ++$sr_cr_index;
                            endforeach;
                            ?>
                        </div>
                    </div>
                </div>
            <?php $this->render_metabox_section_close(); ?>

            <?php
            $this->render_metabox_section_open(
                esc_html__('Media e anteprima', 'fp-experiences'),
                'dashicons-format-gallery',
                'details-media'
            );
            ?>
                <?php $this->render_hero_image_field($hero_image); ?>
                <?php $this->render_gallery_field($gallery); ?>
                <?php $this->render_gallery_video_field($data); ?>
            <?php $this->render_metabox_section_close(); ?>

            <?php
            $this->render_metabox_section_open(
                esc_html__('Contenuto, categorie e fiducia', 'fp-experiences'),
                'dashicons-tag',
                'details-content'
            );
            ?>
                <div class="fp-exp-content-trust">
                    <?php $this->render_experience_badges_field($data, $post_id); ?>
                    <?php $this->render_taxonomy_fields($data, $post_id); ?>
                    <?php $this->render_trust_badges_field($data); ?>
                </div>
            <?php $this->render_metabox_section_close(); ?>

            <?php
            $this->render_metabox_section_open(
                esc_html__('Pubblicazione e partecipanti', 'fp-experiences'),
                'dashicons-groups',
                'details-publish'
            );
            ?>
                <?php $this->render_linked_page_field($data); ?>
                <?php $this->render_capacity_fields($data); ?>
                <?php $this->render_age_fields($data); ?>
                <?php $this->render_children_rules_field($data); ?>
            <?php $this->render_metabox_section_close(); ?>
        </section>
        <?php
    }

    /**
     * Render hero image field.
     */
    private function render_hero_image_field(array $hero_image): void
    {
        $hero_id = isset($hero_image['id']) ? (int) $hero_image['id'] : 0;
        $hero_url = isset($hero_image['url']) ? (string) $hero_image['url'] : '';
        $hero_width = isset($hero_image['width']) ? (int) $hero_image['width'] : 0;
        $hero_height = isset($hero_image['height']) ? (int) $hero_image['height'] : 0;
        ?>
        <div class="fp-exp-field">
            <span class="fp-exp-field__label">
                <?php esc_html_e('Immagine hero', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-hero-image-help', esc_html__('Seleziona l\'immagine principale mostrata come hero a tutta larghezza nella pagina esperienza.', 'fp-experiences')); ?>
            </span>
            <div class="fp-exp-cover-media" data-fp-media-control>
                <input
                    type="hidden"
                    id="fp-exp-hero-image"
                    name="fp_exp_details[hero_image_id]"
                    value="<?php echo esc_attr((string) $hero_id); ?>"
                    data-fp-media-input
                />
                <div class="fp-exp-cover-media__preview" data-fp-media-preview>
                    <div class="fp-exp-cover-media__placeholder" data-fp-media-placeholder <?php echo $hero_url ? 'hidden' : ''; ?>>
                        <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                            <rect x="1" y="1" width="46" height="30" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2" />
                            <path d="M16 12a4 4 0 1 1 4 4 4 4 0 0 1-4-4Zm-6 14 8-10 6 7 4-5 8 8Z" fill="currentColor" />
                        </svg>
                        <span class="screen-reader-text"><?php esc_html_e('Nessuna immagine selezionata', 'fp-experiences'); ?></span>
                    </div>
                    <?php if ($hero_url) : ?>
                        <img
                            src="<?php echo esc_url($hero_url); ?>"
                            alt=""
                            <?php if ($hero_width > 0) : ?>width="<?php echo esc_attr((string) $hero_width); ?>"<?php endif; ?>
                            <?php if ($hero_height > 0) : ?>height="<?php echo esc_attr((string) $hero_height); ?>"<?php endif; ?>
                            loading="lazy"
                            data-fp-media-image
                        />
                    <?php endif; ?>
                </div>
                <div class="fp-exp-cover-media__actions">
                    <button
                        type="button"
                        class="button button-secondary"
                        data-fp-media-choose
                        data-label-select="<?php echo esc_attr__('Seleziona immagine', 'fp-experiences'); ?>"
                        data-label-change="<?php echo esc_attr__('Modifica immagine', 'fp-experiences'); ?>"
                    >
                        <?php echo $hero_url ? esc_html__('Modifica immagine', 'fp-experiences') : esc_html__('Seleziona immagine', 'fp-experiences'); ?>
                    </button>
                    <button
                        type="button"
                        class="button-link"
                        data-fp-media-remove
                        <?php echo $hero_url ? '' : ' hidden'; ?>
                    >
                        <?php esc_html_e('Rimuovi immagine', 'fp-experiences'); ?>
                    </button>
                </div>
            </div>
            <p class="fp-exp-field__description" id="fp-exp-hero-image-help"><?php esc_html_e('Consigliata proporzione 16:9 con soggetti centrati.', 'fp-experiences'); ?></p>
        </div>
        <?php
    }

    /**
     * Render gallery field.
     */
    private function render_gallery_field(array $gallery): void
    {
        $gallery_items = $gallery['items'] ?? [];
        $gallery_ids = $gallery['ids'] ?? [];
        ?>
        <div class="fp-exp-field">
            <span class="fp-exp-field__label">
                <?php esc_html_e('Galleria immagini', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-gallery-help', esc_html__('Aggiungi immagini alla galleria dell\'esperienza. La prima immagine verrà usata come hero se non è selezionata un\'immagine hero dedicata.', 'fp-experiences')); ?>
            </span>
            <div class="fp-exp-gallery" data-fp-gallery-control>
                <input
                    type="hidden"
                    id="fp-exp-gallery-ids"
                    name="fp_exp_details[gallery_ids]"
                    value="<?php echo esc_attr(implode(',', array_map('absint', $gallery_ids))); ?>"
                    data-fp-gallery-input
                />
                <template data-fp-gallery-item-template>
                    <li class="fp-exp-gallery-control__item" data-fp-gallery-item>
                        <div class="fp-exp-gallery-control__thumb">
                            <span class="fp-exp-gallery-control__placeholder" data-fp-gallery-placeholder></span>
                            <img src="" alt="" loading="lazy" data-fp-gallery-image hidden />
                        </div>
                        <div class="fp-exp-gallery-control__toolbar">
                            <button
                                type="button"
                                class="fp-exp-gallery-control__move"
                                data-fp-gallery-move="prev"
                                aria-label="<?php esc_attr_e('Sposta indietro', 'fp-experiences'); ?>"
                            >
                                <span aria-hidden="true">←</span>
                            </button>
                            <button
                                type="button"
                                class="fp-exp-gallery-control__move"
                                data-fp-gallery-move="next"
                                aria-label="<?php esc_attr_e('Sposta avanti', 'fp-experiences'); ?>"
                            >
                                <span aria-hidden="true">→</span>
                            </button>
                            <button
                                type="button"
                                class="fp-exp-gallery-control__remove"
                                data-fp-gallery-remove
                                aria-label="<?php esc_attr_e('Rimuovi immagine', 'fp-experiences'); ?>"
                            >
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    </li>
                </template>
                <ul class="fp-exp-gallery-control__list" data-fp-gallery-list role="list">
                    <?php foreach ($gallery_items as $item) :
                        if (!is_array($item)) {
                            continue;
                        }

                        $item_id = isset($item['id']) ? (int) $item['id'] : 0;
                        $item_url = isset($item['url']) ? (string) $item['url'] : '';
                        if ($item_id <= 0 || '' === $item_url) {
                            continue;
                        }
                        ?>
                        <li class="fp-exp-gallery-control__item" data-fp-gallery-item data-id="<?php echo esc_attr((string) $item_id); ?>">
                            <div class="fp-exp-gallery-control__thumb">
                                <img src="<?php echo esc_url($item_url); ?>" alt="" loading="lazy" data-fp-gallery-image />
                            </div>
                            <div class="fp-exp-gallery-control__toolbar">
                                <button
                                    type="button"
                                    class="fp-exp-gallery-control__move"
                                    data-fp-gallery-move="prev"
                                    aria-label="<?php esc_attr_e('Sposta indietro', 'fp-experiences'); ?>"
                                >
                                    <span aria-hidden="true">←</span>
                                </button>
                                <button
                                    type="button"
                                    class="fp-exp-gallery-control__move"
                                    data-fp-gallery-move="next"
                                    aria-label="<?php esc_attr_e('Sposta avanti', 'fp-experiences'); ?>"
                                >
                                    <span aria-hidden="true">→</span>
                                </button>
                                <button
                                    type="button"
                                    class="fp-exp-gallery-control__remove"
                                    data-fp-gallery-remove
                                    aria-label="<?php esc_attr_e('Rimuovi immagine', 'fp-experiences'); ?>"
                                >
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="fp-exp-gallery-control__empty" data-fp-gallery-empty <?php echo empty($gallery_items) ? '' : ' hidden'; ?>>
                    <?php esc_html_e('Nessuna immagine nella galleria.', 'fp-experiences'); ?>
                </p>
                <div class="fp-exp-gallery-control__actions">
                    <button
                        type="button"
                        class="button button-primary"
                        data-fp-gallery-add
                        data-label-select="<?php echo esc_attr__('Seleziona immagini', 'fp-experiences'); ?>"
                        data-label-update="<?php echo esc_attr__('Aggiungi altre immagini', 'fp-experiences'); ?>"
                    >
                        <?php echo empty($gallery_items) ? esc_html__('Seleziona immagini', 'fp-experiences') : esc_html__('Aggiungi altre immagini', 'fp-experiences'); ?>
                    </button>
                    <button
                        type="button"
                        class="button"
                        data-fp-gallery-clear
                        data-label-clear="<?php echo esc_attr__('Rimuovi tutte le immagini', 'fp-experiences'); ?>"
                        <?php echo empty($gallery_items) ? ' hidden' : ''; ?>
                    >
                        <?php esc_html_e('Rimuovi tutte le immagini', 'fp-experiences'); ?>
                    </button>
                </div>
            </div>
            <p class="fp-exp-field__description" id="fp-exp-gallery-help"><?php esc_html_e('Le immagini della galleria vengono mostrate nella pagina esperienza.', 'fp-experiences'); ?></p>
        </div>
        <?php
    }

    /**
     * Render taxonomy fields (categories, tags, difficulty, age_restrictions, location).
     */
    private function render_taxonomy_fields(array $data, int $post_id): void
    {
        $categories = $data['categories'] ?? [];
        $tags = $data['tags'] ?? [];
        $difficulty = $data['difficulty'] ?? [];
        $age_restrictions = $data['age_restrictions'] ?? [];
        $location = $data['location'] ?? [];

        $has_selects = ! empty($difficulty['choices'])
            || ! empty($age_restrictions['choices'])
            || ! empty($location['choices']);

        ?>
        <div class="fp-exp-content-trust__block fp-exp-content-trust__block--taxonomy">
            <h4 class="fp-exp-field__subtitle"><?php esc_html_e('Categorie e classificazione', 'fp-experiences'); ?></h4>
            <p class="fp-exp-dms-hint fp-exp-content-trust__block-intro">
                <?php esc_html_e('Categorie e tag consentono più selezioni; difficoltà, restrizioni di età e località usano un solo valore a scelta.', 'fp-experiences'); ?>
            </p>
        <?php
        if (! empty($categories['choices'])) {
            $this->render_taxonomy_checkboxes(
                'categories',
                'fp_exp_details[categories]',
                __('Categorie', 'fp-experiences'),
                $categories['choices'],
                $categories['selected'] ?? []
            );
        }

        if (! empty($tags['choices'])) {
            $this->render_taxonomy_checkboxes(
                'tags',
                'fp_exp_details[tags]',
                __('Tag', 'fp-experiences'),
                $tags['choices'],
                $tags['selected'] ?? []
            );
        }

        if ($has_selects) {
            echo '<div class="fp-exp-dms-fields-grid fp-exp-content-trust__selects">';
        }
        if (! empty($difficulty['choices'])) {
            $this->render_taxonomy_select(
                'difficulty',
                'fp_exp_details[difficulty]',
                __('Difficoltà', 'fp-experiences'),
                $difficulty['choices'],
                (int) ($difficulty['selected'] ?? 0)
            );
        }
        if (! empty($age_restrictions['choices'])) {
            $this->render_taxonomy_select(
                'age_restrictions',
                'fp_exp_details[age_restrictions]',
                __('Restrizioni età', 'fp-experiences'),
                $age_restrictions['choices'],
                (int) ($age_restrictions['selected'] ?? 0)
            );
        }
        if (! empty($location['choices'])) {
            $this->render_taxonomy_select(
                'location',
                'fp_exp_details[location]',
                __('Località', 'fp-experiences'),
                $location['choices'],
                (int) ($location['selected'] ?? 0)
            );
        }
        if ($has_selects) {
            echo '</div>';
        }
        ?>
        </div>
        <?php
    }

    /**
     * Render taxonomy checkboxes.
     */
    private function render_taxonomy_checkboxes(string $id, string $name, string $label, array $choices, array $selected): void
    {
        ?>
        <div class="fp-exp-field fp-exp-field--taxonomy-multi">
            <span class="fp-exp-field__label"><?php echo esc_html($label); ?></span>
            <div class="fp-exp-checkbox-grid fp-exp-checkbox-grid--taxonomy-terms" role="group" aria-label="<?php echo esc_attr($label); ?>">
                <?php foreach ($choices as $choice) :
                    if (! is_array($choice)) {
                        continue;
                    }

                    $term_id = isset($choice['id']) ? (int) $choice['id'] : 0;
                    $term_label = isset($choice['label']) ? (string) $choice['label'] : '';

                    if ($term_id <= 0 || '' === $term_label) {
                        continue;
                    }
                    ?>
                    <label class="fp-exp-checkbox-grid__item fp-exp-checkbox-grid__item--taxonomy-term">
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($name); ?>[]"
                            value="<?php echo esc_attr((string) $term_id); ?>"
                            <?php checked(in_array($term_id, $selected, true)); ?>
                        />
                        <span class="fp-exp-checkbox-grid__content">
                            <span class="fp-exp-checkbox-grid__icon fp-exp-checkbox-grid__icon--empty" aria-hidden="true"></span>
                            <span class="fp-exp-checkbox-grid__body">
                                <span class="fp-exp-checkbox-grid__title"><?php echo esc_html($term_label); ?></span>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render gallery video field.
     */
    private function render_gallery_video_field(array $data): void
    {
        $gallery_video_url = isset($data['gallery_video_url']) ? (string) $data['gallery_video_url'] : '';
        ?>
        <div class="fp-exp-field">
            <label for="fp-exp-gallery-video-url" class="fp-exp-field__label">
                <?php esc_html_e('Video YouTube della galleria', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-gallery-video-help', esc_html__('Inserisci l\'URL di un video YouTube da mostrare nella sezione "Uno sguardo all\'esperienza". Il video partirà automaticamente. Es: https://www.youtube.com/watch?v=ABC123', 'fp-experiences')); ?>
            </label>
            <input
                type="url"
                id="fp-exp-gallery-video-url"
                name="fp_exp_details[gallery_video_url]"
                value="<?php echo esc_attr($gallery_video_url); ?>"
                placeholder="https://www.youtube.com/watch?v=..."
                class="regular-text"
                aria-describedby="fp-exp-gallery-video-help"
            />
            <p class="fp-exp-field__description" id="fp-exp-gallery-video-help">
                <?php esc_html_e('Il video YouTube verrà mostrato prima della galleria di immagini e partirà automaticamente con audio disattivato.', 'fp-experiences'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Select icona per badge custom con anteprima Font Awesome (opzioni ordinate, default in cima).
     *
     * @param array<string, string> $icon_options Slug => etichetta tradotta.
     * @param array<string, string> $icon_fa_map  Slug => classi FA per `data-fp-fa-classes`.
     */
    private function render_badge_custom_icon_control(
        string $selected_slug,
        array $icon_options,
        array $icon_fa_map,
        string $field_name,
        bool $use_data_name
    ): void {
        ?>
        <div class="fp-exp-taxonomy-editor__field fp-exp-badge-icon-field">
            <span class="fp-exp-field__label"><?php esc_html_e('Icona', 'fp-experiences'); ?></span>
            <div class="fp-exp-badge-icon-field__control">
                <span class="fp-exp-badge-icon-preview" data-fp-exp-badge-icon-preview aria-hidden="true"></span>
                <select
                    class="fp-exp-badge-icon-select regular-text"
                    <?php
                    if ($use_data_name) {
                        echo ' data-name="' . esc_attr($field_name) . '"';
                    } else {
                        echo ' name="' . esc_attr($field_name) . '"';
                    }
                    ?>
                >
                    <?php foreach ($icon_options as $opt_slug => $opt_label) :
                        $opt_slug = sanitize_key((string) $opt_slug);
                        if ('' === $opt_slug) {
                            continue;
                        }
                        $fa_preview = $icon_fa_map[$opt_slug] ?? '';
                        ?>
                        <option
                            value="<?php echo esc_attr($opt_slug); ?>"
                            <?php
                            if ('' !== $fa_preview) {
                                echo ' data-fp-fa-classes="' . esc_attr($fa_preview) . '"';
                            }
                            ?>
                            <?php selected($selected_slug, $opt_slug, true); ?>
                        >
                            <?php echo esc_html((string) $opt_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Render experience badges field (predefiniti + personalizzati con aggiunta dinamica).
     */
    private function render_experience_badges_field(array $data, int $post_id): void
    {
        $eb = $data['experience_badges'] ?? [];
        $badge_choices = isset($eb['choices']) && is_array($eb['choices']) ? $eb['choices'] : [];
        $selected_slugs = isset($eb['selected']) && is_array($eb['selected'])
            ? array_values(array_filter(array_map(static function ($v): string {
                return sanitize_key((string) $v);
            }, $eb['selected'])))
            : [];
        $custom_rows = isset($data['experience_badges_custom']) && is_array($data['experience_badges_custom'])
            ? $data['experience_badges_custom']
            : [];
        $icon_options = Helpers::experience_badge_icon_admin_options();
        $icon_fa_map = Helpers::experience_badge_icon_fa_class_map();
        if (isset($icon_options['default'])) {
            $default_opt = $icon_options['default'];
            unset($icon_options['default']);
            uasort($icon_options, static fn ($a, $b): int => strcasecmp((string) $a, (string) $b));
            $icon_options = ['default' => $default_opt] + $icon_options;
        } else {
            uasort($icon_options, static fn ($a, $b): int => strcasecmp((string) $a, (string) $b));
        }
        $next_custom_index = count($custom_rows);
        ?>
        <div class="fp-exp-content-trust__block" id="fp-exp-experience-badges">
            <h4 class="screen-reader-text"><?php esc_html_e('Caratteristiche e badge', 'fp-experiences'); ?></h4>
            <div class="fp-exp-experience-badges__sections">
            <div class="fp-exp-field">
                <span class="fp-exp-field__label">
                    <?php esc_html_e('Caratteristiche predefinite', 'fp-experiences'); ?>
                    <?php $this->render_tooltip(
                        'fp-exp-experience-badges-preset-help',
                        esc_html__(
                            'Seleziona i tipi di esperienza (ognuno ha un\'icona). Puoi combinarli con badge personalizzati sotto.',
                            'fp-experiences'
                        )
                    ); ?>
                </span>
                <?php if (! empty($badge_choices)) : ?>
                    <div class="fp-exp-checkbox-grid fp-exp-checkbox-grid--experience-badges" aria-describedby="fp-exp-experience-badges-preset-desc">
                        <?php foreach ($badge_choices as $choice) :
                            if (! is_array($choice)) {
                                continue;
                            }
                            $bid = isset($choice['id']) ? sanitize_key((string) $choice['id']) : '';
                            $blabel = isset($choice['label']) ? (string) $choice['label'] : '';
                            if ('' === $bid || '' === $blabel) {
                                continue;
                            }
                            $bdesc = isset($choice['description']) ? (string) $choice['description'] : '';
                            $bicon = isset($choice['icon']) ? (string) $choice['icon'] : '';
                            $icon_svg = Helpers::experience_badge_icon_svg($bicon);
                            ?>
                            <label class="fp-exp-checkbox-grid__item fp-exp-checkbox-grid__item--experience-badge">
                                <input
                                    type="checkbox"
                                    name="fp_exp_details[experience_badges][]"
                                    value="<?php echo esc_attr($bid); ?>"
                                    <?php checked(in_array($bid, $selected_slugs, true)); ?>
                                />
                                <span class="fp-exp-checkbox-grid__content">
                                    <span class="fp-exp-checkbox-grid__icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
                                    <span class="fp-exp-checkbox-grid__body">
                                        <span class="fp-exp-checkbox-grid__title"><?php echo esc_html($blabel); ?></span>
                                        <?php if ('' !== $bdesc) : ?>
                                            <span class="fp-exp-checkbox-grid__description"><?php echo esc_html($bdesc); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="fp-exp-field__description" id="fp-exp-experience-badges-preset-desc">
                        <?php esc_html_e('Le etichette possono essere personalizzate in Impostazioni → Listing.', 'fp-experiences'); ?>
                    </p>
                <?php else : ?>
                    <p class="fp-exp-field__description fp-exp-field__description--muted">
                        <?php esc_html_e('Nessuna caratteristica predefinita disponibile.', 'fp-experiences'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="fp-exp-field fp-exp-field--experience-badges-custom-divider">
                <span class="fp-exp-field__label">
                    <?php esc_html_e('Badge personalizzati', 'fp-experiences'); ?>
                    <?php $this->render_tooltip(
                        'fp-exp-experience-badges-custom-help',
                        esc_html__(
                            'Aggiungi solo le righe che servono: titolo obbligatorio; descrizione e icona facoltativi.',
                            'fp-experiences'
                        )
                    ); ?>
                </span>
                <p class="fp-exp-field__description" id="fp-exp-experience-badges-custom-help-text">
                    <?php esc_html_e('Usa «Aggiungi badge» per inserire una riga. Per l’icona scegli una voce dall’elenco (con anteprima). Rimuovi le righe non necessarie prima di salvare.', 'fp-experiences'); ?>
                </p>
                <div
                    class="fp-exp-taxonomy-editor fp-exp-exp-badge-custom-editor"
                    data-fp-exp-badge-custom-editor
                    data-fp-exp-badge-next-index="<?php echo esc_attr((string) $next_custom_index); ?>"
                >
                    <div class="fp-exp-taxonomy-editor__list fp-exp-exp-badge-custom-list" data-fp-exp-badge-custom-list>
                        <?php
                        $badge_index = 0;
                        foreach ($custom_rows as $entry) :
                            if (! is_array($entry)) {
                                continue;
                            }
                            $cid = sanitize_key((string) ($entry['id'] ?? ''));
                            $clabel = sanitize_text_field((string) ($entry['label'] ?? ''));
                            $cdesc = sanitize_text_field((string) ($entry['description'] ?? ''));
                            $cicon = Helpers::sanitize_experience_badge_icon_key((string) ($entry['icon'] ?? 'default'));
                            ?>
                            <div class="fp-exp-taxonomy-editor__item fp-exp-exp-badge-custom-item" data-fp-exp-badge-item>
                                <input type="hidden" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][id]" value="<?php echo esc_attr($cid); ?>" />
                                <?php
                                $this->render_badge_custom_icon_control(
                                    $cicon,
                                    $icon_options,
                                    $icon_fa_map,
                                    'fp_exp_details[experience_badges_custom][' . (string) $badge_index . '][icon]',
                                    false
                                );
                                ?>
                                <label class="fp-exp-taxonomy-editor__field">
                                    <span class="fp-exp-field__label"><?php esc_html_e('Titolo badge', 'fp-experiences'); ?></span>
                                    <input type="text" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][label]" value="<?php echo esc_attr($clabel); ?>" />
                                </label>
                                <label class="fp-exp-taxonomy-editor__field">
                                    <span class="fp-exp-field__label"><?php esc_html_e('Descrizione badge', 'fp-experiences'); ?></span>
                                    <textarea rows="2" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][description]"><?php echo esc_textarea($cdesc); ?></textarea>
                                </label>
                                <p class="fp-exp-exp-badge-custom__remove-wrap">
                                    <button type="button" class="button-link-delete" data-fp-exp-badge-remove>
                                        <?php esc_html_e('Rimuovi', 'fp-experiences'); ?>
                                    </button>
                                </p>
                            </div>
                            <?php
                            ++$badge_index;
                        endforeach;
                        ?>
                    </div>
                    <p class="fp-exp-taxonomy-editor__actions">
                        <button type="button" class="button" data-fp-exp-badge-add>
                            <?php esc_html_e('Aggiungi badge', 'fp-experiences'); ?>
                        </button>
                    </p>
                    <template data-fp-exp-badge-template>
                        <div class="fp-exp-taxonomy-editor__item fp-exp-exp-badge-custom-item" data-fp-exp-badge-item>
                            <input type="hidden" data-name="fp_exp_details[experience_badges_custom][__INDEX__][id]" value="" />
                            <?php
                            $this->render_badge_custom_icon_control(
                                'default',
                                $icon_options,
                                $icon_fa_map,
                                'fp_exp_details[experience_badges_custom][__INDEX__][icon]',
                                true
                            );
                            ?>
                            <label class="fp-exp-taxonomy-editor__field">
                                <span class="fp-exp-field__label"><?php esc_html_e('Titolo badge', 'fp-experiences'); ?></span>
                                <input type="text" data-name="fp_exp_details[experience_badges_custom][__INDEX__][label]" value="" />
                            </label>
                            <label class="fp-exp-taxonomy-editor__field">
                                <span class="fp-exp-field__label"><?php esc_html_e('Descrizione badge', 'fp-experiences'); ?></span>
                                <textarea rows="2" data-name="fp_exp_details[experience_badges_custom][__INDEX__][description]"></textarea>
                            </label>
                            <p class="fp-exp-exp-badge-custom__remove-wrap">
                                <button type="button" class="button-link-delete" data-fp-exp-badge-remove>
                                    <?php esc_html_e('Rimuovi', 'fp-experiences'); ?>
                                </button>
                            </p>
                        </div>
                    </template>
                </div>
            </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render linked page field.
     */
    private function render_linked_page_field(array $data): void
    {
        $page_details = $data['linked_page'] ?? [];
        $page_id = isset($page_details['id']) ? (int) $page_details['id'] : 0;
        $page_url = isset($page_details['url']) ? (string) $page_details['url'] : '';
        $page_edit_url = isset($page_details['edit_url']) ? (string) $page_details['edit_url'] : '';
        $page_status = isset($page_details['status_label']) ? (string) $page_details['status_label'] : '';
        ?>
        <div class="fp-exp-field">
            <label class="fp-exp-field__label">
                <?php esc_html_e('Pagina pubblica', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-linked-page-help', esc_html__('Ogni esperienza pubblicata genera una pagina WordPress con lo shortcode completo.', 'fp-experiences')); ?>
            </label>
            <?php if ($page_id && $page_url) : ?>
                <div class="fp-exp-field__buttons" role="group" aria-describedby="fp-exp-linked-page-help">
                    <a
                        class="button button-secondary"
                        href="<?php echo esc_url($page_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <?php esc_html_e('Vedi pagina', 'fp-experiences'); ?>
                    </a>
                    <?php if ($page_edit_url) : ?>
                        <a class="button" href="<?php echo esc_url($page_edit_url); ?>">
                            <?php esc_html_e('Modifica pagina', 'fp-experiences'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($page_status) : ?>
                    <p class="fp-exp-field__description" id="fp-exp-linked-page-help">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: current page status label. */
                                __('Stato pagina: %s', 'fp-experiences'),
                                $page_status
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <p class="fp-exp-field__description" id="fp-exp-linked-page-help">
                    <?php esc_html_e("La pagina viene generata automaticamente alla pubblicazione dell'esperienza.", 'fp-experiences'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render capacity fields (min party and capacity slot).
     */
    private function render_capacity_fields(array $data): void
    {
        $min_party = isset($data['min_party']) ? absint((string) $data['min_party']) : 0;
        $capacity_slot = isset($data['capacity_slot']) ? absint((string) $data['capacity_slot']) : 0;
        $party_default = isset($data['party_default']) ? absint((string) $data['party_default']) : 0;
        $party_max = isset($data['party_max']) ? absint((string) $data['party_max']) : 0;
        ?>
        <div class="fp-exp-field fp-exp-field--columns">
            <div>
                <label class="fp-exp-field__label" for="fp-exp-min-party">
                    <?php esc_html_e('Partecipanti minimi', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-min-party-help', esc_html__('Numero minimo richiesto per confermare la partenza.', 'fp-experiences')); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-min-party"
                    name="fp_exp_details[min_party]"
                    min="0"
                    step="1"
                    value="<?php echo esc_attr((string) $min_party); ?>"
                    aria-describedby="fp-exp-min-party-help"
                />
            </div>
            <div>
                <label class="fp-exp-field__label" for="fp-exp-capacity">
                    <?php esc_html_e('Capienza totale', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-capacity-help', esc_html__('Numero massimo di posti disponibili complessivi.', 'fp-experiences')); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-capacity"
                    name="fp_exp_details[capacity_slot]"
                    min="0"
                    step="1"
                    value="<?php echo esc_attr((string) $capacity_slot); ?>"
                    aria-describedby="fp-exp-capacity-help"
                />
            </div>
        </div>
        <div class="fp-exp-field fp-exp-field--columns">
            <div>
                <label class="fp-exp-field__label" for="fp-exp-party-default">
                    <?php esc_html_e('Quantità predefinita', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-party-default-help', esc_html__('Quantità pre-selezionata nel widget (es. 2 per esperienza di coppia). Lascia 0 per nessuna pre-selezione.', 'fp-experiences')); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-party-default"
                    name="fp_exp_details[party_default]"
                    min="0"
                    step="1"
                    value="<?php echo esc_attr((string) $party_default); ?>"
                    aria-describedby="fp-exp-party-default-help"
                />
            </div>
            <div>
                <label class="fp-exp-field__label" for="fp-exp-party-max">
                    <?php esc_html_e('Quantità massima', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-party-max-help', esc_html__('Limite massimo di biglietti acquistabili (es. 2 per esperienza di coppia). Lascia 0 per nessun limite.', 'fp-experiences')); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-party-max"
                    name="fp_exp_details[party_max]"
                    min="0"
                    step="1"
                    value="<?php echo esc_attr((string) $party_max); ?>"
                    aria-describedby="fp-exp-party-max-help"
                />
            </div>
        </div>
        <?php
    }

    /**
     * Render age fields (min and max age).
     */
    private function render_age_fields(array $data): void
    {
        $age_min = isset($data['age_min']) ? absint((string) $data['age_min']) : 0;
        $age_max = isset($data['age_max']) ? absint((string) $data['age_max']) : 0;
        ?>
        <div class="fp-exp-field fp-exp-field--columns">
            <div>
                <label class="fp-exp-field__label" for="fp-exp-age-min">
                    <?php esc_html_e('Età minima', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-age-min-help', esc_html__('Età minima consigliata per partecipare.', 'fp-experiences')); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-age-min"
                    name="fp_exp_details[age_min]"
                    min="0"
                    step="1"
                    value="<?php echo esc_attr((string) $age_min); ?>"
                    aria-describedby="fp-exp-age-min-help"
                />
            </div>
            <div>
                <label class="fp-exp-field__label" for="fp-exp-age-max">
                    <?php esc_html_e('Età massima', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-age-max-help', esc_html__('Lascia vuoto se non previsto.', 'fp-experiences')); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-age-max"
                    name="fp_exp_details[age_max]"
                    min="0"
                    step="1"
                    value="<?php echo esc_attr((string) $age_max); ?>"
                    aria-describedby="fp-exp-age-max-help"
                />
            </div>
        </div>
        <?php
    }

    /**
     * Render children rules field.
     */
    private function render_children_rules_field(array $data): void
    {
        $rules_children = isset($data['rules_children']) ? (string) $data['rules_children'] : '';
        ?>
        <div class="fp-exp-field">
            <label class="fp-exp-field__label" for="fp-exp-children-rules">
                <?php esc_html_e('Regole bambini', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-children-help', esc_html__('Note su policy bambini, passeggini o riduzioni.', 'fp-experiences')); ?>
            </label>
            <textarea
                id="fp-exp-children-rules"
                name="fp_exp_details[rules_children]"
                rows="3"
                placeholder="<?php echo esc_attr__('Es. Gratuito sotto i 6 anni accompagnati da un adulto', 'fp-experiences'); ?>"
                aria-describedby="fp-exp-children-help"
            ><?php echo esc_textarea($rules_children); ?></textarea>
            <p class="fp-exp-field__description" id="fp-exp-children-help"><?php esc_html_e('Testo mostrato nelle informazioni aggiuntive.', 'fp-experiences'); ?></p>
        </div>
        <?php
    }

    /**
     * Render trust badges (cognitive biases) field.
     */
    private function render_trust_badges_field(array $data): void
    {
        $cognitive_biases = $data['cognitive_biases'] ?? [];
        $choices = $cognitive_biases['choices'] ?? [];
        $selected_biases = isset($cognitive_biases['selected']) && is_array($cognitive_biases['selected'])
            ? array_values(array_filter(array_map('strval', $cognitive_biases['selected'])))
            : [];
        
        if (empty($choices)) {
            return;
        }
        
        $max_biases = Helpers::cognitive_bias_max_selection();
        $selected_bias_count = count($selected_biases);
        $status_template = __('Badge selezionati: %1$s su %2$s', 'fp-experiences');
        $status_limit_message = __('Hai raggiunto il numero massimo di badge selezionabili.', 'fp-experiences');
        $search_input_id = 'fp-exp-bias-search';
        $grid_id = 'fp-exp-bias-grid';
        ?>
        <div class="fp-exp-content-trust__block fp-exp-content-trust__block--trust">
            <h4 class="fp-exp-field__subtitle">
                <?php esc_html_e('Badge di fiducia', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-bias-help', esc_html__('Evidenzia le leve persuasive che caratterizzano l\'esperienza; vengono mostrate nella panoramica.', 'fp-experiences')); ?>
            </h4>
            <div class="fp-exp-field fp-exp-field--trust-badges">
            <div class="fp-exp-checkbox-grid__search">
                <label class="screen-reader-text" for="<?php echo esc_attr($search_input_id); ?>">
                    <?php esc_html_e('Filtra badge di fiducia', 'fp-experiences'); ?>
                </label>
                <input
                    type="search"
                    id="<?php echo esc_attr($search_input_id); ?>"
                    class="fp-exp-checkbox-grid__search-input"
                    data-fp-cognitive-bias-search
                    placeholder="<?php echo esc_attr__('Cerca badge…', 'fp-experiences'); ?>"
                    autocomplete="off"
                    spellcheck="false"
                    aria-controls="<?php echo esc_attr($grid_id); ?>"
                />
            </div>
            <div
                id="<?php echo esc_attr($grid_id); ?>"
                class="fp-exp-checkbox-grid fp-exp-checkbox-grid--trust-badges"
                aria-describedby="fp-exp-bias-status fp-exp-bias-help"
                data-fp-cognitive-bias
                data-max="<?php echo esc_attr((string) $max_biases); ?>"
            >
                <?php foreach ($choices as $choice) :
                    if (!is_array($choice)) {
                        continue;
                    }
                    
                    $bias_id = (string) ($choice['id'] ?? '');
                    if ('' === $bias_id) {
                        continue;
                    }

                    $label = isset($choice['label']) ? (string) $choice['label'] : '';
                    if ('' === $label) {
                        continue;
                    }

                    $description = isset($choice['description']) ? (string) $choice['description'] : '';
                    $tagline = isset($choice['tagline']) ? (string) $choice['tagline'] : '';
                    $icon_name = isset($choice['icon']) ? (string) $choice['icon'] : '';
                    $icon_svg = Helpers::cognitive_bias_icon_svg($icon_name);
                    $keywords = isset($choice['keywords']) && is_array($choice['keywords'])
                        ? array_values(array_filter(array_map('strval', $choice['keywords'])))
                        : [];
                    $search_terms = array_merge([$label, $tagline, $description], $keywords);
                    $search_terms = array_values(array_filter($search_terms, static function ($term): bool {
                        return '' !== trim((string) $term);
                    }));
                    $search_terms = array_map(static function ($term): string {
                        $value = sanitize_text_field((string) $term);
                        if ('' === $value) {
                            return '';
                        }

                        if (function_exists('mb_strtolower')) {
                            return mb_strtolower($value, 'UTF-8');
                        }

                        return strtolower($value);
                    }, $search_terms);
                    $search_terms = array_values(array_filter($search_terms));
                    $search_blob = implode(' ', array_unique($search_terms));
                    ?>
                    <label class="fp-exp-checkbox-grid__item" data-search="<?php echo esc_attr($search_blob); ?>">
                        <input type="checkbox" name="fp_exp_details[cognitive_biases][]" value="<?php echo esc_attr($bias_id); ?>" <?php checked(in_array($bias_id, $selected_biases, true)); ?> />
                        <span class="fp-exp-checkbox-grid__content">
                            <span class="fp-exp-checkbox-grid__icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
                            <span class="fp-exp-checkbox-grid__body">
                                <span class="fp-exp-checkbox-grid__title"><?php echo esc_html($label); ?></span>
                                <?php if ('' !== $tagline) : ?>
                                    <span class="fp-exp-checkbox-grid__tagline"><?php echo esc_html($tagline); ?></span>
                                <?php endif; ?>
                                <?php if ('' !== $description) : ?>
                                    <span class="fp-exp-checkbox-grid__description"><?php echo esc_html($description); ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p
                class="fp-exp-field__description fp-exp-field__description--muted"
                data-fp-cognitive-bias-empty
                hidden
            >
                <?php esc_html_e('Nessun badge corrisponde alla ricerca.', 'fp-experiences'); ?>
            </p>
            <p
                class="fp-exp-field__description fp-exp-field__description--status"
                id="fp-exp-bias-status"
                data-fp-cognitive-bias-status
                data-template="<?php echo esc_attr($status_template); ?>"
                data-max-message="<?php echo esc_attr($status_limit_message); ?>"
            >
                <?php
                echo esc_html(
                    sprintf(
                        $status_template,
                        $selected_bias_count,
                        $max_biases
                    )
                );

                if ($selected_bias_count >= $max_biases && '' !== $status_limit_message) {
                    echo ' ' . esc_html($status_limit_message);
                }
                ?>
            </p>
            <p class="fp-exp-field__description" id="fp-exp-bias-help"><?php esc_html_e('Scegli fino a tre badge di fiducia per creare aspettative chiare nella sezione panoramica.', 'fp-experiences'); ?></p>
            </div>
        </div>
        <script>
        (function () {
            function initFpExpTrustBadgeCounter() {
                var grid = document.getElementById('<?php echo esc_js($grid_id); ?>');
                var status = document.getElementById('fp-exp-bias-status');
                if (!grid || !status) {
                    return;
                }

                var max = parseInt(grid.getAttribute('data-max') || '0', 10);
                if (!max || max < 1) {
                    return;
                }

                var template = status.getAttribute('data-template') || 'Badge selezionati: %1$s su %2$s';
                var maxMessage = status.getAttribute('data-max-message') || '';

                function render() {
                    var checkboxes = grid.querySelectorAll('input[type="checkbox"]');
                    var count = 0;

                    checkboxes.forEach(function (checkbox) {
                        if (checkbox.checked) {
                            count += 1;
                        }
                        var item = checkbox.closest('label');
                        if (item) {
                            item.classList.toggle('is-selected', checkbox.checked);
                        }
                    });

                    var message = template.replace('%1$s', String(count)).replace('%2$s', String(max));
                    if (count >= max && maxMessage) {
                        message += ' ' + maxMessage;
                    }
                    status.textContent = message;
                }

                grid.addEventListener('change', function (event) {
                    var target = event.target;
                    if (target && target.matches && target.matches('input[type="checkbox"]')) {
                        render();
                    }
                });

                grid.addEventListener('click', function () {
                    window.setTimeout(render, 0);
                });

                render();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initFpExpTrustBadgeCounter);
            } else {
                initFpExpTrustBadgeCounter();
            }
        })();
        </script>
        <?php
    }

    /**
     * Render taxonomy select.
     */
    private function render_taxonomy_select(string $id, string $name, string $label, array $choices, int $selected): void
    {
        ?>
        <div class="fp-exp-dms-field fp-exp-content-trust__select-field">
            <label for="fp-exp-<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <select id="fp-exp-<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                <option value="0"><?php esc_html_e('-- Nessuno --', 'fp-experiences'); ?></option>
                <?php foreach ($choices as $choice) :
                    if (!is_array($choice)) {
                        continue;
                    }

                    $term_id = isset($choice['id']) ? (int) $choice['id'] : 0;
                    $term_label = isset($choice['label']) ? (string) $choice['label'] : '';

                    if ($term_id <= 0 || '' === $term_label) {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr((string) $term_id); ?>" <?php selected($selected, $term_id, true); ?>>
                        <?php echo esc_html($term_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        // Short description
        $short_desc = $this->sanitize_textarea($raw['short_desc'] ?? '');
        $this->update_or_delete_meta($post_id, 'short_desc', $short_desc);

        // Duration
        $duration_minutes = isset($raw['duration_minutes']) ? absint((string) $raw['duration_minutes']) : 0;
        $this->update_or_delete_meta($post_id, 'duration_minutes', $duration_minutes > 0 ? $duration_minutes : null);

        // Event flags
        $is_event = !empty($raw['is_event']);
        $this->update_or_delete_meta($post_id, 'is_event', $is_event);

        $event_datetime_raw = $this->sanitize_text($raw['event_datetime'] ?? '');
        $event_datetime = $this->normalize_event_datetime($event_datetime_raw);
        if ($is_event && $event_datetime !== '') {
            $this->update_or_delete_meta($post_id, 'event_datetime', $event_datetime);
        } else {
            $this->update_or_delete_meta($post_id, 'event_datetime', null);
        }

        $event_ticket_sales_end_raw = $this->sanitize_text($raw['event_ticket_sales_end'] ?? '');
        $event_ticket_sales_end = $this->normalize_event_datetime($event_ticket_sales_end_raw);
        if ($is_event && $event_ticket_sales_end !== '') {
            if ($event_datetime !== '' && strcmp($event_ticket_sales_end, $event_datetime) > 0) {
                $event_ticket_sales_end = $event_datetime;
            }
            $this->update_or_delete_meta($post_id, 'event_ticket_sales_end', $event_ticket_sales_end);
        } else {
            $this->update_or_delete_meta($post_id, 'event_ticket_sales_end', null);
        }

        $sr_mode = sanitize_key((string) ($raw['single_event_special_requests_mode'] ?? 'default'));
        if (! in_array($sr_mode, ['default', 'notes_only', 'hidden'], true)) {
            $sr_mode = 'default';
        }
        $this->update_or_delete_meta($post_id, 'single_event_special_requests_mode', $sr_mode);

        $sr_title = $this->sanitize_text($raw['single_event_special_requests_title'] ?? '');
        $this->update_or_delete_meta($post_id, 'single_event_special_requests_title', $sr_title !== '' ? $sr_title : null);

        $sr_notes_label = $this->sanitize_text($raw['single_event_special_requests_notes_label'] ?? '');
        $this->update_or_delete_meta($post_id, 'single_event_special_requests_notes_label', $sr_notes_label !== '' ? $sr_notes_label : null);

        $sr_help = $this->sanitize_textarea($raw['single_event_special_requests_help'] ?? '');
        $this->update_or_delete_meta($post_id, 'single_event_special_requests_help', $sr_help !== '' ? $sr_help : null);

        $this->save_widget_special_requests_items($post_id, $raw);

        // Hero image
        $hero_image_id = isset($raw['hero_image_id']) ? absint((string) $raw['hero_image_id']) : 0;
        if ($hero_image_id > 0 && wp_attachment_is_image($hero_image_id)) {
            $this->update_or_delete_meta($post_id, 'hero_image_id', $hero_image_id);
        } else {
            delete_post_meta($post_id, '_fp_hero_image_id');
        }

        // Gallery
        $gallery_ids_raw = $raw['gallery_ids'] ?? '';
        $gallery_ids = [];
        if (is_string($gallery_ids_raw) && $gallery_ids_raw !== '') {
            $ids = explode(',', $gallery_ids_raw);
            foreach ($ids as $id) {
                $id = absint(trim($id));
                if ($id > 0 && wp_attachment_is_image($id)) {
                    $gallery_ids[] = $id;
                }
            }
        }
        $this->update_or_delete_meta($post_id, 'gallery_ids', !empty($gallery_ids) ? array_unique($gallery_ids) : null);

        // Gallery video URL
        $gallery_video_url = isset($raw['gallery_video_url']) ? esc_url_raw((string) $raw['gallery_video_url']) : '';
        $this->update_or_delete_meta($post_id, 'gallery_video_url', $gallery_video_url !== '' ? $gallery_video_url : null);

        // Experience badges predefiniti (slug validi rispetto a Helpers::experience_badge_choices)
        $experience_badges_slugs = [];
        if (isset($raw['experience_badges']) && is_array($raw['experience_badges'])) {
            foreach ($raw['experience_badges'] as $slug) {
                $s = sanitize_key((string) $slug);
                if ('' !== $s) {
                    $experience_badges_slugs[] = $s;
                }
            }
        }
        $experience_badges_slugs = array_values(array_unique($experience_badges_slugs));
        $valid_badge_ids = array_keys(Helpers::experience_badge_choices());
        $experience_badges_slugs = array_values(array_filter(
            $experience_badges_slugs,
            static fn (string $s): bool => in_array($s, $valid_badge_ids, true)
        ));
        $this->update_or_delete_meta($post_id, 'experience_badges', [] !== $experience_badges_slugs ? $experience_badges_slugs : null);

        // Experience badges personalizzati (titolo obbligatorio; icona da registry)
        $experience_badges_custom = isset($raw['experience_badges_custom']) && is_array($raw['experience_badges_custom'])
            ? $raw['experience_badges_custom']
            : [];
        $sanitized_badges = [];
        foreach ($experience_badges_custom as $badge) {
            if (! is_array($badge)) {
                continue;
            }
            $badge_id = sanitize_key((string) ($badge['id'] ?? ''));
            $badge_label = sanitize_text_field((string) ($badge['label'] ?? ''));
            $badge_desc = $this->sanitize_textarea($badge['description'] ?? '');
            $badge_icon = Helpers::sanitize_experience_badge_icon_key((string) ($badge['icon'] ?? 'default'));
            if ('' === $badge_label) {
                continue;
            }
            if ('' === $badge_id) {
                $badge_id = sanitize_key($badge_label);
            }
            $sanitized_badges[] = [
                'id' => $badge_id,
                'label' => $badge_label,
                'description' => $badge_desc,
                'icon' => $badge_icon,
            ];
        }
        $this->update_or_delete_meta($post_id, 'experience_badge_custom', ! empty($sanitized_badges) ? $sanitized_badges : null);

        // Min party and capacity
        $min_party = isset($raw['min_party']) ? absint((string) $raw['min_party']) : 0;
        $this->update_or_delete_meta($post_id, 'min_party', $min_party > 0 ? $min_party : null);
        $capacity_slot = isset($raw['capacity_slot']) ? absint((string) $raw['capacity_slot']) : 0;
        $this->update_or_delete_meta($post_id, 'capacity_slot', $capacity_slot > 0 ? $capacity_slot : null);
        $party_default = isset($raw['party_default']) ? absint((string) $raw['party_default']) : 0;
        $this->update_or_delete_meta($post_id, 'party_default', $party_default > 0 ? $party_default : null);
        $party_max = isset($raw['party_max']) ? absint((string) $raw['party_max']) : 0;
        $this->update_or_delete_meta($post_id, 'party_max', $party_max > 0 ? $party_max : null);

        // Age restrictions
        $age_min = isset($raw['age_min']) ? absint((string) $raw['age_min']) : 0;
        $this->update_or_delete_meta($post_id, 'age_min', $age_min > 0 ? $age_min : null);
        $age_max = isset($raw['age_max']) ? absint((string) $raw['age_max']) : 0;
        $this->update_or_delete_meta($post_id, 'age_max', $age_max > 0 ? $age_max : null);

        // Children rules
        $rules_children = $this->sanitize_textarea($raw['rules_children'] ?? '');
        $this->update_or_delete_meta($post_id, 'rules_children', $rules_children !== '' ? $rules_children : null);

        // Languages (taxonomy) - MUST use integers for term IDs to avoid creating duplicate terms
        $languages = isset($raw['languages']) && is_array($raw['languages']) 
            ? array_values(array_filter(array_map('absint', $raw['languages'])))
            : [];
        
        if (!empty($languages)) {
            wp_set_post_terms($post_id, $languages, 'fp_exp_language', false);
        } else {
            wp_set_post_terms($post_id, [], 'fp_exp_language', false);
        }

        // Handle quick language presets (create terms from pre-defined checkboxes)
        $languages_quick = isset($raw['languages_quick']) && is_array($raw['languages_quick'])
            ? $raw['languages_quick']
            : [];
        $quick_term_ids = [];
        foreach ($languages_quick as $quick_value) {
            $quick_value = sanitize_text_field((string) $quick_value);
            if ('' === $quick_value) {
                continue;
            }

            $parts = array_map('trim', explode('|', $quick_value, 2));
            $quick_code = isset($parts[0]) ? sanitize_key((string) $parts[0]) : '';
            $quick_label = isset($parts[1]) ? sanitize_text_field((string) $parts[1]) : '';

            if ('' === $quick_code || '' === $quick_label) {
                continue;
            }

            $term = wp_insert_term($quick_label, 'fp_exp_language', ['slug' => $quick_code]);
            if (is_wp_error($term)) {
                if ('term_exists' === $term->get_error_code()) {
                    $existing_id = $term->get_error_data('term_exists');
                    if (is_numeric($existing_id)) {
                        $quick_term_ids[] = (int) $existing_id;
                    }
                }
                continue;
            }

            if (isset($term['term_id'])) {
                $quick_term_ids[] = (int) $term['term_id'];
            }
        }

        if (!empty($quick_term_ids)) {
            $languages = array_values(array_unique(array_merge($languages, $quick_term_ids)));
            wp_set_post_terms($post_id, $languages, 'fp_exp_language', false);
        }

        // Handle languages_manual (create terms from comma-separated list)
        $languages_manual = sanitize_text_field($raw['languages_manual'] ?? '');
        if ($languages_manual !== '') {
            $terms_to_add = array_map('trim', explode(',', $languages_manual));
            $created_term_ids = [];
            foreach ($terms_to_add as $term_name) {
                if ($term_name === '') {
                    continue;
                }
                $term = wp_insert_term($term_name, 'fp_exp_language');
                if (!is_wp_error($term) && isset($term['term_id'])) {
                    $created_term_ids[] = (int) $term['term_id'];
                }
            }
            if (!empty($created_term_ids)) {
                // Merge and ensure all are integers
                $all_languages = array_values(array_unique(array_merge($languages, $created_term_ids)));
                wp_set_post_terms($post_id, $all_languages, 'fp_exp_language', false);
            }
        }

        // Cognitive biases (trust badges)
        // IMPORTANT: When no checkboxes are selected, PHP doesn't send the field in POST
        // So we need to check if the field exists, and if not, treat it as empty array
        $cognitive_biases = [];
        if (isset($raw['cognitive_biases']) && is_array($raw['cognitive_biases'])) {
            // Sanitize and filter out empty values
            foreach ($raw['cognitive_biases'] as $bias) {
                $bias = sanitize_key((string) $bias);
                if ($bias !== '' && $bias !== null) {
                    $cognitive_biases[] = $bias;
                }
            }
        }
        $cognitive_biases = array_values(array_unique($cognitive_biases));
        $cognitive_biases = array_slice($cognitive_biases, 0, Helpers::cognitive_bias_max_selection());
        
        // Save cognitive biases directly (the key is already prefixed with _fp in the meta key)
        // Note: get_meta_key() returns '_fp', so the full key will be '_fp_cognitive_biases'
        // Always save, even if empty array (to clear previous selections when all are unchecked)
        update_post_meta($post_id, '_fp_cognitive_biases', $cognitive_biases);

        // Taxonomies (categories, tags, difficulty, age_restrictions, location)
        $this->save_taxonomy_terms($post_id, $raw['categories'] ?? [], 'fp_exp_category');
        $this->save_taxonomy_terms($post_id, $raw['tags'] ?? [], 'fp_exp_tag');
        $this->save_taxonomy_terms($post_id, $raw['difficulty'] ?? [], 'fp_exp_difficulty', true);
        $this->save_taxonomy_terms($post_id, $raw['age_restrictions'] ?? [], 'fp_exp_age_restriction', true);
        $this->save_taxonomy_terms($post_id, $raw['location'] ?? [], 'fp_exp_location', true);
    }

    /**
     * Save taxonomy terms.
     */
    private function save_taxonomy_terms(int $post_id, mixed $terms, string $taxonomy, bool $single = false): void
    {
        if ($single) {
            $term_id = isset($terms) && is_numeric($terms) ? absint((string) $terms) : 0;
            wp_set_post_terms($post_id, $term_id > 0 ? [$term_id] : [], $taxonomy, false);
        } else {
            $term_ids = is_array($terms) ? array_map('absint', $terms) : [];
            wp_set_post_terms($post_id, array_unique(array_filter($term_ids)), $taxonomy, false);
        }
    }

    protected function get_meta_data(int $post_id): array
    {
        // Get basic fields
        $short_desc = $this->get_meta_value($post_id, 'short_desc', '');
        $duration_minutes = absint((string) $this->get_meta_value($post_id, 'duration_minutes', 0));

        // Get hero image
        $hero_image = $this->get_hero_image($post_id);

        // Get gallery
        $gallery = $this->get_gallery_for_editor($post_id);

        // Get languages
        $language_selected = $this->get_assigned_terms($post_id, 'fp_exp_language');
        $language_choices = $this->get_language_choices_for_editor();
        $language_badges = $this->get_language_badges($language_selected);

        // Get taxonomies
        $categories = [
            'selected' => $this->get_assigned_terms($post_id, 'fp_exp_category'),
            'choices' => $this->get_taxonomy_choices('fp_exp_category'),
        ];
        $tags = [
            'selected' => $this->get_assigned_terms($post_id, 'fp_exp_tag'),
            'choices' => $this->get_taxonomy_choices('fp_exp_tag'),
        ];
        $difficulty = [
            'selected' => $this->get_assigned_terms($post_id, 'fp_exp_difficulty')[0] ?? 0,
            'choices' => $this->get_taxonomy_choices('fp_exp_difficulty'),
        ];
        $age_restrictions = [
            'selected' => $this->get_assigned_terms($post_id, 'fp_exp_age_restriction')[0] ?? 0,
            'choices' => $this->get_taxonomy_choices('fp_exp_age_restriction'),
        ];
        $location = [
            'selected' => $this->get_assigned_terms($post_id, 'fp_exp_location')[0] ?? 0,
            'choices' => $this->get_taxonomy_choices('fp_exp_location'),
        ];

        // Cognitive biases (trust badges)
        $cognitive_biases_stored = get_post_meta($post_id, '_fp_cognitive_biases', true);
        if (!is_array($cognitive_biases_stored)) {
            $cognitive_biases_stored = [];
        }
        $cognitive_biases_selected = array_map(static fn ($badge): string => sanitize_key((string) $badge), $cognitive_biases_stored);
        $cognitive_biases_selected = array_values(array_unique(array_filter($cognitive_biases_selected)));
        $cognitive_biases_choices = Helpers::cognitive_bias_choices();
        $valid_choices = array_map(static fn ($choice) => (string) $choice['id'], $cognitive_biases_choices);
        $cognitive_biases_selected = array_values(array_filter($cognitive_biases_selected, static function (string $badge) use ($valid_choices): bool {
            return in_array($badge, $valid_choices, true);
        }));

        // Get gallery video URL
        $gallery_video_url = esc_url((string) $this->get_meta_value($post_id, 'gallery_video_url', ''));

        $experience_badges_custom_raw = $this->get_meta_value($post_id, 'experience_badge_custom', []);
        if (! is_array($experience_badges_custom_raw)) {
            $experience_badges_custom_raw = [];
        }
        $experience_badges_custom = [];
        foreach ($experience_badges_custom_raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $experience_badges_custom[] = [
                'id' => sanitize_key((string) ($row['id'] ?? '')),
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
                'icon' => Helpers::sanitize_experience_badge_icon_key((string) ($row['icon'] ?? 'default')),
            ];
        }

        $badge_choice_map = Helpers::experience_badge_choices();
        $experience_badges_selected = Helpers::get_meta_array($post_id, '_fp_experience_badges', []);
        $experience_badges_selected = array_values(array_filter(
            array_map(static fn ($s): string => sanitize_key((string) $s), $experience_badges_selected),
            static fn (string $s): bool => '' !== $s && isset($badge_choice_map[$s])
        ));

        // Get linked page details
        $linked_page = $this->get_linked_page_details($post_id);

        // Get capacity and age fields
        $min_party = absint((string) $this->get_meta_value($post_id, 'min_party', 0));
        $capacity_slot = absint((string) $this->get_meta_value($post_id, 'capacity_slot', 0));
        $party_default = absint((string) $this->get_meta_value($post_id, 'party_default', 0));
        $party_max = absint((string) $this->get_meta_value($post_id, 'party_max', 0));
        $age_min = absint((string) $this->get_meta_value($post_id, 'age_min', 0));
        $age_max = absint((string) $this->get_meta_value($post_id, 'age_max', 0));
        $rules_children = sanitize_text_field((string) $this->get_meta_value($post_id, 'rules_children', ''));

        // Get event fields (evento a data singola)
        $is_event = (bool) $this->get_meta_value($post_id, 'is_event', false);
        $event_datetime = (string) $this->get_meta_value($post_id, 'event_datetime', '');
        $event_ticket_sales_end = (string) $this->get_meta_value($post_id, 'event_ticket_sales_end', '');
        $single_event_sr_mode = (string) $this->get_meta_value($post_id, 'single_event_special_requests_mode', 'default');
        if (! in_array($single_event_sr_mode, ['default', 'notes_only', 'hidden'], true)) {
            $single_event_sr_mode = 'default';
        }
        $single_event_sr_title = sanitize_text_field((string) $this->get_meta_value($post_id, 'single_event_special_requests_title', ''));
        $single_event_sr_notes_label = sanitize_text_field((string) $this->get_meta_value($post_id, 'single_event_special_requests_notes_label', ''));
        $single_event_sr_help = sanitize_textarea_field((string) $this->get_meta_value($post_id, 'single_event_special_requests_help', ''));

        $raw_sr_items = $this->get_meta_value($post_id, 'widget_special_requests_items', '');
        $parsed_sr_items = SpecialRequestsOptions::parse_stored_meta($raw_sr_items);
        if ($parsed_sr_items === null) {
            $special_requests_enabled_presets = SpecialRequestsOptions::PRESET_ORDER;
            $special_requests_custom_rows = [];
        } else {
            $special_requests_enabled_presets = [];
            $special_requests_custom_rows = [];
            foreach ($parsed_sr_items as $sr_row) {
                if (($sr_row['kind'] ?? '') === 'preset') {
                    $special_requests_enabled_presets[] = (string) ($sr_row['id'] ?? '');
                } elseif (($sr_row['kind'] ?? '') === 'custom') {
                    $special_requests_custom_rows[] = [
                        'slug' => (string) ($sr_row['slug'] ?? ''),
                        'label' => (string) ($sr_row['label'] ?? ''),
                    ];
                }
            }
            $special_requests_enabled_presets = array_values(array_filter($special_requests_enabled_presets));
        }
        for ($sr_pad = 0; $sr_pad < 2; $sr_pad++) {
            $special_requests_custom_rows[] = ['slug' => '', 'label' => ''];
        }

        return [
            'short_desc' => $short_desc,
            'duration_minutes' => $duration_minutes,
            'is_event' => $is_event,
            'event_datetime' => $event_datetime,
            'event_ticket_sales_end' => $event_ticket_sales_end,
            'single_event_special_requests_mode' => $single_event_sr_mode,
            'single_event_special_requests_title' => $single_event_sr_title,
            'single_event_special_requests_notes_label' => $single_event_sr_notes_label,
            'single_event_special_requests_help' => $single_event_sr_help,
            'special_requests_enabled_presets' => $special_requests_enabled_presets,
            'special_requests_custom_rows' => $special_requests_custom_rows,
            'hero_image' => $hero_image,
            'gallery' => $gallery,
            'gallery_video_url' => $gallery_video_url,
            'languages' => [
                'selected' => $language_selected,
                'choices' => $language_choices,
            ],
            'language_badges' => $language_badges,
            'categories' => $categories,
            'tags' => $tags,
            'difficulty' => $difficulty,
            'age_restrictions' => $age_restrictions,
            'location' => $location,
            'cognitive_biases' => [
                'choices' => $cognitive_biases_choices,
                'selected' => $cognitive_biases_selected,
            ],
            'experience_badges' => [
                'choices' => array_values($badge_choice_map),
                'selected' => $experience_badges_selected,
            ],
            'experience_badges_custom' => $experience_badges_custom,
            'linked_page' => $linked_page,
            'min_party' => $min_party,
            'capacity_slot' => $capacity_slot,
            'party_default' => $party_default,
            'party_max' => $party_max,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'rules_children' => $rules_children,
        ];
    }

    /**
     * Salva elenco checkbox richieste speciali widget (JSON) o rimuove meta se equivale al predefinito.
     *
     * @param array<string, mixed> $raw
     */
    private function save_widget_special_requests_items(int $post_id, array $raw): void
    {
        if (empty($raw['special_requests_items_touched'])) {
            return;
        }

        $catalog = SpecialRequestsOptions::preset_catalog();
        $enabled = isset($raw['special_requests_enabled_presets']) && is_array($raw['special_requests_enabled_presets'])
            ? array_values(array_unique(array_filter(array_map(
                static fn ($v): string => sanitize_key((string) $v),
                $raw['special_requests_enabled_presets']
            ))))
            : [];
        $enabled = array_values(array_filter($enabled, static fn (string $id): bool => isset($catalog[$id])));

        $items = [];
        foreach (SpecialRequestsOptions::PRESET_ORDER as $pid) {
            if (in_array($pid, $enabled, true)) {
                $items[] = ['kind' => 'preset', 'id' => $pid];
            }
        }

        $custom_raw = isset($raw['special_requests_custom_rows']) && is_array($raw['special_requests_custom_rows'])
            ? $raw['special_requests_custom_rows']
            : [];

        $reserved = array_flip(SpecialRequestsOptions::PRESET_ORDER);
        foreach ($items as $pit) {
            if (($pit['kind'] ?? '') === 'preset' && isset($pit['id'])) {
                $reserved[(string) $pit['id']] = true;
            }
        }

        foreach ($custom_raw as $crow) {
            if (! is_array($crow)) {
                continue;
            }
            $label = sanitize_text_field((string) ($crow['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $slug_in = sanitize_key((string) ($crow['slug'] ?? ''));
            $slug = $slug_in !== '' ? $slug_in : SpecialRequestsOptions::slug_from_label($label);
            $slug = substr(sanitize_key($slug), 0, 60);
            if ($slug === '') {
                $slug = 'opt_' . substr(md5($label), 0, 8);
            }
            $base = $slug;
            $n = 1;
            while (isset($reserved[$slug])) {
                $slug = substr($base . '_' . (string) $n, 0, 60);
                ++$n;
                if ($n > 500) {
                    $slug = substr($base . '_' . wp_generate_password(4, false, false), 0, 60);
                    break;
                }
            }
            $reserved[$slug] = true;
            $items[] = ['kind' => 'custom', 'slug' => $slug, 'label' => $label];
        }

        if (SpecialRequestsOptions::is_equivalent_to_default($items)) {
            $this->update_or_delete_meta($post_id, 'widget_special_requests_items', null);

            return;
        }

        $json = wp_json_encode($items, JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            $json = '[]';
        }
        $this->update_or_delete_meta($post_id, 'widget_special_requests_items', $json);
    }

    private function normalize_event_datetime(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        $value = str_replace('T', ' ', $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $value)) {
            return '';
        }

        return $value;
    }

    private function format_event_datetime_for_input(string $value): string
    {
        $normalized = $this->normalize_event_datetime($value);
        if ($normalized === '') {
            return '';
        }

        return str_replace(' ', 'T', $normalized);
    }

    /**
     * Get linked page details.
     */
    private function get_linked_page_details(int $post_id): array
    {
        $page_id = absint((string) get_post_meta($post_id, '_fp_exp_page_id', true));
        if (!$page_id) {
            return [
                'id' => 0,
                'url' => '',
                'edit_url' => '',
                'status' => '',
                'status_label' => '',
            ];
        }

        $status = get_post_status($page_id) ?: '';
        $status_object = $status ? get_post_status_object($status) : null;

        return [
            'id' => $page_id,
            'url' => get_permalink($page_id) ?: '',
            'edit_url' => get_edit_post_link($page_id, 'raw') ?: '',
            'status' => $status,
            'status_label' => $status_object && !empty($status_object->label) ? (string) $status_object->label : '',
        ];
    }

    /**
     * Get gallery for editor.
     * 
     * @return array{items: array<int, array{id: int, url: string}>, ids: array<int>}
     */
    private function get_gallery_for_editor(int $post_id): array
    {
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $stored = [];
        if ($repo !== null) {
            $stored = $repo->getMeta($post_id, '_fp_gallery_ids', []);
            if (!is_array($stored)) {
                $stored = [];
            }
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $stored = get_post_meta($post_id, '_fp_gallery_ids', true);
            if (!is_array($stored)) {
                $stored = [];
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $stored))));
        $items = [];

        foreach ($ids as $image_id) {
            if ($image_id <= 0 || !wp_attachment_is_image($image_id)) {
                continue;
            }

            $url = wp_get_attachment_image_url($image_id, 'thumbnail');
            if ($url) {
                $items[] = [
                    'id' => $image_id,
                    'url' => $url,
                ];
            }
        }

        return [
            'items' => $items,
            'ids' => $ids,
        ];
    }

    /**
     * Get language choices enriched with flag sprite metadata.
     *
     * @return array<int, array{id: int, label: string, code: string, sprite: string}>
     */
    private function get_language_choices_for_editor(): array
    {
        $terms = get_terms([
            'taxonomy' => 'fp_exp_language',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $choices = [];
        foreach ($terms as $term) {
            $code = sanitize_key((string) $term->slug);
            $choices[] = [
                'id' => (int) $term->term_id,
                'label' => (string) $term->name,
                'code' => strtoupper($code),
                'sprite' => LanguageHelper::get_sprite_id_for_code($code),
            ];
        }

        return $choices;
    }

    /**
     * Default quick options shown when no language terms exist yet.
     *
     * @return array<int, array{code: string, label: string}>
     */
    private function get_language_quick_options(): array
    {
        return [
            ['code' => 'it', 'label' => 'Italiano'],
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'fr', 'label' => 'Français'],
            ['code' => 'de', 'label' => 'Deutsch'],
            ['code' => 'es', 'label' => 'Español'],
            ['code' => 'pt', 'label' => 'Português'],
        ];
    }

    /**
     * Get language badges data.
     * 
     * @param array<int> $term_ids
     * @return array<int, array{sprite: string, code: string, aria_label: string, label: string}>
     */
    private function get_language_badges(array $term_ids): array
    {
        if (empty($term_ids)) {
            return [];
        }

        $badges = [];
        foreach ($term_ids as $term_id) {
            $term = get_term($term_id, 'fp_exp_language');
            if (!$term || is_wp_error($term)) {
                continue;
            }

            $code = sanitize_key($term->slug);
            $sprite_id = LanguageHelper::get_sprite_id_for_code($code);
            $label = $term->name;

            $badges[] = [
                'sprite' => $sprite_id,
                'code' => strtoupper($code),
                'aria_label' => $label,
                'label' => $label,
            ];
        }

        return $badges;
    }
}










