<?php

declare(strict_types=1);

namespace FP_Exp\Domain\Booking\Repositories;

use DateTimeInterface;

/**
 * Reservation repository interface.
 */
interface ReservationRepositoryInterface
{
    /**
     * Get a reservation by ID.
     *
     * @param int $reservation_id Reservation ID
     * @return array<string, mixed>|null Reservation data or null if not found
     */
    public function findById(int $reservation_id): ?array;

    /**
     * Find reservations by order ID.
     *
     * @param int $order_id Order ID
     * @return array<int, array<string, mixed>> Array of reservation data
     */
    public function findByOrderId(int $order_id): array;

    /**
     * Find reservations by slot ID.
     *
     * @param int $slot_id Slot ID
     * @return array<int, array<string, mixed>> Array of reservation data
     */
    public function findBySlotId(int $slot_id): array;

    /**
     * Count bookings for a virtual slot (by experience and datetime range).
     *
     * @param int $experience_id Experience ID
     * @param string $start_utc Start datetime in UTC format
     * @param string $end_utc End datetime in UTC format
     * @return int Number of bookings
     */
    public function countBookingsForVirtualSlot(int $experience_id, string $start_utc, string $end_utc): int;

    /**
     * Create a new reservation.
     *
     * @param array<string, mixed> $data Reservation data
     * @return int Reservation ID or 0 on failure
     */
    public function create(array $data): int;

    /**
     * Update a reservation.
     *
     * @param int $reservation_id Reservation ID
     * @param array<string, mixed> $data Reservation data
     * @return bool True on success, false on failure
     */
    public function update(int $reservation_id, array $data): bool;

    /**
     * Delete a reservation.
     *
     * @param int $reservation_id Reservation ID
     * @return bool True on success, false on failure
     */
    public function delete(int $reservation_id): bool;

    /**
     * Delete reservations by order ID.
     *
     * @param int $order_id Order ID
     * @return bool True on success, false on failure
     */
    public function deleteByOrderId(int $order_id): bool;

    /**
     * Get table name.
     */
    public function getTableName(): string;
}










