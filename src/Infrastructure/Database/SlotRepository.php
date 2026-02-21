<?php

declare(strict_types=1);

namespace FP_Exp\Infrastructure\Database;

use DateTimeInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Database\DatabaseInterface;

use function absint;
use function current_time;
use function maybe_serialize;
use function maybe_unserialize;
use function sanitize_text_field;
use function strtolower;

/**
 * Slot repository implementation.
 */
final class SlotRepository implements SlotRepositoryInterface
{
    private DatabaseInterface $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function getTableName(): string
    {
        return $this->database->getPrefix() . 'fp_exp_slots';
    }

    public function findById(int $slot_id): ?array
    {
        $table = $this->getTableName();
        $sql = "SELECT * FROM {$table} WHERE id = %d";
        $row = $this->database->getRow($sql, [$slot_id]);

        if ($row === null) {
            return null;
        }

        // Unserialize serialized fields
        if (isset($row['capacity_per_type'])) {
            $row['capacity_per_type'] = maybe_unserialize($row['capacity_per_type']);
        }
        if (isset($row['resource_lock'])) {
            $row['resource_lock'] = maybe_unserialize($row['resource_lock']);
        }
        if (isset($row['price_rules'])) {
            $row['price_rules'] = maybe_unserialize($row['price_rules']);
        }

        return $row;
    }

    public function findByExperienceAndDateRange(int $experience_id, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $table = $this->getTableName();
        $posts = $this->database->getPrefix() . 'posts';

        $sql = "SELECT s.*, p.post_title AS experience_title FROM {$table} s "
            . "LEFT JOIN {$posts} p ON p.ID = s.experience_id "
            . "WHERE s.experience_id = %d AND s.start_datetime >= %s AND s.start_datetime < %s "
            . "ORDER BY s.start_datetime ASC";

        $rows = $this->database->getResults($sql, [
            $experience_id,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);

        return $this->unserializeRows($rows);
    }

    public function findByDateRange(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $table = $this->getTableName();
        $posts = $this->database->getPrefix() . 'posts';

        $sql = "SELECT s.*, p.post_title AS experience_title FROM {$table} s "
            . "LEFT JOIN {$posts} p ON p.ID = s.experience_id "
            . "WHERE s.start_datetime >= %s AND s.start_datetime < %s "
            . "ORDER BY s.start_datetime ASC";

        $rows = $this->database->getResults($sql, [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);

        return $this->unserializeRows($rows);
    }

    public function create(array $data): int
    {
        $prepared = $this->prepareForStorage($data);
        $table = $this->getTableName();
        
        return $this->database->insert($table, $prepared);
    }

    public function update(int $slot_id, array $data): bool
    {
        $prepared = $this->prepareForStorage($data);
        $prepared['updated_at'] = current_time('mysql', true);
        $table = $this->getTableName();
        
        return $this->database->update($table, $prepared, ['id' => $slot_id]);
    }

    public function delete(int $slot_id): bool
    {
        $table = $this->getTableName();
        
        return $this->database->delete($table, ['id' => $slot_id]);
    }

    /**
     * Prepare slot data for storage.
     *
     * @param array<string, mixed> $data Raw slot data
     * @return array<string, mixed> Prepared data
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

        $data = array_merge($defaults, $data);

        return [
            'experience_id' => absint($data['experience_id']),
            'start_datetime' => (string) $data['start_datetime'],
            'end_datetime' => (string) $data['end_datetime'],
            'capacity_total' => absint($data['capacity_total']),
            'capacity_per_type' => maybe_serialize($data['capacity_per_type']),
            'resource_lock' => maybe_serialize($data['resource_lock']),
            'status' => $this->normalizeStatus((string) ($data['status'] ?? 'open')),
            'price_rules' => maybe_serialize($data['price_rules']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function unserializeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            if (isset($row['capacity_per_type'])) {
                $row['capacity_per_type'] = maybe_unserialize($row['capacity_per_type']);
            }
            if (isset($row['resource_lock'])) {
                $row['resource_lock'] = maybe_unserialize($row['resource_lock']);
            }
            if (isset($row['price_rules'])) {
                $row['price_rules'] = maybe_unserialize($row['price_rules']);
            }
        }
        return $rows;
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['open', 'closed', 'cancelled'];
        $status = strtolower($status);
        
        return in_array($status, $allowed, true) ? $status : 'open';
    }
}

