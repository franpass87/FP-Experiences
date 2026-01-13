<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function checked;
use function esc_attr;
use function esc_html;

/**
 * Renderer for checkbox fields.
 */
final class CheckboxFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'checkbox';
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? false;
        $checkbox_value = $field->getOption('value', '1');
        
        $attributes = $field->getAttributesString();
        // Remove 'required' from attributes for checkbox (handled differently)
        $attributes = str_replace('required="required"', '', $attributes);
        $attributes = trim($attributes);

        $html = sprintf(
            '<label for="%s">',
            esc_attr($field_id)
        );

        $html .= sprintf(
            '<input type="checkbox" id="%s" name="%s" value="%s" %s %s />',
            esc_attr($field_id),
            esc_attr($input_name),
            esc_attr((string) $checkbox_value),
            checked($value, $checkbox_value, false),
            $attributes
        );

        $html .= ' ' . esc_html($field->label);
        $html .= '</label>';

        if ($field->description) {
            $html .= '<p class="description">' . esc_html($field->description) . '</p>';
        }

        return $html;
    }
}
















