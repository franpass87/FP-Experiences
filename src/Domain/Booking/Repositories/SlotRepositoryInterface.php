<?php

declare(strict_types=1);

namespace FP_Exp\Domain\Booking\Repositories;

use DateTimeInterface;

/**
 * Slot repository interface.
 */
interface SlotRepositoryInterface
{
    /**
     * Get a slot by ID.
     *
     * @param int $slot_id Slot ID
     * @return array<string, mixed>|null Slot data or null if not found
     */
    public function findById(int $slot_id): ?array;

    /**
     * Find slots for an experience within a date range.
     *
     * @param int $experience_id Experience ID
     * @param DateTimeInterface $start Start date
     * @param DateTimeInterface $end End date
     * @return array<int, array<string, mixed>> Array of slot data
     */
    public function findByExperienceAndDateRange(int $experience_id, DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * Create a new slot.
     *
     * @param array<string, mixed> $data Slot data
     * @return int Slot ID or 0 on failure
     */
    public function create(array $data): int;

    /**
     * Update a slot.
     *
     * @param int $slot_id Slot ID
     * @param array<string, mixed> $data Slot data
     * @return bool True on success, false on failure
     */
    public function update(int $slot_id, array $data): bool;

    /**
     * Delete a slot.
     *
     * @param int $slot_id Slot ID
     * @return bool True on success, false on failure
     */
    public function delete(int $slot_id): bool;

    /**
     * Get table name.
     */
    public function getTableName(): string;
}










