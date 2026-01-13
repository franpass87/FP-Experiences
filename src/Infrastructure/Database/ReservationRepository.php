<?php

declare(strict_types=1);

namespace FP_Exp\Infrastructure\Database;

use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Services\Database\DatabaseInterface;

use function absint;
use function current_time;
use function gmdate;
use function maybe_serialize;
use function maybe_unserialize;
use function sanitize_text_field;
use function strtotime;
use function strtolower;

/**
 * Reservation repository implementation.
 */
final class ReservationRepository implements ReservationRepositoryInterface
{
    private DatabaseInterface $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function getTableName(): string
    {
        return $this->database->getPrefix() . 'fp_exp_reservations';
    }

    public function findById(int $reservation_id): ?array
    {
        $table = $this->getTableName();
        $sql = "SELECT * FROM {$table} WHERE id = %d";
        $row = $this->database->getRow($sql, [$reservation_id]);

        if ($row === null) {
            return null;
        }

        // Unserialize serialized fields
        $row['pax'] = maybe_unserialize($row['pax'] ?? '');
        $row['addons'] = maybe_unserialize($row['addons'] ?? '');
        $row['utm'] = maybe_unserialize($row['utm'] ?? '');
        $row['meta'] = maybe_unserialize($row['meta'] ?? '');

        return $row;
    }

    public function findByOrderId(int $order_id): array
    {
        $table = $this->getTableName();
        $sql = "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC";
        $rows = $this->database->getResults($sql, [$order_id]);

        // Unserialize serialized fields
        foreach ($rows as &$row) {
            $row['pax'] = maybe_unserialize($row['pax'] ?? '');
            $row['addons'] = maybe_unserialize($row['addons'] ?? '');
            $row['utm'] = maybe_unserialize($row['utm'] ?? '');
            $row['meta'] = maybe_unserialize($row['meta'] ?? '');
        }

        return $rows;
    }

    public function findBySlotId(int $slot_id): array
    {
        $table = $this->getTableName();
        $sql = "SELECT * FROM {$table} WHERE slot_id = %d ORDER BY id ASC";
        $rows = $this->database->getResults($sql, [$slot_id]);

        // Unserialize serialized fields
        foreach ($rows as &$row) {
            $row['pax'] = maybe_unserialize($row['pax'] ?? '');
            $row['addons'] = maybe_unserialize($row['addons'] ?? '');
            $row['utm'] = maybe_unserialize($row['utm'] ?? '');
            $row['meta'] = maybe_unserialize($row['meta'] ?? '');
        }

        return $rows;
    }

    public function countBookingsForVirtualSlot(int $experience_id, string $start_utc, string $end_utc): int
    {
        $reservations_table = $this->getTableName();
        $slots_table = $this->wpdb->prefix . 'fp_exp_slots';

        // Active statuses that count as bookings
        $active_statuses = [
            'pending',
            'pending_request',
            'approved_confirmed',
            'approved_pending_payment',
            'paid',
            'checked_in',
        ];

        $placeholders = implode(',', array_fill(0, count($active_statuses), '%s'));

        $sql = "SELECT COALESCE(SUM(
            CASE 
                WHEN r.pax IS NULL OR r.pax = '' THEN 1
                ELSE JSON_LENGTH(r.pax)
            END
        ), 0) as total 
        FROM {$reservations_table} r 
        INNER JOIN {$slots_table} s ON r.slot_id = s.id 
        WHERE r.experience_id = %d 
        AND r.status IN ({$placeholders}) 
        AND s.start_datetime >= %s 
        AND s.start_datetime < %s";

        $params = array_merge(
            [$experience_id],
            $active_statuses,
            [$start_utc, $end_utc]
        );

        $result = $this->database->getVar($sql, $params);

        return (int) ($result ?? 0);
    }

    public function create(array $data): int
    {
        $prepared = $this->prepareForStorage($data);
        $table = $this->getTableName();
        
        return $this->database->insert($table, $prepared);
    }

    public function update(int $reservation_id, array $data): bool
    {
        $prepared = $this->prepareForStorage($data);
        $prepared['updated_at'] = current_time('mysql', true);
        $table = $this->getTableName();
        
        return $this->database->update($table, $prepared, ['id' => $reservation_id]);
    }

    public function delete(int $reservation_id): bool
    {
        $table = $this->getTableName();
        
        return $this->database->delete($table, ['id' => $reservation_id]);
    }

    public function deleteByOrderId(int $order_id): bool
    {
        $table = $this->getTableName();
        
        return $this->database->delete($table, ['order_id' => $order_id]);
    }

    /**
     * Prepare reservation data for storage.
     *
     * @param array<string, mixed> $data Raw reservation data
     * @return array<string, mixed> Prepared data
     */
    private function prepareForStorage(array $data): array
    {
        $defaults = [
            'order_id' => 0,
            'experience_id' => 0,
            'slot_id' => 0,
            'customer_id' => 0,
            'status' => 'pending',
            'pax' => [],
            'addons' => [],
            'utm' => [],
            'meta' => [],
            'locale' => '',
            'total_gross' => 0.0,
            'tax_total' => 0.0,
            'hold_expires_at' => null,
        ];

        $data = array_merge($defaults, $data);

        return [
            'order_id' => absint($data['order_id']),
            'experience_id' => absint($data['experience_id']),
            'slot_id' => absint($data['slot_id']),
            'customer_id' => absint($data['customer_id']),
            'status' => $this->normalizeStatus((string) ($data['status'] ?? 'pending')),
            'pax' => maybe_serialize($data['pax']),
            'addons' => maybe_serialize($data['addons']),
            'utm' => maybe_serialize($data['utm']),
            'meta' => maybe_serialize($data['meta']),
            'locale' => sanitize_text_field((string) ($data['locale'] ?? '')),
            'total_gross' => (float) ($data['total_gross'] ?? 0.0),
            'tax_total' => (float) ($data['tax_total'] ?? 0.0),
            'hold_expires_at' => $this->normalizeDateTime($data['hold_expires_at'] ?? null),
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = [
            'pending',
            'pending_request',
            'approved_confirmed',
            'approved_pending_payment',
            'declined',
            'paid',
            'cancelled',
            'checked_in',
        ];

        $status = strtolower($status);
        
        return in_array($status, $allowed, true) ? $status : 'pending';
    }

    /**
     * @param mixed $value
     */
    private function normalizeDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
    }
}

