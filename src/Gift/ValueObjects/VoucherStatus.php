<?php

declare(strict_types=1);

namespace FP_Exp\Gift\ValueObjects;

use InvalidArgumentException;

use function in_array;
use function sanitize_key;

/**
 * Value Object representing a voucher status.
 *
 * Ensures status values are always valid and provides status-related logic.
 */
final class VoucherStatus
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const REDEEMED = 'redeemed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /**
     * @var array<int, string>
     */
    private const VALID_STATUSES = [
        self::PENDING,
        self::ACTIVE,
        self::REDEEMED,
        self::CANCELLED,
        self::EXPIRED,
    ];

    /**
     * @var array<int, string>
     */
    private const REDEEMABLE_STATUSES = [
        self::ACTIVE,
    ];

    /**
     * @var array<int, string>
     */
    private const FINAL_STATUSES = [
        self::REDEEMED,
        self::CANCELLED,
        self::EXPIRED,
    ];

    private string $status;

    /**
     * @throws InvalidArgumentException If status is invalid.
     */
    public function __construct(string $status)
    {
        $sanitized = sanitize_key($status);

        if (! in_array($sanitized, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid voucher status: %s', $status)
            );
        }

        $this->status = $sanitized;
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $status): self
    {
        return new self($status);
    }

    /**
     * Create pending status.
     */
    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    /**
     * Create active status.
     */
    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    /**
     * Create redeemed status.
     */
    public static function redeemed(): self
    {
        return new self(self::REDEEMED);
    }

    /**
     * Create cancelled status.
     */
    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    /**
     * Create expired status.
     */
    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    /**
     * Get the status as string.
     */
    public function toString(): string
    {
        return $this->status;
    }

    /**
     * Get the status as string (magic method).
     */
    public function __toString(): string
    {
        return $this->status;
    }

    /**
     * Check if status is valid.
     */
    public function isValid(): bool
    {
        return in_array($this->status, self::VALID_STATUSES, true);
    }

    /**
     * Check if voucher can be redeemed.
     */
    public function canBeRedeemed(): bool
    {
        return in_array($this->status, self::REDEEMABLE_STATUSES, true);
    }

    /**
     * Check if status is pending.
     */
    public function isPending(): bool
    {
        return self::PENDING === $this->status;
    }

    /**
     * Check if status is active.
     */
    public function isActive(): bool
    {
        return self::ACTIVE === $this->status;
    }

    /**
     * Check if status is redeemed.
     */
    public function isRedeemed(): bool
    {
        return self::REDEEMED === $this->status;
    }

    /**
     * Check if status is cancelled.
     */
    public function isCancelled(): bool
    {
        return self::CANCELLED === $this->status;
    }

    /**
     * Check if status is expired.
     */
    public function isExpired(): bool
    {
        return self::EXPIRED === $this->status;
    }

    /**
     * Check if status is final (cannot be changed).
     */
    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /**
     * Check if two statuses are equal.
     */
    public function equals(VoucherStatus $other): bool
    {
        return $this->status === $other->status;
    }

    /**
     * Get the status value.
     */
    public function getValue(): string
    {
        return $this->status;
    }

    /**
     * Get all valid statuses.
     *
     * @return array<int, string>
     */
    public static function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }
}















