<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function esc_attr;
use function esc_html;

/**
 * Renderer for number input fields.
 */
final class NumberFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'number';
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? '';
        
        $attributes = $field->getAttributesString();
        if ($field->hasOption('min')) {
            $attributes .= ' min="' . esc_attr((string) $field->getOption('min')) . '"';
        }
        if ($field->hasOption('max')) {
            $attributes .= ' max="' . esc_attr((string) $field->getOption('max')) . '"';
        }
        if ($field->hasOption('step')) {
            $attributes .= ' step="' . esc_attr((string) $field->getOption('step')) . '"';
        }

        $html = sprintf(
            '<input type="number" id="%s" name="%s" value="%s" %s />',
            esc_attr($field_id),
            esc_attr($input_name),
            esc_attr((string) $value),
            $attributes
        );

        if ($field->description) {
            $html .= '<p class="description">' . esc_html($field->description) . '</p>';
        }

        return $html;
    }
}
















