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
        
        $html = '';
        
        // Add hidden input if requested (for WordPress settings API)
        // This ensures the value is sent even when checkbox is unchecked
        if ($field->getOption('add_hidden_input', false)) {
            $html .= '<input type="hidden" name="' . esc_attr($input_name) . '" value="0" />';
        }
        
        // Structure:
        // <label class="fp-exp-toggle">
        //     <input type="checkbox" class="fp-exp-toggle__input" />
        //     <span class="fp-exp-toggle__switch"></span>
        //     <span class="fp-exp-toggle__label">Label text</span>
        // </label>
        // The checkbox is hidden via CSS (opacity:0) but remains clickable
        // The switch has pointer-events:none so clicks go through to checkbox
        
        $html .= '<label class="' . esc_attr($toggle_class) . '" for="' . esc_attr($field_id) . '">';
        // Hide checkbox with inline style to ensure it works regardless of CSS specificity
        // Uses sr-only technique: hidden visually but accessible and clickable via label
        $html .= sprintf(
            '<input type="checkbox" id="%s" name="%s" value="yes" %s class="fp-exp-toggle__input" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" />',
            esc_attr($field_id),
            esc_attr($input_name),
            checked($is_checked, true, false)
        );
        $html .= '<span class="fp-exp-toggle__switch"></span>';
        
        // Add label text if provided in options
        if ($field->hasOption('label_text')) {
            $html .= '<span class="fp-exp-toggle__label">' . esc_html((string) $field->getOption('label_text')) . '</span>';
        }
        $html .= '</label>';

        if ($field->description) {
            $html .= '<p class="description">' . esc_html($field->description) . '</p>';
        }

        return $html;
    }
}

