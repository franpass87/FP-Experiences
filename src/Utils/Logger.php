<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use function array_filter;
use function array_reverse;
use function array_slice;
use function current_time;
use function fclose;
use function fopen;
use function fputcsv;
use function get_option;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function rewind;
use function stream_get_contents;
use function update_option;
use function wp_json_encode;
use function wp_parse_args;

final class Logger
{
    /**
     * Write a structured entry to the fp_exp_logs option.
     *
     * @param array<string, mixed> $context
     */
    public static function log(string $channel, string $message, array $context = []): void
    {
        $channel = sanitize_key($channel);
        $message = sanitize_text_field($message);

        $logs = get_option('fp_exp_logs', []);
        if (! is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'timestamp' => current_time('mysql'),
            'channel' => $channel,
            'message' => $message,
            'context' => self::scrub_context($context),
        ];

        // Keep the log list reasonably small to avoid bloating the options table.
        $logs = array_slice($logs, -200);

        update_option('fp_exp_logs', $logs, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function latest(int $limit = 50): array
    {
        return self::query([
            'limit' => $limit,
        ]);
    }

    public static function clear(): void
    {
        update_option('fp_exp_logs', [], false);
    }

    /**
     * Query logs with optional filters.
     *
     * @param array<string, mixed> $args
     *
     * @return array<int, array<string, mixed>>
     */
    public static function query(array $args = []): array
    {
        $defaults = [
            'limit' => 100,
            'channel' => '',
            'search' => '',
            'order' => 'desc',
        ];

        $args = wp_parse_args($args, $defaults);

        $logs = get_option('fp_exp_logs', []);
        if (! is_array($logs)) {
            return [];
        }

        $channel = $args['channel'] ? sanitize_key((string) $args['channel']) : '';
        $search = $args['search'] ? sanitize_text_field((string) $args['search']) : '';
        $limit = (int) $args['limit'];
        $order = 'asc' === strtolower((string) $args['order']) ? 'asc' : 'desc';

        $filtered = array_filter(
            $logs,
            static function (array $entry) use ($channel, $search): bool {
                if ($channel && ($entry['channel'] ?? '') !== $channel) {
                    return false;
                }

                if ($search) {
                    $haystack = strtolower(($entry['message'] ?? '') . wp_json_encode($entry['context'] ?? []));
                    if (false === stripos($haystack, strtolower($search))) {
                        return false;
                    }
                }

                return true;
            }
        );

        if ('desc' === $order) {
            $filtered = array_reverse($filtered);
        }

        if ($limit > 0) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return array_values($filtered);
    }

    /**
     * @return array<int, string>
     */
    public static function channels(): array
    {
        $logs = get_option('fp_exp_logs', []);
        if (! is_array($logs)) {
            return [];
        }

        $channels = [];
        foreach ($logs as $entry) {
            if (empty($entry['channel'])) {
                continue;
            }
            $key = sanitize_key((string) $entry['channel']);
            if ('' === $key) {
                continue;
            }
            $channels[$key] = $key;
        }

        sort($channels);

        return array_values($channels);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public static function export_csv(array $entries): string
    {
        $handle = fopen('php://temp', 'r+');

        if (! $handle) {
            return '';
        }

        fputcsv($handle, ['timestamp', 'channel', 'message', 'context']);

        foreach ($entries as $entry) {
            $context = wp_json_encode($entry['context'] ?? []);
            fputcsv($handle, [
                $entry['timestamp'] ?? '',
                $entry['channel'] ?? '',
                $entry['message'] ?? '',
                $context,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    /**
     * @param mixed $context
     *
     * @return mixed
     */
    private static function scrub_context($context)
    {
        if (is_array($context)) {
            $sanitised = [];
            foreach ($context as $key => $value) {
                $sanitised[$key] = self::scrub_context_with_key((string) $key, $value);
            }

            return $sanitised;
        }

        return $context;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function scrub_context_with_key(string $key, $value)
    {
        if (is_array($value)) {
            return self::scrub_context($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $lower = $key ? strtolower($key) : '';

        if (false !== strpos($lower, 'email')) {
            return self::mask_email($value);
        }

        if (false !== strpos($lower, 'phone')) {
            return self::mask_string($value);
        }

        if (false !== strpos($lower, 'token') || false !== strpos($lower, 'secret') || false !== strpos($lower, 'api') || false !== strpos($lower, 'key')) {
            return '***';
        }

        return $value;
    }

    private static function mask_email(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return self::mask_string($email);
        }

        $name = $parts[0];
        $domain = $parts[1];
        $masked_name = strlen($name) > 2 ? substr($name, 0, 2) . '***' : '***';

        return $masked_name . '@' . $domain;
    }

    private static function mask_string(string $value): string
    {
        $length = strlen($value);
        if ($length <= 4) {
            return '***';
        }

        return '***' . substr($value, -4);
    }
}
