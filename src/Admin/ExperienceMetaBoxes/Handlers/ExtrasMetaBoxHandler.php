<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;

use function esc_attr;
use function esc_html;
use function esc_textarea;

/**
 * Handler for Extras tab in Experience Meta Box.
 * 
 * Handles extra services, add-ons, and additional options for experiences.
 */
final class ExtrasMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-extras-panel';
        $highlights = $data['highlights'] ?? '';
        $inclusions = $data['inclusions'] ?? '';
        $exclusions = $data['exclusions'] ?? '';
        $what_to_bring = $data['what_to_bring'] ?? '';
        $notes = $data['notes'] ?? '';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-extras"
            data-tab-panel="extras"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e("Cosa include l'esperienza", 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-highlights">
                        <?php esc_html_e('Highlight (uno per riga)', 'fp-experiences'); ?>
                    </label>
                    <textarea 
                        id="fp-exp-highlights" 
                        name="fp_exp_extras[highlights]" 
                        rows="4" 
                        placeholder="<?php echo esc_attr__('Accesso prioritario&#10;Guida certificata&#10;Piccoli gruppi', 'fp-experiences'); ?>"
                    ><?php echo esc_textarea($highlights); ?></textarea>
                </div>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-inclusions">
                            <?php esc_html_e('Incluso (uno per riga)', 'fp-experiences'); ?>
                        </label>
                        <textarea id="fp-exp-inclusions" name="fp_exp_extras[inclusions]" rows="4"><?php echo esc_textarea($inclusions); ?></textarea>
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-exclusions">
                            <?php esc_html_e('Non incluso (uno per riga)', 'fp-experiences'); ?>
                        </label>
                        <textarea id="fp-exp-exclusions" name="fp_exp_extras[exclusions]" rows="4"><?php echo esc_textarea($exclusions); ?></textarea>
                    </div>
                </div>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-what-to-bring">
                        <?php esc_html_e('Cosa portare', 'fp-experiences'); ?>
                    </label>
                    <textarea id="fp-exp-what-to-bring" name="fp_exp_extras[what_to_bring]" rows="3"><?php echo esc_textarea($what_to_bring); ?></textarea>
                </div>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-notes">
                        <?php esc_html_e('Note aggiuntive', 'fp-experiences'); ?>
                    </label>
                    <textarea id="fp-exp-notes" name="fp_exp_extras[notes]" rows="3"><?php echo esc_textarea($notes); ?></textarea>
                </div>
            </fieldset>
        </section>
        <?php
    }


    protected function save_meta_data(int $post_id, array $raw): void
    {
        // Convert textarea lines to arrays (matching existing code)
        $highlights = isset($raw['highlights']) ? $this->lines_to_array($raw['highlights']) : [];
        $inclusions = isset($raw['inclusions']) ? $this->lines_to_array($raw['inclusions']) : [];
        $exclusions = isset($raw['exclusions']) ? $this->lines_to_array($raw['exclusions']) : [];
        $what_to_bring = isset($raw['what_to_bring']) ? $this->lines_to_array($raw['what_to_bring']) : [];
        $notes = isset($raw['notes']) ? $this->lines_to_array($raw['notes']) : [];

        $this->update_or_delete_meta($post_id, 'highlights', $highlights);
        $this->update_or_delete_meta($post_id, 'inclusions', $inclusions);
        $this->update_or_delete_meta($post_id, 'exclusions', $exclusions);
        $this->update_or_delete_meta($post_id, 'what_to_bring', $what_to_bring);
        $this->update_or_delete_meta($post_id, 'notes', $notes);
    }

    protected function get_meta_data(int $post_id): array
    {
        // Get meta values and convert arrays to lines (matching existing code)
        $highlights = get_post_meta($post_id, '_fp_highlights', true);
        $inclusions = get_post_meta($post_id, '_fp_inclusions', true);
        $exclusions = get_post_meta($post_id, '_fp_exclusions', true);
        $what_to_bring = get_post_meta($post_id, '_fp_what_to_bring', true);
        $notes = get_post_meta($post_id, '_fp_notes', true);

        return [
            'highlights' => $this->array_to_lines($highlights),
            'inclusions' => $this->array_to_lines($inclusions),
            'exclusions' => $this->array_to_lines($exclusions),
            'what_to_bring' => $this->array_to_lines($what_to_bring),
            'notes' => $this->array_to_lines($notes),
        ];
    }
}

