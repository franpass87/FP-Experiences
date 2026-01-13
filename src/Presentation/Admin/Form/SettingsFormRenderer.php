<?php

declare(strict_types=1);

namespace FP_Exp\Presentation\Admin\Form;

use FP_Exp\Presentation\Admin\Form\FormRendererInterface;

/**
 * Form renderer for settings page.
 */
final class SettingsFormRenderer implements FormRendererInterface
{
    /**
     * Render a form field.
     *
     * @param string $type Field type
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $args Field arguments
     * @return string Rendered HTML
     */
    public function renderField(string $type, string $name, $value, array $args = []): string
    {
        $args = array_merge([
            'label' => '',
            'description' => '',
            'options' => [],
            'attributes' => [],
        ], $args);

        $output = '<tr>';
        $output .= '<th scope="row">';
        if (!empty($args['label'])) {
            $output .= '<label for="' . esc_attr($name) . '">' . esc_html($args['label']) . '</label>';
        }
        $output .= '</th>';
        $output .= '<td>';

        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                $output .= $this->renderInputField($type, $name, $value, $args);
                break;
            case 'textarea':
                $output .= $this->renderTextareaField($name, $value, $args);
                break;
            case 'checkbox':
                $output .= $this->renderCheckboxField($name, $value, $args);
                break;
            case 'select':
                $output .= $this->renderSelectField($name, $value, $args);
                break;
            default:
                $output .= $this->renderInputField('text', $name, $value, $args);
        }

        if (!empty($args['description'])) {
            $output .= '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }

        $output .= '</td>';
        $output .= '</tr>';

        return $output;
    }

    /**
     * Render a complete form section.
     *
     * @param string $sectionId Section ID
     * @param string $title Section title
     * @param array<string, mixed> $fields Section fields
     * @return string Rendered HTML
     */
    public function renderSection(string $sectionId, string $title, array $fields): string
    {
        $output = '<div class="fp-exp-settings-section" id="' . esc_attr($sectionId) . '">';
        $output .= '<h2>' . esc_html($title) . '</h2>';
        $output .= '<table class="form-table" role="presentation">';
        $output .= '<tbody>';

        foreach ($fields as $field) {
            $output .= $this->renderField(
                $field['type'] ?? 'text',
                $field['name'] ?? '',
                $field['value'] ?? '',
                $field['args'] ?? []
            );
        }

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render input field.
     *
     * @param string $type Input type
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $args Field arguments
     * @return string Rendered HTML
     */
    private function renderInputField(string $type, string $name, $value, array $args): string
    {
        $attributes = $args['attributes'] ?? [];
        $attributes['type'] = $type;
        $attributes['id'] = $name;
        $attributes['name'] = $name;
        $attributes['value'] = $value;

        $attrs = [];
        foreach ($attributes as $key => $val) {
            if ($val !== null) {
                $attrs[] = esc_attr($key) . '="' . esc_attr((string) $val) . '"';
            }
        }

        return '<input ' . implode(' ', $attrs) . ' />';
    }

    /**
     * Render textarea field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $args Field arguments
     * @return string Rendered HTML
     */
    private function renderTextareaField(string $name, $value, array $args): string
    {
        $attributes = $args['attributes'] ?? [];
        $attributes['id'] = $name;
        $attributes['name'] = $name;
        $rows = $attributes['rows'] ?? 5;
        $cols = $attributes['cols'] ?? 50;

        $attrs = [];
        foreach ($attributes as $key => $val) {
            if ($key !== 'rows' && $key !== 'cols' && $val !== null) {
                $attrs[] = esc_attr($key) . '="' . esc_attr((string) $val) . '"';
            }
        }

        $attrs[] = 'rows="' . esc_attr((string) $rows) . '"';
        $attrs[] = 'cols="' . esc_attr((string) $cols) . '"';

        return '<textarea ' . implode(' ', $attrs) . '>' . esc_textarea((string) $value) . '</textarea>';
    }

    /**
     * Render checkbox field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $args Field arguments
     * @return string Rendered HTML
     */
    private function renderCheckboxField(string $name, $value, array $args): string
    {
        $checked = checked($value, true, false);
        $label = $args['label'] ?? '';

        return '<label for="' . esc_attr($name) . '">'
            . '<input type="checkbox" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="1" ' . $checked . ' /> '
            . esc_html($label)
            . '</label>';
    }

    /**
     * Render select field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $args Field arguments
     * @return string Rendered HTML
     */
    private function renderSelectField(string $name, $value, array $args): string
    {
        $options = $args['options'] ?? [];
        $attributes = $args['attributes'] ?? [];
        $attributes['id'] = $name;
        $attributes['name'] = $name;

        $attrs = [];
        foreach ($attributes as $key => $val) {
            if ($val !== null) {
                $attrs[] = esc_attr($key) . '="' . esc_attr((string) $val) . '"';
            }
        }

        $output = '<select ' . implode(' ', $attrs) . '>';
        foreach ($options as $optionValue => $optionLabel) {
            $selected = selected($value, $optionValue, false);
            $output .= '<option value="' . esc_attr($optionValue) . '" ' . $selected . '>' . esc_html($optionLabel) . '</option>';
        }
        $output .= '</select>';

        return $output;
    }
}







