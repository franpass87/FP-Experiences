<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use DateTimeInterface;
use FP_Exp\Utils\Helpers;
use wpdb;

use function __;
use function absint;
use function current_time;
use function floatval;
use function gmdate;
use function maybe_serialize;
use function maybe_unserialize;
use function sanitize_text_field;
use function strtotime;
use function wp_parse_args;

final class Reservations
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_REQUEST = 'pending_request';
    public const STATUS_APPROVED_CONFIRMED = 'approved_confirmed';
    public const STATUS_APPROVED_PENDING_PAYMENT = 'approved_pending_payment';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CHECKED_IN = 'checked_in';

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fp_exp_reservations';
    }

    public static function create_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            experience_id BIGINT UNSIGNED NOT NULL,
            slot_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('pending','pending_request','approved_confirmed','approved_pending_payment','declined','paid','cancelled','checked_in') NOT NULL DEFAULT 'pending',
            pax LONGTEXT NULL,
            addons LONGTEXT NULL,
            utm LONGTEXT NULL,
            meta LONGTEXT NULL,
            locale VARCHAR(15) NOT NULL DEFAULT '',
            total_gross DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            tax_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            hold_expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_lookup (order_id),
            KEY experience_slot (experience_id, slot_id),
            KEY status_lookup (status),
            KEY combined_lookup (order_id, experience_id, slot_id, status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Prepare reservation data for storage.
     *
     * @param array<string, mixed> $data Raw reservation data.
     *
     * @return array<string, mixed>
     */
    public static function prepare_for_storage(array $data): array
    {
        $defaults = [
            'order_id' => 0,
            'experience_id' => 0,
            'slot_id' => 0,
            'customer_id' => 0,
            'status' => self::STATUS_PENDING,
            'pax' => [],
            'addons' => [],
            'utm' => [],
            'meta' => [],
            'locale' => '',
            'total_gross' => 0.0,
            'tax_total' => 0.0,
            'hold_expires_at' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        return [
            'order_id' => absint($data['order_id']),
            'experience_id' => absint($data['experience_id']),
            'slot_id' => absint($data['slot_id']),
            'customer_id' => absint($data['customer_id']),
            'status' => self::normalize_status((string) $data['status']),
            'pax' => maybe_serialize(self::normalize_structure($data['pax'])),
            'addons' => maybe_serialize(self::normalize_structure($data['addons'])),
            'utm' => maybe_serialize(self::normalize_structure($data['utm'])),
            'meta' => maybe_serialize(self::normalize_structure($data['meta'])),
            'locale' => sanitize_text_field((string) $data['locale']),
            'total_gross' => floatval($data['total_gross']),
            'tax_total' => floatval($data['tax_total']),
            'hold_expires_at' => self::normalize_datetime($data['hold_expires_at']),
            'updated_at' => current_time('mysql', true),
        ];
    }

    public static function normalize_status(string $status): string
    {
        $status = strtolower($status);

        $allowed = [
            self::STATUS_PENDING,
            self::STATUS_PENDING_REQUEST,
            self::STATUS_APPROVED_CONFIRMED,
            self::STATUS_APPROVED_PENDING_PAYMENT,
            self::STATUS_DECLINED,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
            self::STATUS_CHECKED_IN,
        ];

        if (! in_array($status, $allowed, true)) {
            return self::STATUS_PENDING;
        }

        return $status;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_datetime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);

            if (false !== $timestamp) {
                return gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
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

    /**
     * Persist a reservation record.
     *
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        global $wpdb;

        $table = self::table_name();
        $prepared = self::prepare_for_storage($data);

        $result = $wpdb->insert($table, $prepared);

        if (false === $result) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public static function delete_by_order(int $order_id): void
    {
        global $wpdb;

        $table = self::table_name();
        $wpdb->delete(
            $table,
            [
                'order_id' => absint($order_id),
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $reservation_id, array $data): bool
    {
        global $wpdb;

        $table = self::table_name();
        $prepared = self::prepare_for_storage($data);

        $updated = $wpdb->update(
            $table,
            $prepared,
            [
                'id' => absint($reservation_id),
            ]
        );

        return false !== $updated && $updated > 0;
    }

    public static function update_status(int $reservation_id, string $status): bool
    {
        global $wpdb;

        $table = self::table_name();
        $updated = $wpdb->update(
            $table,
            [
                'status' => self::normalize_status($status),
                'updated_at' => current_time('mysql', true),
            ],
            [
                'id' => absint($reservation_id),
            ]
        );

        return false !== $updated && $updated > 0;
    }

    /**
     * @return array<int>
     */
    public static function get_ids_by_order(int $order_id): array
    {
        global $wpdb;

        $table = self::table_name();
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE order_id = %d", $order_id));

        if (! $ids) {
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * Retrieve a single reservation record.
     *
     * @return array<string, mixed>|null
     */
    public static function get(int $reservation_id): ?array
    {
        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $reservation_id), ARRAY_A);

        if (! $row) {
            return null;
        }

        $row['pax'] = maybe_unserialize($row['pax']);
        $row['addons'] = maybe_unserialize($row['addons']);
        $row['utm'] = maybe_unserialize($row['utm']);
        $row['meta'] = maybe_unserialize($row['meta']);

        return $row;
    }

    /**
     * Retrieve request-style reservations for administrative workflows.
     *
     * @param array<string, mixed> $args
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_requests(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'statuses' => [
                self::STATUS_PENDING_REQUEST,
                self::STATUS_APPROVED_PENDING_PAYMENT,
                self::STATUS_APPROVED_CONFIRMED,
            ],
            'experience_id' => 0,
            'per_page' => 20,
            'paged' => 1,
        ];

        $args = wp_parse_args($args, $defaults);

        $statuses = array_filter((array) ($args['statuses'] ?? []));
        if (! $statuses) {
            $statuses = $defaults['statuses'];
        }

        $normalized_statuses = [];
        foreach ($statuses as $status) {
            $normalized_statuses[] = self::normalize_status((string) $status);
        }

        $placeholders = implode(',', array_fill(0, count($normalized_statuses), '%s'));
        $per_page = max(1, absint($args['per_page']));
        $paged = max(1, absint($args['paged']));
        $offset = ($paged - 1) * $per_page;

        $reservations_table = self::table_name();
        $slots_table = Slots::table_name();
        $posts_table = $wpdb->posts;

        $where = [];
        $params = $normalized_statuses;

        $where[] = "r.status IN ({$placeholders})";

        $experience_id = absint($args['experience_id']);
        if ($experience_id > 0) {
            $where[] = 'r.experience_id = %d';
            $params[] = $experience_id;
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = $wpdb->prepare(
            "SELECT r.*, s.start_datetime, s.end_datetime, p.post_title AS experience_title FROM {$reservations_table} r " .
            "LEFT JOIN {$slots_table} s ON r.slot_id = s.id " .
            "LEFT JOIN {$posts_table} p ON r.experience_id = p.ID {$where_clause} " .
            'ORDER BY r.created_at DESC LIMIT %d OFFSET %d',
            [...$params, $per_page, $offset]
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! $rows) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['pax'] = maybe_unserialize($row['pax']);
                $row['addons'] = maybe_unserialize($row['addons']);
                $row['utm'] = maybe_unserialize($row['utm']);
                $row['meta'] = maybe_unserialize($row['meta']);

                return $row;
            },
            $rows
        );
    }

    /**
     * Supported request workflow statuses and their labels.
     *
     * @return array<string, string>
     */
    public static function request_statuses(): array
    {
        return [
            self::STATUS_PENDING_REQUEST => __('Pending review', 'fp-experiences'),
            self::STATUS_APPROVED_CONFIRMED => __('Approved', 'fp-experiences'),
            self::STATUS_APPROVED_PENDING_PAYMENT => __('Waiting payment', 'fp-experiences'),
            self::STATUS_DECLINED => __('Declined', 'fp-experiences'),
        ];
    }

    /**
     * Update arbitrary reservation fields.
     *
     * @param array<string, mixed> $fields
     */
    public static function update_fields(int $reservation_id, array $fields): bool
    {
        global $wpdb;

        if ($reservation_id <= 0 || ! $fields) {
            return false;
        }

        $allowed = [
            'order_id',
            'customer_id',
            'status',
            'pax',
            'addons',
            'utm',
            'meta',
            'locale',
            'total_gross',
            'tax_total',
            'hold_expires_at',
        ];

        $data = [];

        foreach ($fields as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            switch ($key) {
                case 'order_id':
                case 'customer_id':
                    $data[$key] = absint($value);
                    break;
                case 'status':
                    $data[$key] = self::normalize_status((string) $value);
                    break;
                case 'pax':
                case 'addons':
                case 'utm':
                case 'meta':
                    $data[$key] = maybe_serialize(self::normalize_structure($value));
                    break;
                case 'total_gross':
                case 'tax_total':
                    $data[$key] = floatval($value);
                    break;
                case 'locale':
                    $data[$key] = sanitize_text_field((string) $value);
                    break;
                case 'hold_expires_at':
                    $data[$key] = self::normalize_datetime($value);
                    break;
            }
        }

        if (! $data) {
            return false;
        }

        $data['updated_at'] = current_time('mysql', true);

        $updated = $wpdb->update(
            self::table_name(),
            $data,
            [
                'id' => absint($reservation_id),
            ]
        );

        return false !== $updated && $updated > 0;
    }

    /**
     * Merge metadata payload with the stored record.
     *
     * @param array<string, mixed> $meta
     */
    public static function update_meta(int $reservation_id, array $meta): bool
    {
        $record = self::get($reservation_id);

        if (! $record) {
            return false;
        }

        $current = is_array($record['meta']) ? $record['meta'] : [];
        $merged = array_merge($current, $meta);

        return self::update_fields($reservation_id, ['meta' => $merged]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function upcoming(int $limit = 20): array
    {
        global $wpdb;

        $reservations_table = self::table_name();
        $slots_table = Slots::table_name();
        $limit = max(1, $limit);
        $now = gmdate('Y-m-d H:i:s');

        $sql = $wpdb->prepare(
            "SELECT r.*, s.start_datetime, s.end_datetime FROM {$reservations_table} r " .
            "LEFT JOIN {$slots_table} s ON r.slot_id = s.id " .
            "WHERE r.status != %s AND s.start_datetime IS NOT NULL AND s.start_datetime >= %s " .
            "ORDER BY s.start_datetime ASC LIMIT %d",
            self::STATUS_CANCELLED,
            $now,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! $rows) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['pax'] = maybe_unserialize($row['pax']);
                $row['addons'] = maybe_unserialize($row['addons']);
                $row['utm'] = maybe_unserialize($row['utm']);

                return $row;
            },
            $rows
        );
    }

    /**
     * Conta il numero di posti prenotati per uno slot virtuale specifico.
     * Utile per calcolare la capacità rimanente.
     *
     * @param int    $experience_id ID esperienza
     * @param string $start_utc     Data/ora inizio in formato SQL UTC
     * @param string $end_utc       Data/ora fine in formato SQL UTC
     *
     * @return int Numero totale di posti prenotati
     */
    public static function count_bookings_for_virtual_slot(int $experience_id, string $start_utc, string $end_utc): int
    {
        global $wpdb;

        if ($experience_id <= 0 || ! $start_utc || ! $end_utc) {
            return 0;
        }

        $reservations_table = self::table_name();
        $slots_table = Slots::table_name();

        // Stati che contano come prenotazioni attive
        $active_statuses = [
            self::STATUS_PENDING,
            self::STATUS_PENDING_REQUEST,
            self::STATUS_APPROVED_CONFIRMED,
            self::STATUS_APPROVED_PENDING_PAYMENT,
            self::STATUS_PAID,
            self::STATUS_CHECKED_IN,
        ];

        $placeholders = implode(',', array_fill(0, count($active_statuses), '%s'));

        // Nota: usando COALESCE con valore di default se pax è NULL
        // JSON_LENGTH è supportato da MySQL 5.7+ e MariaDB 10.2+
        $sql = $wpdb->prepare(
            "SELECT COALESCE(SUM(
                CASE 
                    WHEN r.pax IS NULL OR r.pax = '' THEN 1
                    ELSE JSON_LENGTH(r.pax)
                END
            ), 0) as total " .
            "FROM {$reservations_table} r " .
            "INNER JOIN {$slots_table} s ON r.slot_id = s.id " .
            "WHERE r.experience_id = %d " .
            "AND r.status IN ({$placeholders}) " .
            "AND s.start_datetime >= %s " .
            "AND s.start_datetime < %s",
            array_merge(
                [$experience_id],
                $active_statuses,
                [$start_utc, $end_utc]
            )
        );

        $result = $wpdb->get_var($sql);

        return (int) ($result ?? 0);
    }
}
