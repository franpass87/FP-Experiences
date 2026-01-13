<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Traits;

use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Admin\Form\FieldRendererFactory;

use function esc_attr;
use function esc_html;

/**
 * Trait for rendering form fields using the Field Renderer Strategy pattern.
 * 
 * Provides helper methods to easily render form fields in admin pages.
 */
trait FormFieldRenderer
{
    private ?FieldRendererFactory $field_renderer_factory = null;

    /**
     * Get field renderer factory (lazy initialization).
     */
    protected function getFieldRendererFactory(): FieldRendererFactory
    {
        if ($this->field_renderer_factory === null) {
            $this->field_renderer_factory = new FieldRendererFactory();
        }

        return $this->field_renderer_factory;
    }

    /**
     * Render a form field using the field renderer strategy.
     * 
     * @param string $name Field name
     * @param string $type Field type
     * @param string $label Field label
     * @param mixed $value Current value
     * @param array<string, mixed> $options Additional options
     * @param string $option_group Option group name (for settings)
     * @return string HTML output
     */
    protected function render_form_field(
        string $name,
        string $type,
        string $label,
        mixed $value = null,
        array $options = [],
        string $option_group = ''
    ): string {
        $field = new FieldDefinition(
            name: $name,
            type: $type,
            label: $label,
            value: $value,
            options: $options,
            description: $options['description'] ?? null,
            required: $options['required'] ?? false,
            attributes: $options['attributes'] ?? []
        );

        $renderer = $this->getFieldRendererFactory()->getRenderer($type);
        $field_html = $renderer->render($field, $option_group);

        return $this->wrap_field($field, $field_html, $option_group);
    }

    /**
     * Wrap field in table row format (WordPress admin style).
     * 
     * @param FieldDefinition $field Field definition
     * @param string $field_html Rendered field HTML
     * @param string $option_group Option group name
     * @param bool $skip_label Whether to skip the label column (for inline fields)
     * @return string HTML output
     */
    protected function wrap_field(FieldDefinition $field, string $field_html, string $option_group = '', bool $skip_label = false): string
    {
        $field_id = $field->getFieldId($option_group);
        $required_mark = $field->required ? ' <span class="required">*</span>' : '';

        $html = '<tr>';
        
        if (!$skip_label && !empty($field->label)) {
            $html .= sprintf(
                '<th scope="row"><label for="%s">%s%s</label></th>',
                esc_attr($field_id),
                esc_html($field->label),
                $required_mark
            );
            $html .= '<td>' . $field_html . '</td>';
        } else {
            // For fields without label or inline fields, span both columns
            $html .= '<td colspan="2">' . $field_html . '</td>';
        }
        
        $html .= '</tr>';

        return $html;
    }

    /**
     * Render multiple fields at once.
     * 
     * @param array<int, array<string, mixed>> $fields Array of field definitions
     * @param string $option_group Option group name
     * @return string HTML output
     */
    protected function render_form_fields(array $fields, string $option_group = ''): string
    {
        $html = '';

        foreach ($fields as $field_config) {
            $html .= $this->render_form_field(
                $field_config['name'] ?? '',
                $field_config['type'] ?? 'text',
                $field_config['label'] ?? '',
                $field_config['value'] ?? null,
                $field_config['options'] ?? [],
                $option_group
            );
        }

        return $html;
    }
}

