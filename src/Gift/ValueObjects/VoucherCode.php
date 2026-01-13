<?php

declare(strict_types=1);

namespace FP_Exp\Gift\ValueObjects;

use Exception;
use InvalidArgumentException;

use function bin2hex;
use function random_bytes;
use function sanitize_key;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Value Object representing a voucher code.
 *
 * Ensures voucher codes are always valid, sanitized, and immutable.
 */
final class VoucherCode
{
    private string $code;

    /**
     * @throws InvalidArgumentException If code is invalid.
     */
    public function __construct(string $code)
    {
        $sanitized = sanitize_key($code);

        if ('' === $sanitized) {
            throw new InvalidArgumentException('Voucher code cannot be empty.');
        }

        if (strlen($sanitized) > 32) {
            throw new InvalidArgumentException('Voucher code cannot exceed 32 characters.');
        }

        $this->code = strtoupper($sanitized);
    }

    /**
     * Generate a new random voucher code.
     */
    public static function generate(): self
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (Exception $exception) {
            $random = bin2hex(random_bytes(8));
        }

        $code = strtoupper(substr($random, 0, 32));

        return new self($code);
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $code): self
    {
        return new self($code);
    }

    /**
     * Get the code as string.
     */
    public function toString(): string
    {
        return $this->code;
    }

    /**
     * Get the code as string (magic method).
     */
    public function __toString(): string
    {
        return $this->code;
    }

    /**
     * Check if two codes are equal.
     */
    public function equals(VoucherCode $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * Get the code value.
     */
    public function getValue(): string
    {
        return $this->code;
    }
}















