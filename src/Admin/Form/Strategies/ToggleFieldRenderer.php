<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function checked;
use function esc_attr;
use function esc_html;

/**
 * Renderer for toggle/switch fields (custom checkbox style).
 */
final class ToggleFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'toggle';
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? 'no';
        $is_checked = in_array($value, ['yes', '1', 1, true], true);
        
        // Get custom class from options or use default
        $toggle_class = $field->getOption('toggle_class', 'fp-exp-toggle');
        
        $html = '<label class="' . esc_attr($toggle_class) . '" for="' . esc_attr($field_id) . '">';
        $html .= sprintf(
            '<input type="checkbox" id="%s" name="%s" value="yes" %s class="fp-exp-toggle__input" />',
            esc_attr($field_id),
            esc_attr($input_name),
            checked($is_checked, true, false)
        );
        $html .= '<span class="fp-exp-toggle__switch"></span>';
        
        // Add label text if provided in options
        if ($field->hasOption('label_text')) {
            $html .= ' <span>' . esc_html((string) $field->getOption('label_text')) . '</span>';
        }
        
        $html .= '</label>';

        if ($field->description) {
            $html .= '<p class="description">' . esc_html($field->description) . '</p>';
        }

        return $html;
    }
}

