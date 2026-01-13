<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function esc_attr;
use function esc_html;

/**
 * Renderer for text input fields.
 */
final class TextFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return in_array($type, ['text', 'url', 'tel'], true);
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? '';
        $type = $field->type === 'text' ? 'text' : $field->type;
        
        $attributes = $field->getAttributesString();
        if ($field->hasOption('placeholder')) {
            $attributes .= ' placeholder="' . esc_attr((string) $field->getOption('placeholder')) . '"';
        }
        if ($field->hasOption('maxlength')) {
            $attributes .= ' maxlength="' . esc_attr((string) $field->getOption('maxlength')) . '"';
        }

        $html = sprintf(
            '<input type="%s" id="%s" name="%s" value="%s" %s />',
            esc_attr($type),
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
















