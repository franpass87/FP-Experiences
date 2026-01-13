<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\ValueObjects;

use InvalidArgumentException;

use function absint;
use function array_filter;
use function array_sum;
use function is_array;
use function is_numeric;

/**
 * Value Object representing slot capacity (total and per type).
 */
final class SlotCapacity
{
    private int $total;
    /**
     * @var array<string, int>
     */
    private array $per_type;

    /**
     * @param array<string, int> $per_type
     *
     * @throws InvalidArgumentException If total is negative
     */
    public function __construct(int $total, array $per_type = [])
    {
        if ($total < 0) {
            throw new InvalidArgumentException('Total capacity cannot be negative.');
        }

        $this->total = $total;
        $this->per_type = $this->normalizePerType($per_type);
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $total = isset($data['total']) && is_numeric($data['total']) ? absint((int) $data['total']) : 0;
        $per_type = isset($data['per_type']) && is_array($data['per_type']) ? $data['per_type'] : [];

        return new self($total, $per_type);
    }

    /**
     * Get total capacity.
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Get capacity per type.
     *
     * @return array<string, int>
     */
    public function getPerType(): array
    {
        return $this->per_type;
    }

    /**
     * Get capacity for specific type.
     */
    public function getForType(string $type): int
    {
        return $this->per_type[$type] ?? 0;
    }

    /**
     * Check if capacity is valid (per_type sum <= total).
     */
    public function isValid(): bool
    {
        $per_type_sum = array_sum($this->per_type);

        return $per_type_sum <= $this->total;
    }

    /**
     * Get remaining capacity (total - sum of per_type).
     */
    public function getRemaining(): int
    {
        $per_type_sum = array_sum($this->per_type);

        return max(0, $this->total - $per_type_sum);
    }

    /**
     * Normalize per_type array.
     *
     * @param array<string, mixed> $per_type
     *
     * @return array<string, int>
     */
    private function normalizePerType(array $per_type): array
    {
        $normalized = [];

        foreach ($per_type as $type => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $type_key = sanitize_key((string) $type);
            $normalized[$type_key] = absint((int) $value);
        }

        return array_filter($normalized, static fn (int $value): bool => $value > 0);
    }

    /**
     * Convert to array.
     *
     * @return array{total: int, per_type: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'per_type' => $this->per_type,
        ];
    }
}















