<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use FP_Exp\Admin\Form\Sanitizers\ArraySanitizer;
use FP_Exp\Admin\Form\Sanitizers\EmailSanitizer;
use FP_Exp\Admin\Form\Sanitizers\NumberSanitizer;
use FP_Exp\Admin\Form\Sanitizers\SelectSanitizer;
use FP_Exp\Admin\Form\Sanitizers\TextareaSanitizer;
use FP_Exp\Admin\Form\Sanitizers\TextSanitizer;
use FP_Exp\Admin\Form\Sanitizers\ToggleSanitizer;

/**
 * Factory for creating appropriate field sanitizers.
 */
final class SanitizerFactory
{
    /**
     * @var array<string, Sanitizer>
     */
    private array $sanitizers = [];

    public function __construct()
    {
        // Register default sanitizers
        $this->registerDefaultSanitizers();
    }

    /**
     * Get sanitizer for field type.
     */
    public function getSanitizer(string $type): Sanitizer
    {
        if (isset($this->sanitizers[$type])) {
            return $this->sanitizers[$type];
        }

        // Try to find sanitizer that supports this type
        foreach ($this->sanitizers as $sanitizer) {
            if ($sanitizer->supports($type)) {
                return $sanitizer;
            }
        }

        // Default to text sanitizer if no match found
        return $this->sanitizers['text'] ?? new TextSanitizer();
    }

    /**
     * Register a custom sanitizer.
     */
    public function registerSanitizer(Sanitizer $sanitizer, string $type): void
    {
        $this->sanitizers[$type] = $sanitizer;
    }

    /**
     * Register default sanitizers.
     */
    private function registerDefaultSanitizers(): void
    {
        $defaults = [
            new TextSanitizer(),
            new TextareaSanitizer(),
            new EmailSanitizer(),
            new NumberSanitizer(),
            new SelectSanitizer(),
            new ToggleSanitizer(),
            new ArraySanitizer(),
        ];

        foreach ($defaults as $sanitizer) {
            // Register by supported types
            $types = ['text', 'textarea', 'email', 'number', 'select', 'toggle', 'checkbox', 'array'];
            foreach ($types as $type) {
                if ($sanitizer->supports($type)) {
                    $this->sanitizers[$type] = $sanitizer;
                }
            }
        }
    }
}
















