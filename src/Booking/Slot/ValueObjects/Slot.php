<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

use function absint;
use function is_array;
use function maybe_unserialize;
use function sanitize_key;

/**
 * Value Object representing a complete slot.
 */
final class Slot
{
    private int $id;
    private int $experience_id;
    private TimeRange $time_range;
    private SlotCapacity $capacity;
    private string $status;
    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public function __construct(
        int $id,
        int $experience_id,
        TimeRange $time_range,
        SlotCapacity $capacity,
        string $status = 'open',
        array $metadata = []
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('Slot ID must be positive.');
        }

        if ($experience_id <= 0) {
            throw new InvalidArgumentException('Experience ID must be positive.');
        }

        $this->id = $id;
        $this->experience_id = $experience_id;
        $this->time_range = $time_range;
        $this->capacity = $capacity;
        $this->status = $this->normalizeStatus($status);
        $this->metadata = $metadata;
    }

    /**
     * Create from database row.
     *
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        $id = absint((int) ($row['id'] ?? 0));
        $experience_id = absint((int) ($row['experience_id'] ?? 0));
        $start_utc = (string) ($row['start_datetime'] ?? '');
        $end_utc = (string) ($row['end_datetime'] ?? '');
        $status = (string) ($row['status'] ?? 'open');

        $time_range = TimeRange::fromUtcStrings($start_utc, $end_utc);

        $capacity_data = [
            'total' => absint((int) ($row['capacity_total'] ?? 0)),
            'per_type' => maybe_unserialize($row['capacity_per_type'] ?? []),
        ];
        $capacity = SlotCapacity::fromArray($capacity_data);

        $metadata = [
            'resource_lock' => maybe_unserialize($row['resource_lock'] ?? []),
            'price_rules' => maybe_unserialize($row['price_rules'] ?? []),
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
        ];

        return new self($id, $experience_id, $time_range, $capacity, $status, $metadata);
    }

    /**
     * Get slot ID.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get experience ID.
     */
    public function getExperienceId(): int
    {
        return $this->experience_id;
    }

    /**
     * Get time range.
     */
    public function getTimeRange(): TimeRange
    {
        return $this->time_range;
    }

    /**
     * Get capacity.
     */
    public function getCapacity(): SlotCapacity
    {
        return $this->capacity;
    }

    /**
     * Get status.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Check if slot is open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if slot is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if slot is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get metadata value.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'experience_id' => $this->experience_id,
            'start_datetime' => $this->time_range->getStartUtc(),
            'end_datetime' => $this->time_range->getEndUtc(),
            'capacity_total' => $this->capacity->getTotal(),
            'capacity_per_type' => $this->capacity->getPerType(),
            'status' => $this->status,
            'resource_lock' => $this->metadata['resource_lock'] ?? [],
            'price_rules' => $this->metadata['price_rules'] ?? [],
        ];
    }

    /**
     * Normalize status.
     */
    private function normalizeStatus(string $status): string
    {
        $status = sanitize_key($status);

        if (! in_array($status, ['open', 'closed', 'cancelled'], true)) {
            return 'open';
        }

        return $status;
    }
}















