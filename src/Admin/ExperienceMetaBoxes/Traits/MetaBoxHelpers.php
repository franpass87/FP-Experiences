<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Traits;

use FP_Exp\Utils\LanguageHelper;

use function absint;
use function array_filter;
use function array_map;
use function array_values;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function esc_url;
use function get_post_meta;
use function get_terms;
use function sanitize_key;
use function sanitize_text_field;
use function wp_get_post_terms;
use function wp_list_pluck;
use function wp_unique_id;

/**
 * Trait with common helper methods for Experience Meta Box handlers.
 */
trait MetaBoxHelpers
{
    /**
     * Get assigned taxonomy terms for a post.
     * 
     * @return array<int>
     */
    protected function get_assigned_terms(int $post_id, string $taxonomy): array
    {
        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
        return is_array($terms) ? array_map('absint', $terms) : [];
    }

    /**
     * Get term names by IDs.
     * 
     * @param array<int> $term_ids
     * @return array<int, string>
     */
    protected function get_term_names_by_ids(array $term_ids, string $taxonomy): array
    {
        if (empty($term_ids)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'include' => $term_ids,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return wp_list_pluck($terms, 'name', 'term_id');
    }

    /**
     * Get taxonomy choices for select/checkbox fields.
     * 
     * @return array<int, array{id: int, label: string}>
     */
    protected function get_taxonomy_choices(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $choices = [];
        foreach ($terms as $term) {
            $choices[] = [
                'id' => (int) $term->term_id,
                'label' => $term->name,
            ];
        }

        return $choices;
    }

    /**
     * Render a tooltip trigger (icon + testo in data-tooltip / stile JS admin).
     *
     * @param string $id Attributo id sull'elemento (può coincidere con aria-describedby del campo).
     * @param string $text Testo dell'aiuto (tradotto), usato anche per aria-label.
     */
    protected function render_tooltip(string $id, string $text): void
    {
        ?>
        <span
            class="fp-exp-tooltip"
            data-tooltip="<?php echo esc_attr($text); ?>"
            id="<?php echo esc_attr($id); ?>"
            tabindex="0"
            role="img"
            aria-label="<?php echo esc_attr($text); ?>"
        >
            <span class="dashicons dashicons-info" aria-hidden="true"></span>
        </span>
        <?php
    }

    /**
     * Get hero image data.
     * 
     * @return array{id: int, url: string}
     */
    protected function get_hero_image(int $post_id): array
    {
        $hero_id = absint((string) get_post_meta($post_id, '_fp_hero_image_id', true));
        
        if ($hero_id <= 0) {
            return ['id' => 0, 'url' => ''];
        }

        $url = wp_get_attachment_image_url($hero_id, 'full');
        
        return [
            'id' => $hero_id,
            'url' => $url ?: '',
        ];
    }

    /**
     * Sanitize text field with fallback.
     */
    protected function sanitize_text_field_safe(mixed $value, string $default = ''): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return $default;
        }

        $sanitized = sanitize_text_field((string) $value);
        return $sanitized !== '' ? $sanitized : $default;
    }

    /**
     * Sanitize integer with fallback.
     */
    protected function sanitize_int_safe(mixed $value, int $default = 0): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $int = absint($value);
        return $int > 0 ? $int : $default;
    }

    /**
     * Sanitize array of keys.
     * 
     * @param mixed $value
     * @return array<string>
     */
    protected function sanitize_key_array(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $keys = array_map('sanitize_key', $value);
        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Convert textarea lines (string) to array.
     * 
     * @param mixed $value String or array
     * @return array<string>
     */
    protected function lines_to_array($value): array
    {
        if (is_array($value)) {
            $sanitized = array_map('sanitize_text_field', $value);
            // Filter empty items and "Array" string (corrupted data)
            return array_values(array_filter($sanitized, static function($item) {
                return $item !== '' && strtolower(trim($item)) !== 'array';
            }));
        }

        $string_value = (string) $value;
        
        // Handle corrupted data where "Array" string was saved
        if (strtolower(trim($string_value)) === 'array') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $string_value);
        if (!is_array($lines)) {
            return [];
        }

        $sanitized = array_map('sanitize_text_field', $lines);
        return array_values(array_filter($sanitized, static function($item) {
            return trim($item) !== '';
        }));
    }

    /**
     * Convert array to textarea lines (string).
     * 
     * @param mixed $value Array or string
     * @return string
     */
    protected function array_to_lines($value): string
    {
        if (is_array($value)) {
            $sanitized = array_map('sanitize_text_field', $value);
            // Filter empty items and "Array" string (corrupted data)
            $items = array_values(array_filter($sanitized, static function($item) {
                return $item !== '' && strtolower(trim($item)) !== 'array';
            }));
            return implode("\n", $items);
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    /**
     * Apre una sezione a card (design system FP: `.fp-exp-dms-card` + header con dashicon).
     *
     * @param string $title    Titolo già tradotto (es. da `esc_html__()`).
     * @param string $dashicon Classe dashicons completa (es. `dashicons-info`).
     * @param string $id_suffix Suffisso stabile per id univoci del titolo (es. `details-general`).
     */
    protected function render_metabox_section_open(string $title, string $dashicon, string $id_suffix): void
    {
        $heading_id = 'fp-exp-metabox-' . sanitize_key($id_suffix) . '-' . wp_unique_id();
        ?>
        <div class="fp-exp-dms-card fp-exp-metabox-section" role="group" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
            <div class="fp-exp-dms-card-header">
                <div class="fp-exp-dms-card-header-left">
                    <span class="dashicons <?php echo esc_attr($dashicon); ?>" aria-hidden="true"></span>
                    <h3 class="fp-exp-metabox-section__title" id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($title); ?></h3>
                </div>
            </div>
            <div class="fp-exp-dms-card-body fp-exp-metabox-section__body">
        <?php
    }

    /**
     * Chiude la card aperta con {@see self::render_metabox_section_open()}.
     */
    protected function render_metabox_section_close(): void
    {
        ?>
            </div>
        </div>
        <?php
    }
}

