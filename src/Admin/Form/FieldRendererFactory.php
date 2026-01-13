<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form;

use FP_Exp\Admin\Form\Strategies\CheckboxFieldRenderer;
use FP_Exp\Admin\Form\Strategies\EmailFieldRenderer;
use FP_Exp\Admin\Form\Strategies\NestedFieldRenderer;
use FP_Exp\Admin\Form\Strategies\NumberFieldRenderer;
use FP_Exp\Admin\Form\Strategies\SelectFieldRenderer;
use FP_Exp\Admin\Form\Strategies\TextareaFieldRenderer;
use FP_Exp\Admin\Form\Strategies\TextFieldRenderer;
use FP_Exp\Admin\Form\Strategies\ToggleFieldRenderer;

/**
 * Factory for creating appropriate field renderers.
 */
final class FieldRendererFactory
{
    /**
     * @var array<string, FieldRenderer>
     */
    private array $renderers = [];

    public function __construct()
    {
        // Register default renderers
        $this->registerDefaultRenderers();
    }

    /**
     * Get renderer for field type.
     */
    public function getRenderer(string $type): FieldRenderer
    {
        if (isset($this->renderers[$type])) {
            return $this->renderers[$type];
        }

        // Try to find renderer that supports this type
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($type)) {
                return $renderer;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('No renderer found for field type: %s', $type)
        );
    }

    /**
     * Register a custom renderer.
     */
    public function registerRenderer(FieldRenderer $renderer, string $type): void
    {
        $this->renderers[$type] = $renderer;
    }

    /**
     * Register default renderers.
     */
    private function registerDefaultRenderers(): void
    {
        $defaults = [
            new TextFieldRenderer(),
            new TextareaFieldRenderer(),
            new EmailFieldRenderer(),
            new NumberFieldRenderer(),
            new SelectFieldRenderer(),
            new CheckboxFieldRenderer(),
            new ToggleFieldRenderer(),
            new NestedFieldRenderer(),
        ];

        foreach ($defaults as $renderer) {
            // Register by supported types
            $types = ['text', 'textarea', 'email', 'number', 'select', 'checkbox', 'toggle', 'nested_text', 'nested_number', 'nested_toggle'];
            foreach ($types as $type) {
                if ($renderer->supports($type)) {
                    $this->renderers[$type] = $renderer;
                }
            }
        }
    }
}

