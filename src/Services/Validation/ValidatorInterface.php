<?php

declare(strict_types=1);

namespace FP_Exp\Services\Validation;

/**
 * Validation result.
 */
final class ValidationResult
{
    private bool $valid;
    private array $errors;

    /**
     * @param bool $valid Whether validation passed
     * @param array<string, string> $errors Validation errors (field => message)
     */
    public function __construct(bool $valid, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}

/**
 * Validator service interface.
 */
interface ValidatorInterface
{
    /**
     * Validate data against rules.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|array<string>> $rules Validation rules
     * @return ValidationResult Validation result
     */
    public function validate(array $data, array $rules): ValidationResult;

    /**
     * Validate a single field value.
     *
     * @param mixed $value Value to validate
     * @param string $rule Validation rule
     * @return bool True if valid, false otherwise
     */
    public function validateField($value, string $rule): bool;
}



