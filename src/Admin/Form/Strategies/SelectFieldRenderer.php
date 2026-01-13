<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Strategies;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRenderer;

use function esc_attr;
use function esc_html;
use function selected;

/**
 * Renderer for select dropdown fields.
 */
final class SelectFieldRenderer implements FieldRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'select';
    }

    public function render(FieldDefinition $field, string $option_group = ''): string
    {
        $field_id = $field->getFieldId($option_group);
        $input_name = $field->getInputName($option_group);
        $value = $field->value ?? '';
        $choices = $field->getOption('choices', []);
        
        if (!is_array($choices) || empty($choices)) {
            return '<p class="description">' . esc_html__('Nessuna opzione disponibile', 'fp-experiences') . '</p>';
        }

        $attributes = $field->getAttributesString();
        if ($field->hasOption('multiple')) {
            $attributes .= ' multiple';
            $input_name .= '[]';
        }

        $html = sprintf('<select id="%s" name="%s" %s>', esc_attr($field_id), esc_attr($input_name), $attributes);

        foreach ($choices as $choice_value => $choice_label) {
            $is_selected = is_array($value) 
                ? in_array($choice_value, $value, true)
                : selected($value, $choice_value, false);
            
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr((string) $choice_value),
                $is_selected,
                esc_html((string) $choice_label)
            );
        }

        $html .= '</select>';

        if ($field->description) {
            $html .= '<p class="description">' . esc_html($field->description) . '</p>';
        }

        return $html;
    }
}
















