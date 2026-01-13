<?php

declare(strict_types=1);

namespace FP_Exp\Services\Validation;

use function absint;
use function filter_var;
use function is_array;
use function is_email;
use function is_numeric;
use function sanitize_email;
use function strlen;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

/**
 * Validator service implementation.
 */
final class Validator implements ValidatorInterface
{
    /**
     * Validate data against rules.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|array<string>> $rules Validation rules
     * @return ValidationResult Validation result
     */
    public function validate(array $data, array $rules): ValidationResult
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleArray = is_array($rule) ? $rule : explode('|', (string) $rule);

            foreach ($ruleArray as $singleRule) {
                $ruleParts = explode(':', (string) $singleRule, 2);
                $ruleName = trim($ruleParts[0]);
                $ruleParam = isset($ruleParts[1]) ? trim($ruleParts[1]) : null;

                if (!$this->validateField($value, $ruleName . ($ruleParam ? ':' . $ruleParam : ''))) {
                    $errors[$field] = $this->getErrorMessage($field, $ruleName, $ruleParam);
                    break; // Stop at first error for this field
                }
            }
        }

        return new ValidationResult(empty($errors), $errors);
    }

    /**
     * Validate a single field value.
     *
     * @param mixed $value Value to validate
     * @param string $rule Validation rule
     * @return bool True if valid, false otherwise
     */
    public function validateField($value, string $rule): bool
    {
        $ruleParts = explode(':', $rule, 2);
        $ruleName = trim($ruleParts[0]);
        $ruleParam = isset($ruleParts[1]) ? trim($ruleParts[1]) : null;

        switch ($ruleName) {
            case 'required':
                return !empty($value) || (is_numeric($value) && $value == 0);

            case 'email':
                return empty($value) || is_email($value);

            case 'url':
                return empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'numeric':
                return empty($value) || is_numeric($value);

            case 'integer':
                return empty($value) || is_int($value) || (is_string($value) && ctype_digit($value));

            case 'min':
                if ($ruleParam === null) {
                    return true;
                }
                $min = (int) $ruleParam;
                if (is_numeric($value)) {
                    return (float) $value >= $min;
                }
                if (is_string($value)) {
                    return strlen($value) >= $min;
                }
                return false;

            case 'max':
                if ($ruleParam === null) {
                    return true;
                }
                $max = (int) $ruleParam;
                if (is_numeric($value)) {
                    return (float) $value <= $max;
                }
                if (is_string($value)) {
                    return strlen($value) <= $max;
                }
                return false;

            case 'array':
                return empty($value) || is_array($value);

            default:
                return true; // Unknown rule, assume valid
        }
    }

    /**
     * Get error message for a validation rule.
     *
     * @param string $field Field name
     * @param string $rule Rule name
     * @param string|null $param Rule parameter
     * @return string Error message
     */
    private function getErrorMessage(string $field, string $rule, ?string $param): string
    {
        $messages = [
            'required' => sprintf('Field "%s" is required', $field),
            'email' => sprintf('Field "%s" must be a valid email', $field),
            'url' => sprintf('Field "%s" must be a valid URL', $field),
            'numeric' => sprintf('Field "%s" must be numeric', $field),
            'integer' => sprintf('Field "%s" must be an integer', $field),
            'min' => sprintf('Field "%s" must be at least %s', $field, $param ?? '0'),
            'max' => sprintf('Field "%s" must be at most %s', $field, $param ?? '0'),
            'array' => sprintf('Field "%s" must be an array', $field),
        ];

        return $messages[$rule] ?? sprintf('Field "%s" is invalid', $field);
    }
}



