<?php

declare(strict_types=1);

namespace FP_Exp\Presentation\Admin\Form;

/**
 * Interface for form renderers.
 */
interface FormRendererInterface
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
    public function renderField(string $type, string $name, $value, array $args = []): string;

    /**
     * Render a complete form section.
     *
     * @param string $sectionId Section ID
     * @param string $title Section title
     * @param array<string, mixed> $fields Section fields
     * @return string Rendered HTML
     */
    public function renderSection(string $sectionId, string $title, array $fields): string;
}







