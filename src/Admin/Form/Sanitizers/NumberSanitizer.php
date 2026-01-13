<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use function absint;
use function is_numeric;

/**
 * Sanitizer for number input fields.
 */
final class NumberSanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return $type === 'number';
    }

    public function sanitize(mixed $value, array $options = []): int|float|null
    {
        if (!is_numeric($value) && $value !== '' && $value !== null) {
            return null;
        }

        if ($value === '' || $value === null) {
            return isset($options['default']) && is_numeric($options['default'])
                ? (is_float($options['default']) ? (float) $options['default'] : (int) $options['default'])
                : null;
        }

        // Check if should be float (has step with decimal)
        $is_float = false;
        if (isset($options['step']) && is_numeric($options['step'])) {
            $step = (float) $options['step'];
            $is_float = ($step < 1 && $step > 0) || strpos((string) $step, '.') !== false;
        }

        $numeric_value = $is_float ? (float) $value : (int) $value;

        // Apply min/max constraints
        if (isset($options['min']) && is_numeric($options['min'])) {
            $min = $is_float ? (float) $options['min'] : (int) $options['min'];
            if ($numeric_value < $min) {
                $numeric_value = $min;
            }
        }

        if (isset($options['max']) && is_numeric($options['max'])) {
            $max = $is_float ? (float) $options['max'] : (int) $options['max'];
            if ($numeric_value > $max) {
                $numeric_value = $max;
            }
        }

        return $numeric_value;
    }
}
















