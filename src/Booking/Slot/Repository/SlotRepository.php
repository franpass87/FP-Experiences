<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\Repository;

use FP_Exp\Booking\Slot\ValueObjects\Slot;
use FP_Exp\Booking\Slot\ValueObjects\TimeRange;
use wpdb;

use function absint;
use function maybe_serialize;
use function maybe_unserialize;
use function sanitize_key;
use function wp_parse_args;

/**
 * Repository for slot data access.
 */
final class SlotRepository
{
    private wpdb $wpdb;
    private string $table_name;

    public function __construct(?wpdb $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $this->wpdb = $GLOBALS['wpdb'];
        } else {
            $this->wpdb = $wpdb;
        }
        $this->table_name = $this->wpdb->prefix . 'fp_exp_slots';
    }

    /**
     * Get table name.
     */
    public function getTableName(): string
    {
        return $this->table_name;
    }

    /**
     * Find slot by ID.
     */
    public function findById(int $slot_id): ?Slot
    {
        $slot_id = absint($slot_id);

        if ($slot_id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $slot_id);
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        if (! $row || ! is_array($row)) {
            return null;
        }

        return Slot::fromDatabaseRow($row);
    }

    /**
     * Find slots by experience ID.
     *
     * @return array<int, Slot>
     */
    public function findByExperienceId(int $experience_id, int $limit = 0): array
    {
        $experience_id = absint($experience_id);

        if ($experience_id <= 0) {
            return [];
        }

        $sql = "SELECT * FROM {$this->table_name} WHERE experience_id = %d ORDER BY start_datetime ASC";
        $limit_sql = $limit > 0 ? " LIMIT {$limit}" : '';
        $sql = $this->wpdb->prepare($sql . $limit_sql, $experience_id);

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (! $rows || ! is_array($rows)) {
            return [];
        }

        $slots = [];

        foreach ($rows as $row) {
            try {
                $slots[] = Slot::fromDatabaseRow($row);
            } catch (\Throwable $exception) {
                // Skip invalid rows
                continue;
            }
        }

        return $slots;
    }

    /**
     * Find slots in time range.
     *
     * @return array<int, Slot>
     */
    public function findByTimeRange(TimeRange $time_range, array $args = []): array
    {
        $experience_id = isset($args['experience_id']) ? absint((int) $args['experience_id']) : 0;
        $status = isset($args['status']) ? sanitize_key((string) $args['status']) : '';

        $where = [];
        $where_values = [];

        $where[] = 'start_datetime >= %s';
        $where_values[] = $time_range->getStartUtc();

        $where[] = 'end_datetime <= %s';
        $where_values[] = $time_range->getEndUtc();

        if ($experience_id > 0) {
            $where[] = 'experience_id = %d';
            $where_values[] = $experience_id;
        }

        if ($status) {
            $where[] = 'status = %s';
            $where_values[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY start_datetime ASC";

        $prepared_sql = $this->wpdb->prepare($sql, ...$where_values);
        $rows = $this->wpdb->get_results($prepared_sql, ARRAY_A);

        if (! $rows || ! is_array($rows)) {
            return [];
        }

        $slots = [];

        foreach ($rows as $row) {
            try {
                $slots[] = Slot::fromDatabaseRow($row);
            } catch (\Throwable $exception) {
                // Skip invalid rows
                continue;
            }
        }

        return $slots;
    }

    /**
     * Find slot by experience and time.
     */
    public function findByExperienceAndTime(int $experience_id, TimeRange $time_range): ?Slot
    {
        $experience_id = absint($experience_id);

        if ($experience_id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE experience_id = %d AND start_datetime = %s AND end_datetime = %s LIMIT 1",
            $experience_id,
            $time_range->getStartUtc(),
            $time_range->getEndUtc()
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        if (! $row || ! is_array($row)) {
            return null;
        }

        return Slot::fromDatabaseRow($row);
    }

    /**
     * Create slot.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): ?Slot
    {
        $prepared = $this->prepareForStorage($data);

        $inserted = $this->wpdb->insert(
            $this->table_name,
            [
                'experience_id' => $prepared['experience_id'],
                'start_datetime' => $prepared['start_datetime'],
                'end_datetime' => $prepared['end_datetime'],
                'capacity_total' => $prepared['capacity_total'],
                'capacity_per_type' => maybe_serialize($prepared['capacity_per_type']),
                'resource_lock' => maybe_serialize($prepared['resource_lock']),
                'status' => $prepared['status'],
                'price_rules' => maybe_serialize($prepared['price_rules']),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return null;
        }

        $slot_id = (int) $this->wpdb->insert_id;

        return $this->findById($slot_id);
    }

    /**
     * Update slot.
     */
    public function update(Slot $slot): bool
    {
        $data = $slot->toArray();
        $prepared = $this->prepareForStorage($data);

        $updated = $this->wpdb->update(
            $this->table_name,
            [
                'experience_id' => $prepared['experience_id'],
                'start_datetime' => $prepared['start_datetime'],
                'end_datetime' => $prepared['end_datetime'],
                'capacity_total' => $prepared['capacity_total'],
                'capacity_per_type' => maybe_serialize($prepared['capacity_per_type']),
                'resource_lock' => maybe_serialize($prepared['resource_lock']),
                'status' => $prepared['status'],
                'price_rules' => maybe_serialize($prepared['price_rules']),
            ],
            ['id' => $slot->getId()],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Delete slot.
     */
    public function delete(int $slot_id): bool
    {
        $slot_id = absint($slot_id);

        if ($slot_id <= 0) {
            return false;
        }

        $deleted = $this->wpdb->delete(
            $this->table_name,
            ['id' => $slot_id],
            ['%d']
        );

        return $deleted !== false;
    }

    /**
     * Prepare data for storage.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function prepareForStorage(array $data): array
    {
        $defaults = [
            'experience_id' => 0,
            'start_datetime' => '',
            'end_datetime' => '',
            'capacity_total' => 0,
            'capacity_per_type' => [],
            'resource_lock' => [],
            'status' => 'open',
            'price_rules' => [],
        ];

        $prepared = wp_parse_args($data, $defaults);

        $prepared['experience_id'] = absint((int) $prepared['experience_id']);
        $prepared['capacity_total'] = absint((int) $prepared['capacity_total']);

        if (! is_array($prepared['capacity_per_type'])) {
            $prepared['capacity_per_type'] = [];
        }

        if (! is_array($prepared['resource_lock'])) {
            $prepared['resource_lock'] = [];
        }

        if (! is_array($prepared['price_rules'])) {
            $prepared['price_rules'] = [];
        }

        $status = sanitize_key((string) $prepared['status']);
        if (! in_array($status, ['open', 'closed', 'cancelled'], true)) {
            $status = 'open';
        }
        $prepared['status'] = $status;

        return $prepared;
    }
}

