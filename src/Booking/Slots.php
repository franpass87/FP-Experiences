<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP_Exp\Utils\Helpers;
use wpdb;

use function __;
use function absint;
use function array_fill;
use function array_filter;
use function array_sum;
use function array_unique;
use function array_values;
use function current_time;
use function gmdate;
use function in_array;
use function is_array;
use function json_decode;
use function maybe_serialize;
use function maybe_unserialize;
use function sanitize_key;
use function strtotime;
use function wp_parse_args;
use function wp_timezone;

final class Slots
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fp_exp_slots';
    }

    public static function create_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            experience_id BIGINT UNSIGNED NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            capacity_total INT UNSIGNED NOT NULL DEFAULT 0,
            capacity_per_type LONGTEXT NULL,
            resource_lock LONGTEXT NULL,
            status ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
            price_rules LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY experience_start (experience_id, start_datetime),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Prepare slot payload for database persistence.
     *
     * @param array<string, mixed> $data Raw slot data.
     *
     * @return array<string, mixed>
     */
    public static function prepare_for_storage(array $data): array
    {
        $defaults = [
            'experience_id' => 0,
            'start_datetime' => '',
            'end_datetime' => '',
            'capacity_total' => 0,
            'capacity_per_type' => [],
            'resource_lock' => [],
            'status' => self::STATUS_OPEN,
            'price_rules' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return [
            'experience_id' => absint($data['experience_id']),
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'capacity_total' => absint($data['capacity_total']),
            'capacity_per_type' => maybe_serialize(self::normalize_structure($data['capacity_per_type'])),
            'resource_lock' => maybe_serialize(self::normalize_structure($data['resource_lock'])),
            'status' => self::normalize_status((string) $data['status']),
            'price_rules' => maybe_serialize(self::normalize_structure($data['price_rules'])),
            'updated_at' => current_time('mysql', true),
        ];
    }

    /**
     * Generate slots based on recurrence rules and store them in the database.
     *
     * @param int                      $experience_id Experience identifier.
     * @param array<int, array<mixed>> $rules         Recurrence rules definition.
     * @param array<int, array<mixed>> $exceptions    Exceptions to skip.
     * @param array<string, mixed>     $options       Additional options (duration, capacity, buffers).
     */
    public static function generate_recurring_slots(int $experience_id, array $rules, array $exceptions = [], array $options = []): int
    {
        $defaults = [
            'default_duration' => 60,
            'default_capacity' => 0,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'replace_existing' => false,
        ];

        $options = wp_parse_args($options, $defaults);
        $timezone = wp_timezone();

        $normalized_exceptions = self::normalize_exceptions($exceptions, $timezone);

        $created = 0;

        foreach ($rules as $rule) {
            $normalized_rule = self::normalize_rule($rule, $options, $timezone);

            if (null === $normalized_rule) {
                continue;
            }

            $occurrences = self::expand_rule($normalized_rule, $timezone);

            foreach ($occurrences as $occurrence) {
                if (self::is_exception($occurrence['start'], $occurrence['end'], $normalized_exceptions)) {
                    continue;
                }

                $start_utc = $occurrence['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $end_utc = $occurrence['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

                if (self::has_buffer_conflict(
                    $experience_id,
                    $start_utc,
                    $end_utc,
                    $normalized_rule['buffer_before'] ?? (int) $options['buffer_before'],
                    $normalized_rule['buffer_after'] ?? (int) $options['buffer_after']
                )) {
                    continue;
                }

                $payload = [
                    'experience_id' => $experience_id,
                    'start_datetime' => $start_utc,
                    'end_datetime' => $end_utc,
                    'capacity_total' => $normalized_rule['capacity_total'],
                    'capacity_per_type' => $normalized_rule['capacity_per_type'],
                    'resource_lock' => $normalized_rule['resource_lock'],
                    'status' => self::STATUS_OPEN,
                    'price_rules' => $normalized_rule['price_rules'],
                ];

                $existing_id = self::slot_exists($experience_id, $start_utc, $end_utc);

                if ($existing_id && ! $options['replace_existing']) {
                    continue;
                }

                if ($existing_id && $options['replace_existing']) {
                    self::update_slot($existing_id, $payload);
                    ++$created;
                    continue;
                }

                if (self::insert_slot($payload)) {
                    ++$created;
                }
            }
        }

        return $created;
    }

    /**
     * Preview recurring slots without persisting them.
     *
     * @param array<int, array<mixed>> $rules
     * @param array<int, array<mixed>> $exceptions
     * @param array<string, mixed>     $options
     *
     * @return array<int, array<string, string|int>>
     */
    public static function preview_recurring_slots(int $experience_id, array $rules, array $exceptions = [], array $options = [], int $limit = 10): array
    {
        $defaults = [
            'default_duration' => 60,
            'default_capacity' => 0,
            'buffer_before' => 0,
            'buffer_after' => 0,
        ];

        $options = wp_parse_args($options, $defaults);
        $timezone = wp_timezone();
        $normalized_exceptions = self::normalize_exceptions($exceptions, $timezone);

        $preview = [];
        $limit = max(1, $limit);
        $now = new DateTimeImmutable('now', $timezone);

        foreach ($rules as $rule) {
            $normalized_rule = self::normalize_rule($rule, $options, $timezone);

            if (null === $normalized_rule) {
                continue;
            }

            $occurrences = self::expand_rule($normalized_rule, $timezone);

            foreach ($occurrences as $occurrence) {
                if (self::is_exception($occurrence['start'], $occurrence['end'], $normalized_exceptions)) {
                    continue;
                }

                if ($occurrence['end'] < $now) {
                    continue;
                }

                $preview[] = [
                    'experience_id' => $experience_id,
                    'start_local' => $occurrence['start']->format('Y-m-d H:i'),
                    'end_local' => $occurrence['end']->format('Y-m-d H:i'),
                    'start_utc' => $occurrence['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'end_utc' => $occurrence['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ];

                if (count($preview) >= $limit) {
                    break 2;
                }
            }
        }

        usort(
            $preview,
            static function (array $a, array $b): int {
                return strcmp((string) $a['start_utc'], (string) $b['start_utc']);
            }
        );

        return array_slice($preview, 0, $limit);
    }

    /**
     * Determine if a slot violates configured buffers.
     */
    public static function has_buffer_conflict(
        int $experience_id,
        string $start_utc,
        string $end_utc,
        int $buffer_before_minutes = 0,
        int $buffer_after_minutes = 0,
        ?int $ignore_slot_id = null
    ): bool {
        $buffer_before_minutes = max(0, $buffer_before_minutes);
        $buffer_after_minutes = max(0, $buffer_after_minutes);

        if (0 === $buffer_before_minutes && 0 === $buffer_after_minutes) {
            return false;
        }

        try {
            $start = new DateTimeImmutable($start_utc, new DateTimeZone('UTC'));
            $end = new DateTimeImmutable($end_utc, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            return false;
        }

        if ($buffer_before_minutes > 0) {
            $start = $start->sub(new DateInterval('PT' . $buffer_before_minutes . 'M'));
        }

        if ($buffer_after_minutes > 0) {
            $end = $end->add(new DateInterval('PT' . $buffer_after_minutes . 'M'));
        }

        global $wpdb;

        $table = self::table_name();

        $query = "SELECT id FROM {$table} WHERE experience_id = %d AND status != %s AND start_datetime < %s AND end_datetime > %s";
        $params = [
            $experience_id,
            self::STATUS_CANCELLED,
            $end->format('Y-m-d H:i:s'),
            $start->format('Y-m-d H:i:s'),
        ];

        if ($ignore_slot_id) {
            $query .= ' AND id != %d';
            $params[] = $ignore_slot_id;
        }

        $sql = $wpdb->prepare($query, $params);

        $result = $wpdb->get_var($sql);

        return null !== $result;
    }

    /**
     * Determine if the provided slot passes the configured lead time.
     *
     * @param array<string, mixed> $slot Slot row from the database.
     */
    public static function passes_lead_time(array $slot, int $lead_time_hours): bool
    {
        $lead_time_hours = max(0, $lead_time_hours);

        if (0 === $lead_time_hours || empty($slot['start_datetime'])) {
            return true;
        }

        try {
            $start = new DateTimeImmutable((string) $slot['start_datetime'], new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            return true;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $cutoff = $now->add(new DateInterval('PT' . $lead_time_hours . 'H'));

        return $start >= $cutoff;
    }

    /**
     * Retrieve a slot by its identifier.
     *
     * @return array<string, mixed>|null
     */
    public static function get_slot(int $slot_id): ?array
    {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $slot_id);

        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! $row) {
            return null;
        }

        $row['capacity_per_type'] = maybe_unserialize($row['capacity_per_type']);
        $row['resource_lock'] = maybe_unserialize($row['resource_lock']);
        $row['price_rules'] = maybe_unserialize($row['price_rules']);

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_upcoming_for_experience(int $experience_id, int $limit = 20): array
    {
        global $wpdb;

        $experience_id = absint($experience_id);

        if ($experience_id <= 0) {
            return [];
        }

        $table = self::table_name();
        $now = gmdate('Y-m-d H:i:s');
        $limit = max(1, $limit);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE experience_id = %d AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT %d",
            $experience_id,
            $now,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! $rows) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['capacity_per_type'] = maybe_unserialize($row['capacity_per_type']);
                $row['resource_lock'] = maybe_unserialize($row['resource_lock']);
                $row['price_rules'] = maybe_unserialize($row['price_rules']);

                return $row;
            },
            $rows
        );
    }

    /**
     * Retrieve slots for a given date range to populate the admin calendar.
     *
     * @param array<string, mixed> $args
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_slots_in_range(string $start, string $end, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'experience_id' => 0,
            'statuses' => [self::STATUS_OPEN, self::STATUS_CLOSED],
        ];

        $args = wp_parse_args($args, $defaults);

        try {
            $start_dt = new DateTimeImmutable($start, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            $start_dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        try {
            $end_dt = new DateTimeImmutable($end, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            $end_dt = $start_dt;
        }

        if ($end_dt < $start_dt) {
            $end_dt = $start_dt;
        }

        $start_sql = $start_dt->setTime(0, 0)->format('Y-m-d H:i:s');
        $end_sql = $end_dt->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $table = self::table_name();
        $posts_table = $wpdb->posts;

        $sql = "SELECT s.*, p.post_title AS experience_title FROM {$table} s " .
            "LEFT JOIN {$posts_table} p ON p.ID = s.experience_id " .
            "WHERE s.start_datetime BETWEEN %s AND %s";

        $parameters = [$start_sql, $end_sql];

        $experience_id = absint($args['experience_id']);
        if ($experience_id > 0) {
            $sql .= ' AND s.experience_id = %d';
            $parameters[] = $experience_id;
        }

        $statuses = array_filter(array_map('sanitize_key', (array) $args['statuses']));
        if (! $statuses) {
            $statuses = [self::STATUS_OPEN, self::STATUS_CLOSED];
        }

        $sql .= ' AND s.status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
        $parameters = array_merge($parameters, $statuses);

        $sql .= ' ORDER BY s.start_datetime ASC';

        $prepared = $wpdb->prepare($sql, $parameters);

        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! $rows) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['capacity_per_type'] = maybe_unserialize($row['capacity_per_type']);
                $row['resource_lock'] = maybe_unserialize($row['resource_lock']);
                $row['price_rules'] = maybe_unserialize($row['price_rules']);

                $snapshot = self::get_capacity_snapshot((int) $row['id']);
                $row['reserved_total'] = $snapshot['total'];
                $row['reserved_per_type'] = $snapshot['per_type'];
                $row['remaining'] = max(0, (int) $row['capacity_total'] - $snapshot['total']);
                $row['duration'] = self::calculate_duration_minutes($row['start_datetime'] ?? '', $row['end_datetime'] ?? '');

                return $row;
            },
            $rows
        );
    }

    /**
     * Retrieve aggregate reservation counts for a slot.
     *
     * @return array{total:int, per_type:array<string,int>}
     */
    public static function get_capacity_snapshot(int $slot_id): array
    {
        $snapshots = self::get_capacity_snapshots([$slot_id]);

        return $snapshots[$slot_id] ?? [
            'total' => 0,
            'per_type' => [],
        ];
    }

    /**
     * @param array<int> $slot_ids
     *
     * @return array<int, array{total:int, per_type:array<string,int>}>
     */
    public static function get_capacity_snapshots(array $slot_ids): array
    {
        global $wpdb;

        $normalized_ids = array_values(array_unique(array_filter(array_map('absint', $slot_ids))));

        if (! $normalized_ids) {
            return [];
        }

        $snapshots = [];

        foreach (array_unique($normalized_ids) as $slot_id) {
            if ($slot_id <= 0) {
                continue;
            }

            $snapshots[$slot_id] = [
                'total' => 0,
                'per_type' => [],
            ];
        }

        if (! $snapshots) {
            return [];
        }

        $slot_table = self::table_name();
        $placeholders = implode(',', array_fill(0, count($snapshots), '%d'));

        $slot_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, experience_id FROM {$slot_table} WHERE id IN ({$placeholders})",
                ...array_keys($snapshots)
            ),
            ARRAY_A
        );

        $experience_map = [];
        if ($slot_rows) {
            foreach ($slot_rows as $row) {
                $slot_id = isset($row['id']) ? absint((string) $row['id']) : 0;
                if (! $slot_id || ! isset($snapshots[$slot_id])) {
                    continue;
                }

                $experience_map[$slot_id] = isset($row['experience_id']) ? absint((string) $row['experience_id']) : 0;
            }
        }

        $block_cache = [];
        $block_map = [];
        foreach ($experience_map as $slot_id => $experience_id) {
            if (! array_key_exists($experience_id, $block_cache)) {
                $block_cache[$experience_id] = Helpers::rtb_block_capacity($experience_id);
            }

            $block_map[$slot_id] = $block_cache[$experience_id];
        }

        $reservations_table = Reservations::table_name();
        $reservation_params = array_merge(array_keys($snapshots), [Reservations::STATUS_CANCELLED]);

        $reservation_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT slot_id, status, pax, hold_expires_at FROM {$reservations_table} " .
                "WHERE slot_id IN ({$placeholders}) AND status != %s",
                ...$reservation_params
            ),
            ARRAY_A
        );

        if (! $reservation_rows) {
            return $snapshots;
        }

        $now = current_time('timestamp', true);

        foreach ($reservation_rows as $row) {
            $slot_id = isset($row['slot_id']) ? absint((string) $row['slot_id']) : 0;

            if (! isset($snapshots[$slot_id])) {
                continue;
            }

            $status = isset($row['status'])
                ? Reservations::normalize_status((string) $row['status'])
                : Reservations::STATUS_PENDING;

            if (Reservations::STATUS_DECLINED === $status) {
                continue;
            }

            if (Reservations::STATUS_PENDING_REQUEST === $status) {
                $should_block = $block_map[$slot_id] ?? false;
                if (! $should_block) {
                    continue;
                }

                $expires_at = ! empty($row['hold_expires_at']) ? strtotime((string) $row['hold_expires_at']) : false;
                if (! $expires_at || $expires_at <= $now) {
                    continue;
                }
            }

            $pax = maybe_unserialize($row['pax']);

            if (! is_array($pax)) {
                continue;
            }

            foreach ($pax as $type => $quantity) {
                $type_key = is_string($type) ? sanitize_key($type) : (string) $type;
                $quantity = absint($quantity);

                if ($quantity <= 0) {
                    continue;
                }

                $snapshots[$slot_id]['per_type'][$type_key] = ($snapshots[$slot_id]['per_type'][$type_key] ?? 0) + $quantity;
                $snapshots[$slot_id]['total'] += $quantity;
            }
        }

        return $snapshots;
    }

    /**
     * Validate a requested party size against slot capacity.
     *
     * @param array<string, int> $requested Quantities requested keyed by ticket slug.
     *
     * @return array{allowed:bool, remaining:array<string,int>, message?:string}
     */
    public static function check_capacity(int $slot_id, array $requested): array
    {
        $slot = self::get_slot($slot_id);

        if (! $slot) {
            return [
                'allowed' => false,
                'remaining' => [],
                'message' => __('Selected slot is no longer available.', 'fp-experiences'),
            ];
        }

        $requested = array_map('absint', $requested);
        $requested_total = (int) array_sum($requested);

        $snapshot = self::get_capacity_snapshot($slot_id);

        $capacity_total = absint($slot['capacity_total']);
        $capacity_per_type = self::normalize_structure($slot['capacity_per_type']);

        if ($capacity_total > 0 && ($snapshot['total'] + $requested_total) > $capacity_total) {
            return [
                'allowed' => false,
                'remaining' => self::calculate_remaining_capacity($capacity_total, $capacity_per_type, $snapshot),
                'message' => __('The selected slot cannot accommodate the requested party size.', 'fp-experiences'),
            ];
        }

        foreach ($requested as $type => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $type_key = sanitize_key($type);
            $type_capacity = isset($capacity_per_type[$type_key]) ? absint($capacity_per_type[$type_key]) : 0;

            if ($type_capacity > 0) {
                $reserved = $snapshot['per_type'][$type_key] ?? 0;

                if (($reserved + $quantity) > $type_capacity) {
                    return [
                        'allowed' => false,
                        'remaining' => self::calculate_remaining_capacity($capacity_total, $capacity_per_type, $snapshot),
                        'message' => __('The selected ticket type is sold out for this slot.', 'fp-experiences'),
                    ];
                }
            }
        }

        return [
            'allowed' => true,
            'remaining' => self::calculate_remaining_capacity($capacity_total, $capacity_per_type, $snapshot),
        ];
    }

    /**
     * Compute remaining capacity snapshot.
     *
     * @param array<string, int> $capacity_per_type
     * @param array{total:int, per_type:array<string,int>} $snapshot
     *
     * @return array<string, int>
     */
    private static function calculate_remaining_capacity(int $capacity_total, array $capacity_per_type, array $snapshot): array
    {
        $remaining = [];

        foreach ($capacity_per_type as $type => $limit) {
            $limit = absint($limit);

            if ($limit <= 0) {
                continue;
            }

            $reserved = $snapshot['per_type'][$type] ?? 0;

            $remaining[$type] = max(0, $limit - $reserved);
        }

        if ($capacity_total > 0) {
            $remaining['total'] = max(0, $capacity_total - $snapshot['total']);
        }

        return $remaining;
    }

    public static function move_slot(int $slot_id, string $start_iso, string $end_iso): bool
    {
        $slot = self::get_slot($slot_id);

        if (! $slot) {
            return false;
        }

        try {
            $start = new DateTimeImmutable($start_iso);
            $end = new DateTimeImmutable($end_iso);
        } catch (Exception $exception) {
            return false;
        }

        if ($end <= $start) {
            return false;
        }

        $start_utc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $end_utc = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        if (self::has_buffer_conflict((int) $slot['experience_id'], $start_utc, $end_utc, 0, 0, $slot_id)) {
            return false;
        }

        return self::update_slot($slot_id, array_merge($slot, [
            'start_datetime' => $start_utc,
            'end_datetime' => $end_utc,
        ]));
    }

    /**
     * @param array<string, int|float> $per_type
     */
    public static function update_capacity(int $slot_id, int $total, array $per_type): bool
    {
        $slot = self::get_slot($slot_id);

        if (! $slot) {
            return false;
        }

        $total = max(0, $total);
        $sanitised = [];

        foreach ($per_type as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }

            $sanitised[$key] = max(0, (int) $value);
        }

        $snapshot = self::get_capacity_snapshot($slot_id);
        if ($total > 0 && $snapshot['total'] > $total) {
            return false;
        }

        foreach ($sanitised as $key => $value) {
            if ($value > 0 && ($snapshot['per_type'][$key] ?? 0) > $value) {
                return false;
            }
        }

        return self::update_slot($slot_id, array_merge($slot, [
            'capacity_total' => $total,
            'capacity_per_type' => $sanitised,
        ]));
    }

    private static function calculate_duration_minutes(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        try {
            $start_dt = new DateTimeImmutable($start);
            $end_dt = new DateTimeImmutable($end);
        } catch (Exception $exception) {
            return 0;
        }

        $interval = $start_dt->diff($end_dt);

        return max(0, (int) (($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i));
    }

    /**
     * Normalize a recurrence rule configuration.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    private static function normalize_rule(array $rule, array $options, DateTimeZone $timezone): ?array
    {
        $type = isset($rule['type']) ? strtolower((string) $rule['type']) : 'weekly';

        if (! in_array($type, ['daily', 'weekly', 'specific'], true)) {
            return null;
        }

        $duration = isset($rule['duration']) ? absint($rule['duration']) : absint($options['default_duration']);

        if ($duration <= 0) {
            return null;
        }

        $capacity_total = isset($rule['capacity_total']) ? absint($rule['capacity_total']) : absint($options['default_capacity']);

        $times = [];

        if (! empty($rule['times']) && is_array($rule['times'])) {
            foreach ($rule['times'] as $time) {
                $time_string = is_string($time) ? trim($time) : '';

                if ('' !== $time_string) {
                    $times[] = $time_string;
                }
            }
        }

        if (empty($times) && ! empty($rule['start_time'])) {
            $time_string = is_string($rule['start_time']) ? trim($rule['start_time']) : '';

            if ('' !== $time_string) {
                $times[] = $time_string;
            }
        }

        if (empty($times)) {
            return null;
        }

        $start_date = isset($rule['start_date']) ? (string) $rule['start_date'] : 'now';
        $end_date = isset($rule['end_date']) ? (string) $rule['end_date'] : $start_date;

        try {
            $start = new DateTimeImmutable($start_date, $timezone);
            $end = new DateTimeImmutable($end_date, $timezone);
        } catch (Exception $exception) {
            return null;
        }

        if ($start > $end) {
            return null;
        }

        $days = [];

        if ('weekly' === $type && ! empty($rule['days']) && is_array($rule['days'])) {
            foreach ($rule['days'] as $day) {
                $day_string = strtolower((string) $day);

                if ('' === $day_string) {
                    continue;
                }

                $normalized_day = self::normalize_weekday_key($day_string);
                if ($normalized_day) {
                    $days[] = $normalized_day;
                }
            }
        }

        $dates = [];

        if ('specific' === $type && ! empty($rule['dates']) && is_array($rule['dates'])) {
            foreach ($rule['dates'] as $date) {
                if (! is_string($date)) {
                    continue;
                }

                $date = trim($date);

                if ('' === $date) {
                    continue;
                }

                $dates[] = $date;
            }
        }

        return [
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'times' => $times,
            'days' => $days,
            'dates' => $dates,
            'duration' => $duration,
            'capacity_total' => $capacity_total,
            'capacity_per_type' => self::normalize_structure($rule['capacity_per_type'] ?? []),
            'resource_lock' => self::normalize_structure($rule['resource_lock'] ?? []),
            'price_rules' => self::normalize_structure($rule['price_rules'] ?? []),
            'buffer_before' => isset($rule['buffer_before']) ? absint($rule['buffer_before']) : null,
            'buffer_after' => isset($rule['buffer_after']) ? absint($rule['buffer_after']) : null,
        ];
    }

    /**
     * Expand a rule into concrete slot occurrences.
     *
     * @param array<string, mixed> $rule
     *
     * @return array<int, array{start:DateTimeImmutable,end:DateTimeImmutable}>
     */
    private static function expand_rule(array $rule, DateTimeZone $timezone): array
    {
        $occurrences = [];

        $duration_interval = new DateInterval('PT' . absint($rule['duration']) . 'M');

        if ('specific' === $rule['type']) {
            foreach ($rule['dates'] as $date_string) {
                try {
                    $start = new DateTimeImmutable($date_string, $timezone);
                } catch (Exception $exception) {
                    continue;
                }

                $occurrences[] = [
                    'start' => $start,
                    'end' => $start->add($duration_interval),
                ];
            }

            return $occurrences;
        }

        $period = new DatePeriod($rule['start'], new DateInterval('P1D'), $rule['end']->add(new DateInterval('P1D')));

        foreach ($period as $date) {
            if (! $date instanceof DateTimeImmutable) {
                $date = DateTimeImmutable::createFromMutable($date);
            }

            if ('weekly' === $rule['type']) {
                $weekday = strtolower($date->format('l'));

                if (! in_array($weekday, $rule['days'], true)) {
                    continue;
                }
            }

            foreach ($rule['times'] as $time) {
                $start = self::combine_date_time($date, $time, $timezone);

                if (! $start) {
                    continue;
                }

                $occurrences[] = [
                    'start' => $start,
                    'end' => $start->add($duration_interval),
                ];
            }
        }

        return $occurrences;
    }

    private static function combine_date_time(DateTimeImmutable $date, string $time, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $time = trim($time);

        if ('' === $time) {
            return null;
        }

        $date_string = $date->format('Y-m-d') . ' ' . $time;

        try {
            return new DateTimeImmutable($date_string, $timezone);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * Normalize exception definitions.
     *
     * @param array<int, array<mixed>> $exceptions
     *
     * @return array<int, array<string, DateTimeImmutable>>
     */
    private static function normalize_exceptions(array $exceptions, DateTimeZone $timezone): array
    {
        $normalized = [];

        foreach ($exceptions as $exception) {
            if (! is_array($exception)) {
                continue;
            }

            if (isset($exception['range']) && is_array($exception['range'])) {
                $range = $exception['range'];
                $start_string = isset($range['start']) ? (string) $range['start'] : '';
                $end_string = isset($range['end']) ? (string) $range['end'] : '';

                if ('' === $start_string || '' === $end_string) {
                    continue;
                }

                try {
                    $start = new DateTimeImmutable($start_string, $timezone);
                    $end = new DateTimeImmutable($end_string, $timezone);
                } catch (Exception $exception) {
                    continue;
                }

                $normalized[] = [
                    'start' => $start,
                    'end' => $end,
                ];
                continue;
            }

            if (! empty($exception['datetime']) && is_string($exception['datetime'])) {
                try {
                    $start = new DateTimeImmutable((string) $exception['datetime'], $timezone);
                } catch (Exception $exception) {
                    continue;
                }

                $normalized[] = [
                    'start' => $start,
                    'end' => $start,
                ];
                continue;
            }

            if (! empty($exception['date']) && is_string($exception['date'])) {
                try {
                    $start = new DateTimeImmutable((string) $exception['date'], $timezone);
                } catch (Exception $exception) {
                    continue;
                }

                $normalized[] = [
                    'start' => $start,
                    'end' => $start->setTime(23, 59, 59),
                ];
            }
        }

        return $normalized;
    }

    /**
     * Determine if a slot falls inside an exception window.
     *
     * @param array<int, array<string, DateTimeImmutable>> $exceptions
     */
    private static function is_exception(DateTimeImmutable $start, DateTimeImmutable $end, array $exceptions): bool
    {
        foreach ($exceptions as $exception) {
            if (! isset($exception['start'], $exception['end'])) {
                continue;
            }

            if ($start >= $exception['start'] && $start <= $exception['end']) {
                return true;
            }

            if ($end >= $exception['start'] && $end <= $exception['end']) {
                return true;
            }

            if ($start <= $exception['start'] && $end >= $exception['end']) {
                return true;
            }
        }

        return false;
    }

    private static function slot_exists(int $experience_id, string $start_utc, string $end_utc): ?int
    {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} WHERE experience_id = %d AND start_datetime = %s AND end_datetime = %s LIMIT 1",
            $experience_id,
            $start_utc,
            $end_utc
        );

        $found = $wpdb->get_var($sql);

        return $found ? (int) $found : null;
    }

    /**
     * Insert a slot row.
     */
    private static function insert_slot(array $data): bool
    {
        global $wpdb;

        $prepared = self::prepare_for_storage($data);
        $prepared['created_at'] = $prepared['updated_at'];

        $result = $wpdb->insert(self::table_name(), $prepared, self::get_formats($prepared));

        return false !== $result;
    }

    private static function normalize_weekday_key(string $day): ?string
    {
        $map = [
            'mon' => 'monday',
            'tue' => 'tuesday',
            'wed' => 'wednesday',
            'thu' => 'thursday',
            'fri' => 'friday',
            'sat' => 'saturday',
            'sun' => 'sunday',
        ];

        if (isset($map[$day])) {
            return $map[$day];
        }

        $day = strtolower($day);

        return in_array($day, $map, true) ? $day : null;
    }

    /**
     * Update an existing slot.
     */
    private static function update_slot(int $slot_id, array $data): bool
    {
        global $wpdb;

        $prepared = self::prepare_for_storage($data);

        $result = $wpdb->update(
            self::table_name(),
            $prepared,
            ['id' => $slot_id],
            self::get_formats($prepared),
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Map slot data keys to wpdb formats.
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, string>
     */
    private static function get_formats(array $data): array
    {
        $map = [
            'experience_id' => '%d',
            'start_datetime' => '%s',
            'end_datetime' => '%s',
            'capacity_total' => '%d',
            'capacity_per_type' => '%s',
            'resource_lock' => '%s',
            'status' => '%s',
            'price_rules' => '%s',
            'updated_at' => '%s',
            'created_at' => '%s',
        ];

        $formats = [];

        foreach ($data as $key => $value) {
            if (isset($map[$key])) {
                $formats[] = $map[$key];
            }
        }

        return $formats;
    }

    /**
     * Ensure only allowed statuses are stored.
     */
    public static function normalize_status(string $status): string
    {
        $status = strtolower($status);

        if (! in_array($status, [self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_CANCELLED], true)) {
            return self::STATUS_OPEN;
        }

        return $status;
    }

    /**
     * @param mixed $value
     *
     * @return array<mixed>
     */
    private static function normalize_structure($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && '' !== $value) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
