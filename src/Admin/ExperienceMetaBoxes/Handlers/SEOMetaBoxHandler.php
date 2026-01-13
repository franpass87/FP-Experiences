<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;

use function esc_attr;
use function esc_html;
use function esc_textarea;
use function wp_kses_post;

/**
 * Handler for SEO/Schema tab in Experience Meta Box.
 * 
 * Handles SEO metadata, schema markup, and social sharing settings.
 */
final class SEOMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-seo-panel';
        $meta_title = $data['meta_title'] ?? '';
        $meta_description = $data['meta_description'] ?? '';
        $schema_type = $data['schema_type'] ?? 'TouristTrip';
        $schema_rating = $data['schema_rating'] ?? '';
        $schema_review_count = $data['schema_review_count'] ?? '';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-seo"
            data-tab-panel="seo"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Meta Tags SEO', 'fp-experiences'); ?></legend>
                
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meta-title">
                        <?php esc_html_e('Titolo SEO', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-meta-title-help', esc_html__('Titolo personalizzato per i motori di ricerca. Se vuoto, viene usato il titolo dell\'esperienza.', 'fp-experiences')); ?>
                    </label>
                    <input
                        type="text"
                        id="fp-exp-meta-title"
                        name="fp_exp_seo[meta_title]"
                        value="<?php echo esc_attr($meta_title); ?>"
                        class="large-text"
                        maxlength="60"
                        placeholder="<?php echo esc_attr__('Es. Tour Enogastronomico Langhe - Degustazione Vini', 'fp-experiences'); ?>"
                        aria-describedby="fp-exp-meta-title-help"
                    />
                    <p class="fp-exp-field__description" id="fp-exp-meta-title-help">
                        <?php esc_html_e('Massimo 60 caratteri consigliati per i risultati di ricerca.', 'fp-experiences'); ?>
                    </p>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meta-description">
                        <?php esc_html_e('Descrizione SEO', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-meta-description-help', esc_html__('Descrizione personalizzata per i motori di ricerca. Se vuota, viene usata la descrizione breve.', 'fp-experiences')); ?>
                    </label>
                    <textarea
                        id="fp-exp-meta-description"
                        name="fp_exp_seo[meta_description]"
                        rows="3"
                        class="large-text"
                        maxlength="160"
                        placeholder="<?php echo esc_attr__('Es. Scopri le Langhe con un tour enogastronomico...', 'fp-experiences'); ?>"
                        aria-describedby="fp-exp-meta-description-help"
                    ><?php echo esc_textarea($meta_description); ?></textarea>
                    <p class="fp-exp-field__description" id="fp-exp-meta-description-help">
                        <?php esc_html_e('Massimo 160 caratteri consigliati per i risultati di ricerca.', 'fp-experiences'); ?>
                    </p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Schema.org / Structured Data', 'fp-experiences'); ?></legend>
                
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-schema-type">
                        <?php esc_html_e('Tipo Schema', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-schema-type-help', esc_html__('Tipo di schema strutturato da utilizzare per questa esperienza.', 'fp-experiences')); ?>
                    </label>
                    <select
                        id="fp-exp-schema-type"
                        name="fp_exp_seo[schema_type]"
                        aria-describedby="fp-exp-schema-type-help"
                    >
                        <option value="TouristTrip" <?php selected($schema_type, 'TouristTrip'); ?>>
                            <?php esc_html_e('TouristTrip', 'fp-experiences'); ?>
                        </option>
                        <option value="Event" <?php selected($schema_type, 'Event'); ?>>
                            <?php esc_html_e('Event', 'fp-experiences'); ?>
                        </option>
                        <option value="Product" <?php selected($schema_type, 'Product'); ?>>
                            <?php esc_html_e('Product', 'fp-experiences'); ?>
                        </option>
                    </select>
                    <p class="fp-exp-field__description" id="fp-exp-schema-type-help">
                        <?php esc_html_e('Il tipo di schema più comune per esperienze turistiche è TouristTrip.', 'fp-experiences'); ?>
                    </p>
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-schema-rating">
                            <?php esc_html_e('Valutazione Media', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-schema-rating"
                            name="fp_exp_seo[schema_rating]"
                            value="<?php echo esc_attr($schema_rating); ?>"
                            min="0"
                            max="5"
                            step="0.1"
                            class="small-text"
                            placeholder="4.5"
                        />
                        <p class="fp-exp-field__description">
                            <?php esc_html_e('Valutazione media (0-5).', 'fp-experiences'); ?>
                        </p>
                    </div>

                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-schema-review-count">
                            <?php esc_html_e('Numero Recensioni', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-schema-review-count"
                            name="fp_exp_seo[schema_review_count]"
                            value="<?php echo esc_attr($schema_review_count); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            placeholder="42"
                        />
                        <p class="fp-exp-field__description">
                            <?php esc_html_e('Numero totale di recensioni.', 'fp-experiences'); ?>
                        </p>
                    </div>
                </div>
            </fieldset>
        </section>
        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        // Meta tags - match existing meta keys
        $meta_title = $this->sanitize_text($raw['meta_title'] ?? '');
        $meta_description = $this->sanitize_textarea($raw['meta_description'] ?? '');

        // Limit lengths as per SEO best practices
        if (mb_strlen($meta_title) > 60) {
            $meta_title = mb_substr($meta_title, 0, 60);
        }
        if (mb_strlen($meta_description) > 160) {
            $meta_description = mb_substr($meta_description, 0, 160);
        }

        $this->update_or_delete_meta($post_id, 'meta_title', $meta_title);
        $this->update_or_delete_meta($post_id, 'meta_description', $meta_description);

        // Schema.org - save as _fp_schema_manual array to match existing structure
        $schema_type = $this->sanitize_text($raw['schema_type'] ?? 'TouristTrip');
        $allowed_types = ['TouristTrip', 'Event', 'Product'];
        if (!in_array($schema_type, $allowed_types, true)) {
            $schema_type = 'TouristTrip';
        }

        $schema_rating = isset($raw['schema_rating']) && is_numeric($raw['schema_rating'])
            ? (float) $raw['schema_rating']
            : null;
        if ($schema_rating !== null) {
            $schema_rating = max(0, min(5, $schema_rating));
        }

        $schema_review_count = $this->sanitize_int($raw['schema_review_count'] ?? 0);

        // Build schema_manual array to match existing structure
        $schema_manual = [];
        if ($schema_type !== 'TouristTrip') {
            $schema_manual['type'] = $schema_type;
        }
        if ($schema_rating !== null && $schema_rating > 0) {
            $schema_manual['rating'] = $schema_rating;
        }
        if ($schema_review_count > 0) {
            $schema_manual['review_count'] = $schema_review_count;
        }

        $this->update_or_delete_meta($post_id, 'schema_manual', !empty($schema_manual) ? $schema_manual : null);
    }

    protected function get_meta_data(int $post_id): array
    {
        // Match existing meta keys from original code
        $meta_title = get_post_meta($post_id, '_fp_meta_title', true);
        $meta_description = get_post_meta($post_id, '_fp_meta_description', true);
        $schema_manual = get_post_meta($post_id, '_fp_schema_manual', true);
        
        // Parse schema_manual if it's an array
        $schema_type = 'TouristTrip';
        $schema_rating = '';
        $schema_review_count = '';
        
        if (is_array($schema_manual)) {
            $schema_type = sanitize_text_field((string) ($schema_manual['type'] ?? 'TouristTrip'));
            $schema_rating = isset($schema_manual['rating']) ? (string) $schema_manual['rating'] : '';
            $schema_review_count = isset($schema_manual['review_count']) ? (string) $schema_manual['review_count'] : '';
        }

        return [
            'meta_title' => sanitize_text_field((string) $meta_title),
            'meta_description' => sanitize_textarea_field((string) $meta_description),
            'schema_type' => $schema_type,
            'schema_rating' => $schema_rating,
            'schema_review_count' => $schema_review_count,
        ];
    }
}

