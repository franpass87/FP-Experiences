<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function esc_attr;
use function esc_html;

/**
 * Renderer for email input fields.
 */
final class EmailFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'email';
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? '';
        
        $attributes = $field->getAttributesString();
        if ($field->hasOption('placeholder')) {
            $attributes .= ' placeholder="' . esc_attr((string) $field->getOption('placeholder')) . '"';
        }
        if ($field->hasOption('multiple')) {
            $attributes .= ' multiple';
        }

        $html = sprintf(
            '<input type="email" id="%s" name="%s" value="%s" %s />',
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
















