<?php

declare(strict_types=1);

namespace FP_Exp\Gift;

use wpdb;

use function absint;
use function ctype_digit;
use function current_time;
use function dbDelta;
use function gmdate;
use function is_array;
use function is_numeric;
use function round;
use function sanitize_key;
use function sanitize_text_field;
use function strlen;
use function trim;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_parse_args;

use const ARRAY_A;

final class VoucherTable
{
    private const CACHE_GROUP = 'fp_exp_gift_voucher';

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fp_exp_gift_vouchers';
    }

    public static function create_table(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        
        // Try to get charset_collate from DatabaseInterface if available
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        $charset = '';
        
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(\FP_Exp\Services\Database\DatabaseInterface::class)) {
                try {
                    $database = $container->make(\FP_Exp\Services\Database\DatabaseInterface::class);
                    $charset = $database->getCharsetCollate();
                } catch (\Throwable $e) {
                    // Fall through to global $wpdb
                }
            }
        }
        
        // Fallback to global $wpdb for backward compatibility
        if (empty($charset)) {
            global $wpdb;
            $charset = $wpdb->get_charset_collate();
        }

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            voucher_id bigint(20) unsigned NOT NULL,
            code varchar(64) NOT NULL,
            status varchar(32) NOT NULL,
            experience_id bigint(20) unsigned NOT NULL DEFAULT 0,
            valid_until bigint(20) unsigned NOT NULL DEFAULT 0,
            value decimal(19,4) NOT NULL DEFAULT 0,
            currency varchar(8) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            UNIQUE KEY voucher_id (voucher_id),
            KEY status (status),
            KEY experience_id (experience_id),
            KEY valid_until (valid_until)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsert(array $data): void
    {
        global $wpdb;

        $defaults = [
            'voucher_id' => 0,
            'code' => '',
            'status' => 'pending',
            'experience_id' => 0,
            'valid_until' => 0,
            'value' => 0.0,
            'currency' => '',
            'created_at' => null,
            'updated_at' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        $voucher_id = absint($data['voucher_id']);
        $code = sanitize_key((string) $data['code']);

        if ($voucher_id <= 0 || '' === $code) {
            return;
        }

        $status = sanitize_key((string) $data['status']);
        if ('' === $status) {
            $status = 'pending';
        }

        $experience_id = absint($data['experience_id']);
        $valid_until = absint($data['valid_until']);
        $value = is_numeric($data['value']) ? (float) $data['value'] : 0.0;
        $currency = sanitize_text_field((string) $data['currency']);

        $table = self::table_name();

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, created_at FROM {$table} WHERE voucher_id = %d LIMIT 1",
                $voucher_id
            ),
            ARRAY_A
        );

        $created_at = self::normalize_datetime($data['created_at']);
        $updated_at = self::normalize_datetime($data['updated_at']);
        $now = current_time('mysql', true);

        if (! $created_at) {
            if (is_array($existing) && ! empty($existing['created_at'])) {
                $created_at = $existing['created_at'];
            } else {
                $created_at = $now;
            }
        }

        if (! $updated_at) {
            $updated_at = $now;
        }

        $record = [
            'voucher_id' => $voucher_id,
            'code' => $code,
            'status' => $status,
            'experience_id' => $experience_id,
            'valid_until' => $valid_until,
            'value' => round($value, 4),
            'currency' => $currency,
            'created_at' => $created_at,
            'updated_at' => $updated_at,
        ];

        $formats = ['%d', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s'];

        if (is_array($existing) && isset($existing['id'])) {
            $record = array_merge(['id' => absint($existing['id'])], $record);
            array_unshift($formats, '%d');
        }

        $wpdb->replace($table, $record, $formats);
        wp_cache_delete($code, self::CACHE_GROUP);
        wp_cache_delete('id_' . $voucher_id, self::CACHE_GROUP);
    }

    public static function delete(int $voucher_id): void
    {
        global $wpdb;

        $voucher_id = absint($voucher_id);
        if ($voucher_id <= 0) {
            return;
        }

        $wpdb->delete(self::table_name(), ['voucher_id' => $voucher_id], ['%d']);
        wp_cache_delete('id_' . $voucher_id, self::CACHE_GROUP);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_by_code(string $code): ?array
    {
        global $wpdb;

        $code = sanitize_key($code);
        if ('' === $code) {
            return null;
        }

        $cached = wp_cache_get($code, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table_name() . " WHERE code = %s LIMIT 1",
                $code
            ),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        wp_cache_set($code, $row, self::CACHE_GROUP, 15);
        wp_cache_set('id_' . absint($row['voucher_id'] ?? 0), $row, self::CACHE_GROUP, 15);

        return $row;
    }

    /**
     * @param int|string|null $value
     */
    private static function normalize_datetime($value): ?string
    {
        if (is_int($value) && $value > 0) {
            return gmdate('Y-m-d H:i:s', $value);
        }

        if (is_string($value)) {
            $value = trim($value);

            if ('' !== $value) {
                if (ctype_digit($value)) {
                    return gmdate('Y-m-d H:i:s', (int) $value);
                }

                if (strlen($value) >= 10) {
                    return $value;
                }
            }
        }

        return null;
    }
}
