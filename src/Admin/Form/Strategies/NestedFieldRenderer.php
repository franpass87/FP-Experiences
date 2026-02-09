<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function esc_attr;
use function esc_html;
use function get_option;

/**
 * Renderer for nested fields (fields with array path like fp_exp_emails[types][customer_confirmation]).
 * 
 * This renderer handles fields that are nested within option arrays,
 * commonly used in WordPress settings pages for complex configurations.
 */
final class NestedFieldRenderer implements FieldRenderer
{
    private static ?\FP_Exp\Admin\Form\FieldRendererFactory $factory = null;

    private function getFactory(): \FP_Exp\Admin\Form\FieldRendererFactory
    {
        if (self::$factory === null) {
            self::$factory = new \FP_Exp\Admin\Form\FieldRendererFactory();
        }
        return self::$factory;
    }

    public function supports(string $type): bool
    {
        return str_starts_with($type, 'nested_');
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $path = $field->getOption('path', []);
        $base_type = $field->getOption('base_type', 'text');
        $option_name = $field->getOption('option_name', '');
        
        if (empty($path) || !is_array($path)) {
            return '<p class="description">' . esc_html__('Path non valido per campo annidato', 'fp-experiences') . '</p>';
        }

        // Get current value from nested structure
        $settings = get_option($option_name, []);
        $settings = is_array($settings) ? $settings : [];
        
        // Navigate through the path to get the value
        $cursor = $settings;
        $path_exists = true;
        $path_count = count($path);
        $current_index = 0;
        
        foreach ($path as $segment) {
            $current_index++;
            $is_last_segment = ($current_index === $path_count);
            
            if (!isset($cursor[$segment])) {
                // If the path doesn't exist, the value doesn't exist
                $path_exists = false;
                break;
            }
            
            // If this is the last segment, the value can be any type (string, number, etc.)
            // Otherwise, it must be an array to continue navigating
            if ($is_last_segment) {
                // Last segment - value can be any type
                $cursor = $cursor[$segment];
            } else {
                // Not the last segment - must be an array to continue
                if (!is_array($cursor[$segment])) {
                    $path_exists = false;
                    break;
                }
                $cursor = $cursor[$segment];
            }
        }
        
        // If path doesn't exist, use empty string or field value
        if (!$path_exists) {
            $value = $field->value ?? '';
        } elseif (is_array($cursor)) {
            // If cursor is an array, use empty string (prevents "Array" being displayed in inputs)
            $value = '';
        } else {
            $value = $cursor ?? ($field->value ?? '');
        }

        // Build nested field name (e.g., fp_exp_emails[types][customer_confirmation])
        $field_name = $option_name;
        foreach ($path as $segment) {
            $field_name .= '[' . esc_attr((string) $segment) . ']';
        }

        // Debug logging per verificare il nome del campo generato
        if (defined('WP_DEBUG') && WP_DEBUG && $option_name === 'fp_exp_rtb') {
            error_log('[FP-Exp NestedFieldRenderer] Field name: ' . $field_name . ', base_type: ' . $base_type . ', value: ' . print_r($value, true));
        }

        // Get the base renderer for the actual field type
        try {
            $base_renderer = $this->getFactory()->getRenderer($base_type);
        } catch (\InvalidArgumentException $e) {
            return '<p class="description">' . esc_html__('Tipo di campo non supportato', 'fp-experiences') . '</p>';
        }

        // Merge field options with nested-specific options
        $merged_options = array_merge($field->options, [
            'nested' => true,
        ]);
        
        // Pass through min/max/step/placeholder for number fields
        if ($base_type === 'number') {
            if ($field->hasOption('min')) {
                $merged_options['min'] = $field->getOption('min');
            }
            if ($field->hasOption('max')) {
                $merged_options['max'] = $field->getOption('max');
            }
            if ($field->hasOption('step')) {
                $merged_options['step'] = $field->getOption('step');
            }
            if ($field->hasOption('placeholder')) {
                $merged_options['placeholder'] = $field->getOption('placeholder');
            }
        }

        // Create a new field definition with the nested name and value
        $nested_field = new FieldDefinition(
            name: $field_name,
            type: $base_type,
            label: $field->label,
            value: $value,
            options: $merged_options,
            description: $field->description,
            required: $field->required,
            attributes: $field->attributes
        );

        // Render using base renderer but with nested name
        $rendered = $base_renderer->render($nested_field, '');

        // For toggle nested fields (single, not arrays), add hidden input to ensure value is always sent
        // This is important because unchecked checkboxes don't send any value in POST
        // Note: We only add hidden input for single toggles, not for array checkboxes (which use [])
        if ($base_type === 'toggle') {
            // Check if this is an array field (ends with []) - if so, don't add hidden input
            // Use substr for PHP 7.4+ compatibility (str_ends_with requires PHP 8.0+)
            $is_array_field = substr($field_name, -2) === '[]';
            if (!$is_array_field) {
                $hidden_name = $field_name;
                $rendered = '<input type="hidden" name="' . esc_attr($hidden_name) . '" value="0" />' . $rendered;
            }
        }

        return $rendered;
    }
}

