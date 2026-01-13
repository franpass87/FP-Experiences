<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function esc_attr;
use function esc_html;
use function esc_textarea;

/**
 * Renderer for textarea fields.
 */
final class TextareaFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'textarea';
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? '';
        
        $attributes = $field->getAttributesString();
        if ($field->hasOption('rows')) {
            $attributes .= ' rows="' . esc_attr((string) $field->getOption('rows', 5)) . '"';
        }
        if ($field->hasOption('cols')) {
            $attributes .= ' cols="' . esc_attr((string) $field->getOption('cols', 50)) . '"';
        }
        if ($field->hasOption('placeholder')) {
            $attributes .= ' placeholder="' . esc_attr((string) $field->getOption('placeholder')) . '"';
        }

        $html = sprintf(
            '<textarea id="%s" name="%s" %s>%s</textarea>',
            esc_attr($field_id),
            esc_attr($input_name),
            $attributes,
            esc_textarea((string) $value)
        );

        if ($field->description) {
            $html .= '<p class="description">' . esc_html($field->description) . '</p>';
        }

        return $html;
    }
}
















