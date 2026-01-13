<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function get_post_meta;
use function get_transient;
use function is_array;
use function set_transient;
use function time;

/**
 * Helper for general utility functions.
 */
final class UtilityHelper
{
    /**
     * Get meta array with default fallback.
     *
     * @param array<string, mixed> $default
     *
     * @return array<string, mixed>
     */
    public static function getMetaArray(int $post_id, string $key, array $default = []): array
    {
        $meta = get_post_meta($post_id, $key, true);

        if (! is_array($meta)) {
            return $default;
        }

        return $meta;
    }

    /**
     * Check if rate limit is hit.
     *
     * @param string $key Rate limit key
     * @param int $limit Maximum requests
     * @param int $window Time window in seconds
     *
     * @return bool True if limit is hit
     */
    public static function hitRateLimit(string $key, int $limit, int $window): bool
    {
        $transient_key = 'fp_exp_rate_limit_' . $key;
        $current = get_transient($transient_key);

        if (false === $current) {
            $current = 0;
        }

        if (! is_numeric($current)) {
            $current = 0;
        }

        $current = (int) $current;

        if ($current >= $limit) {
            return true;
        }

        $current++;
        set_transient($transient_key, $current, $window);

        return false;
    }

    /**
     * Generate client fingerprint.
     */
    public static function clientFingerprint(): string
    {
        $components = [];

        // User agent
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $components[] = sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']);
        }

        // Accept language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $components[] = sanitize_text_field((string) $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }

        // Accept encoding
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $components[] = sanitize_text_field((string) $_SERVER['HTTP_ACCEPT_ENCODING']);
        }

        $fingerprint = implode('|', $components);

        return md5($fingerprint);
    }

    /**
     * Check if debug logging is enabled.
     */
    public static function isDebugLoggingEnabled(): bool
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        $option = get_option('fp_exp_debug_logging', false);

        return ! empty($option);
    }

    /**
     * Get currency code.
     */
    public static function currencyCode(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }

        return 'EUR';
    }
}















