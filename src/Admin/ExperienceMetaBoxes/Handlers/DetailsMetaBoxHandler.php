<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;
use FP_Exp\Utils\LanguageHelper;

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
                <?php $this->render_taxonomy_fields($data, $post_id); ?>
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
                <div class="fp-exp-gallery__grid" data-fp-gallery-grid>
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
                        <div class="fp-exp-gallery__item" data-fp-gallery-item="<?php echo esc_attr((string) $item_id); ?>">
                            <img src="<?php echo esc_url($item_url); ?>" alt="" loading="lazy" />
                            <button type="button" class="fp-exp-gallery__remove" data-fp-gallery-remove aria-label="<?php esc_attr_e('Rimuovi immagine', 'fp-experiences'); ?>">
                                <span class="screen-reader-text"><?php esc_html_e('Rimuovi', 'fp-experiences'); ?></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button
                    type="button"
                    class="button button-secondary"
                    data-fp-gallery-add
                >
                    <?php esc_html_e('Aggiungi immagini', 'fp-experiences'); ?>
                </button>
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

        return [
            'short_desc' => $short_desc,
            'duration_minutes' => $duration_minutes,
            'hero_image' => $hero_image,
            'gallery' => $gallery,
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










