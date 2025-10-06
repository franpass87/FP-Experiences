<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use wpdb;

use function current_time;
use function maybe_serialize;
use function sanitize_text_field;
use function wp_parse_args;

final class Resources
{
    public const TYPE_GUIDE = 'guide';
    public const TYPE_ROOM = 'room';
    public const TYPE_VEHICLE = 'vehicle';

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fp_exp_resources';
    }

    public static function create_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type ENUM('guide','room','vehicle') NOT NULL DEFAULT 'guide',
            name VARCHAR(191) NOT NULL,
            calendar LONGTEXT NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type_lookup (type)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Prepare resource payload for persistence.
     *
     * @param array<string, mixed> $data Raw resource data.
     *
     * @return array<string, mixed>
     */
    public static function prepare_for_storage(array $data): array
    {
        $defaults = [
            'type' => self::TYPE_GUIDE,
            'name' => '',
            'calendar' => [],
            'notes' => '',
        ];

        $data = wp_parse_args($data, $defaults);

        return [
            'type' => self::normalize_type((string) $data['type']),
            'name' => sanitize_text_field((string) $data['name']),
            'calendar' => maybe_serialize(self::normalize_structure($data['calendar'])),
            'notes' => sanitize_text_field((string) $data['notes']),
            'updated_at' => current_time('mysql', true),
        ];
    }

    public static function normalize_type(string $type): string
    {
        $type = strtolower($type);

        if (! in_array($type, [self::TYPE_GUIDE, self::TYPE_ROOM, self::TYPE_VEHICLE], true)) {
            return self::TYPE_GUIDE;
        }

        return $type;
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
