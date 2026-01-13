<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form;

/**
 * Interface for field renderers.
 * 
 * Each field type should have its own renderer implementing this interface.
 */
interface FieldRenderer
{
    /**
     * Render the field HTML.
     */
    public function render(FieldDefinition $field, string $option_group = ''): string;

    /**
     * Check if this renderer supports the given field type.
     */
    public function supports(string $type): bool;
}
















