<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;
use FP_Exp\Utils\LanguageHelper;
use FP_Exp\Utils\Helpers;

use function absint;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function esc_url;
use function get_terms;
use function sanitize_key;
use function sanitize_text_field;
use function wp_get_post_terms;
use function wp_attachment_is_image;

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
        $hero_image = $data['hero_image'] ?? ['id' => 0, 'url' => '', 'width' => 0, 'height' => 0];
        $gallery = $data['gallery'] ?? ['items' => [], 'ids' => []];
        $language_details = $data['languages'] ?? [];
        $language_choices = $language_details['choices'] ?? [];
        $language_selected = $language_details['selected'] ?? [];
        $language_badges = $data['language_badges'] ?? [];
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-details"
            data-tab-panel="details"
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Informazioni generali', 'fp-experiences'); ?></legend>
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
                            <?php esc_html_e('Lingue disponibili', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-language-badge-help', esc_html__("Seleziona le lingue parlate durante l'esperienza: verranno mostrate nei badge pubblici e nel widget di prenotazione.", 'fp-experiences')); ?>
                        </span>
                        <?php if (!empty($language_choices)) : ?>
                            <div class="fp-exp-checkbox-grid" aria-describedby="fp-exp-language-badge-help">
                                <?php foreach ($language_choices as $choice) :
                                    if (!is_array($choice)) {
                                        continue;
                                    }

                                    $term_id = isset($choice['id']) ? (int) $choice['id'] : 0;
                                    $label = isset($choice['label']) ? (string) $choice['label'] : '';

                                    if ($term_id <= 0 || '' === $label) {
                                        continue;
                                    }
                                    ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="fp_exp_details[languages][]"
                                            value="<?php echo esc_attr((string) $term_id); ?>"
                                            <?php checked(in_array($term_id, $language_selected, true)); ?>
                                        />
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Non hai ancora creato lingue. Aggiungi nuove voci qui sotto per iniziare.', 'fp-experiences'); ?></p>
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
                        <?php if (!empty($language_badges)) : ?>
                            <ul class="fp-exp-language-preview" role="list" aria-describedby="fp-exp-language-badge-help">
                                <?php foreach ($language_badges as $language) :
                                    if (!is_array($language)) {
                                        continue;
                                    }

                                    $sprite_id = isset($language['sprite']) ? (string) $language['sprite'] : '';
                                    $code = isset($language['code']) ? (string) $language['code'] : '';
                                    $aria_label = isset($language['aria_label']) ? (string) $language['aria_label'] : $code;
                                    $label = isset($language['label']) ? (string) $language['label'] : $code;

                                    if ('' === $code) {
                                        continue;
                                    }
                                    ?>
                                    <li class="fp-exp-language-preview__item">
                                        <?php if ($sprite_id) : ?>
                                            <span class="fp-exp-language-preview__flag" role="img" aria-label="<?php echo esc_attr($aria_label); ?>">
                                                <svg viewBox="0 0 24 16" aria-hidden="true" focusable="false">
                                                    <use href="<?php echo esc_url(LanguageHelper::get_sprite_url() . '#' . $sprite_id); ?>"></use>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                        <span class="fp-exp-language-preview__code" aria-hidden="true"><?php echo esc_html($code); ?></span>
                                        <span class="screen-reader-text"><?php echo esc_html($label); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Nessuna lingua selezionata al momento.', 'fp-experiences'); ?></p>
                        <?php endif; ?>
                        <p class="fp-exp-field__description" id="fp-exp-language-badge-help"><?php esc_html_e('Le lingue selezionate vengono mostrate nei badge pubblici, nel widget e nei filtri.', 'fp-experiences'); ?></p>
                    </div>
                </div>

                <?php $this->render_hero_image_field($hero_image); ?>
                <?php $this->render_gallery_field($gallery); ?>
                <?php $this->render_gallery_video_field($data); ?>
                <?php $this->render_experience_badges_field($data, $post_id); ?>
                <?php $this->render_taxonomy_fields($data, $post_id); ?>
                <?php $this->render_trust_badges_field($data); ?>
                <?php $this->render_linked_page_field($data); ?>
                <?php $this->render_capacity_fields($data); ?>
                <?php $this->render_age_fields($data); ?>
                <?php $this->render_children_rules_field($data); ?>
            </fieldset>
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

        // Categories
        if (!empty($categories['choices'])) {
            $this->render_taxonomy_checkboxes(
                'categories',
                'fp_exp_details[categories]',
                'Categorie',
                $categories['choices'],
                $categories['selected'] ?? []
            );
        }

        // Tags
        if (!empty($tags['choices'])) {
            $this->render_taxonomy_checkboxes(
                'tags',
                'fp_exp_details[tags]',
                'Tag',
                $tags['choices'],
                $tags['selected'] ?? []
            );
        }

        // Difficulty
        if (!empty($difficulty['choices'])) {
            $this->render_taxonomy_select(
                'difficulty',
                'fp_exp_details[difficulty]',
                'Difficoltà',
                $difficulty['choices'],
                $difficulty['selected'] ?? 0
            );
        }

        // Age Restrictions
        if (!empty($age_restrictions['choices'])) {
            $this->render_taxonomy_select(
                'age_restrictions',
                'fp_exp_details[age_restrictions]',
                'Restrizioni età',
                $age_restrictions['choices'],
                $age_restrictions['selected'] ?? 0
            );
        }

        // Location
        if (!empty($location['choices'])) {
            $this->render_taxonomy_select(
                'location',
                'fp_exp_details[location]',
                'Località',
                $location['choices'],
                $location['selected'] ?? 0
            );
        }
    }

    /**
     * Render taxonomy checkboxes.
     */
    private function render_taxonomy_checkboxes(string $id, string $name, string $label, array $choices, array $selected): void
    {
        ?>
        <div class="fp-exp-field">
            <span class="fp-exp-field__label"><?php echo esc_html($label); ?></span>
            <div class="fp-exp-checkbox-grid">
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
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($name); ?>[]"
                            value="<?php echo esc_attr((string) $term_id); ?>"
                            <?php checked(in_array($term_id, $selected, true)); ?>
                        />
                        <span><?php echo esc_html($term_label); ?></span>
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
     * Render experience badges field.
     */
    private function render_experience_badges_field(array $data, int $post_id): void
    {
        $custom_badges_existing = get_post_meta($post_id, '_fp_experience_badge_custom', true);
        $custom_badges_existing = is_array($custom_badges_existing) ? $custom_badges_existing : [];
        ?>
        <div class="fp-exp-field fp-exp-field--taxonomies">
            <div class="fp-exp-field">
                <span class="fp-exp-field__label">
                    <?php esc_html_e('Badge esperienza', 'fp-experiences'); ?>
                    <?php $this->render_tooltip('fp-exp-experience-badges-help', esc_html__('Aggiungi i badge per questa esperienza compilando i campi sottostanti. I badge inseriti verranno mostrati nella pagina esperienza, nelle liste e nei badge rapidi. Se compili almeno un campo (titolo o descrizione), il badge verrà visualizzato automaticamente.', 'fp-experiences')); ?>
                </span>
                <div class="fp-exp-taxonomy-editor fp-exp-taxonomy-editor--compact" aria-describedby="fp-exp-experience-badges-help">
                    <div class="fp-exp-taxonomy-editor__list">
                        <?php
                        $badge_index = 0;
                        foreach ($custom_badges_existing as $entry) :
                            $cid = sanitize_key((string) ($entry['id'] ?? ''));
                            $clabel = sanitize_text_field((string) ($entry['label'] ?? ''));
                            $cdesc = sanitize_text_field((string) ($entry['description'] ?? ''));
                            ?>
                            <div class="fp-exp-taxonomy-editor__item">
                                <input type="hidden" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][id]" value="<?php echo esc_attr($cid); ?>" />
                                <label class="fp-exp-taxonomy-editor__field">
                                    <span class="fp-exp-field__label"><?php esc_html_e('Titolo badge', 'fp-experiences'); ?></span>
                                    <input type="text" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][label]" value="<?php echo esc_attr($clabel); ?>" />
                                </label>
                                <label class="fp-exp-taxonomy-editor__field">
                                    <span class="fp-exp-field__label"><?php esc_html_e('Descrizione badge', 'fp-experiences'); ?></span>
                                    <textarea rows="2" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][description]"><?php echo esc_textarea($cdesc); ?></textarea>
                                </label>
                            </div>
                        <?php
                            $badge_index++;
                        endforeach;
                        ?>
                        <?php for ($i = 0; $i < 6; $i++) : ?>
                            <div class="fp-exp-taxonomy-editor__item">
                                <input type="hidden" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][id]" value="" />
                                <label class="fp-exp-taxonomy-editor__field">
                                    <span class="fp-exp-field__label"><?php esc_html_e('Titolo badge', 'fp-experiences'); ?></span>
                                    <input type="text" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][label]" value="" />
                                </label>
                                <label class="fp-exp-taxonomy-editor__field">
                                    <span class="fp-exp-field__label"><?php esc_html_e('Descrizione badge', 'fp-experiences'); ?></span>
                                    <textarea rows="2" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][description]"></textarea>
                                </label>
                            </div>
                        <?php
                            $badge_index++;
                        endfor;
                        ?>
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
        <div class="fp-exp-field">
            <span class="fp-exp-field__label">
                <?php esc_html_e('Badge di fiducia', 'fp-experiences'); ?>
                <?php $this->render_tooltip('fp-exp-bias-help', esc_html__('Evidenzia le leve persuasive che caratterizzano l\'esperienza; vengono mostrate nella panoramica.', 'fp-experiences')); ?>
            </span>
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
                class="fp-exp-checkbox-grid"
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
        <?php
    }

    /**
     * Render taxonomy select.
     */
    private function render_taxonomy_select(string $id, string $name, string $label, array $choices, int $selected): void
    {
        ?>
        <div class="fp-exp-field">
            <label class="fp-exp-field__label" for="fp-exp-<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
            </label>
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

        // Experience badges custom
        $experience_badges_custom = isset($raw['experience_badges_custom']) && is_array($raw['experience_badges_custom'])
            ? $raw['experience_badges_custom']
            : [];
        $sanitized_badges = [];
        foreach ($experience_badges_custom as $badge) {
            if (!is_array($badge)) {
                continue;
            }
            $badge_id = sanitize_key((string) ($badge['id'] ?? ''));
            $badge_label = sanitize_text_field((string) ($badge['label'] ?? ''));
            $badge_desc = $this->sanitize_textarea($badge['description'] ?? '');
            // Only save if at least one field is filled
            if ($badge_id !== '' || $badge_label !== '' || $badge_desc !== '') {
                $sanitized_badges[] = [
                    'id' => $badge_id,
                    'label' => $badge_label,
                    'description' => $badge_desc,
                ];
            }
        }
        $this->update_or_delete_meta($post_id, 'experience_badge_custom', !empty($sanitized_badges) ? $sanitized_badges : null);

        // Min party and capacity
        $min_party = isset($raw['min_party']) ? absint((string) $raw['min_party']) : 0;
        $this->update_or_delete_meta($post_id, 'min_party', $min_party > 0 ? $min_party : null);
        $capacity_slot = isset($raw['capacity_slot']) ? absint((string) $raw['capacity_slot']) : 0;
        $this->update_or_delete_meta($post_id, 'capacity_slot', $capacity_slot > 0 ? $capacity_slot : null);

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
        $language_choices = $this->get_taxonomy_choices('fp_exp_language');
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

        // Get experience badges custom
        $experience_badges_custom = $this->get_meta_value($post_id, 'experience_badge_custom', []);
        if (!is_array($experience_badges_custom)) {
            $experience_badges_custom = [];
        }

        // Get linked page details
        $linked_page = $this->get_linked_page_details($post_id);

        // Get capacity and age fields
        $min_party = absint((string) $this->get_meta_value($post_id, 'min_party', 0));
        $capacity_slot = absint((string) $this->get_meta_value($post_id, 'capacity_slot', 0));
        $age_min = absint((string) $this->get_meta_value($post_id, 'age_min', 0));
        $age_max = absint((string) $this->get_meta_value($post_id, 'age_max', 0));
        $rules_children = sanitize_text_field((string) $this->get_meta_value($post_id, 'rules_children', ''));

        return [
            'short_desc' => $short_desc,
            'duration_minutes' => $duration_minutes,
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
            'linked_page' => $linked_page,
            'min_party' => $min_party,
            'capacity_slot' => $capacity_slot,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'rules_children' => $rules_children,
        ];
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










