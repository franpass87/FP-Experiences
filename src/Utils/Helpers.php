<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use function absint;
use function array_map;
use function explode;
use function get_current_user_id;
use function get_option;
use function get_post_meta;
use function get_transient;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_decode;
use function sanitize_text_field;
use function set_transient;
use function stripslashes;
use function time;
use function trim;
use function wp_parse_args;
use function wp_unslash;

final class Helpers
{
    /**
     * @return array<string, mixed>
     */
    public static function tracking_settings(): array
    {
        $settings = get_option('fp_exp_tracking', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Build a serialisable config array for front-end scripts.
     *
     * @return array<string, mixed>
     */
    public static function tracking_config(): array
    {
        $settings = self::tracking_settings();

        $channels = [
            'ga4' => isset($settings['ga4']) && is_array($settings['ga4']) ? $settings['ga4'] : [],
            'google_ads' => isset($settings['google_ads']) && is_array($settings['google_ads']) ? $settings['google_ads'] : [],
            'meta_pixel' => isset($settings['meta_pixel']) && is_array($settings['meta_pixel']) ? $settings['meta_pixel'] : [],
            'clarity' => isset($settings['clarity']) && is_array($settings['clarity']) ? $settings['clarity'] : [],
        ];

        $enabled = [
            'ga4' => ! empty($channels['ga4']['enabled']) && (! empty($channels['ga4']['gtm_id']) || ! empty($channels['ga4']['measurement_id'])) && Consent::granted(Consent::CHANNEL_GA4),
            'google_ads' => ! empty($channels['google_ads']['enabled']) && ! empty($channels['google_ads']['conversion_id']) && Consent::granted(Consent::CHANNEL_GOOGLE_ADS),
            'meta_pixel' => ! empty($channels['meta_pixel']['enabled']) && ! empty($channels['meta_pixel']['pixel_id']) && Consent::granted(Consent::CHANNEL_META),
            'clarity' => ! empty($channels['clarity']['enabled']) && ! empty($channels['clarity']['project_id']) && Consent::granted(Consent::CHANNEL_CLARITY),
        ];

        $consent_defaults = isset($settings['consent_defaults']) && is_array($settings['consent_defaults'])
            ? array_map(static fn ($value) => ! empty($value), $settings['consent_defaults'])
            : [];

        return [
            'enabled' => $enabled,
            'ga4' => $channels['ga4'],
            'googleAds' => $channels['google_ads'],
            'metaPixel' => $channels['meta_pixel'],
            'clarity' => $channels['clarity'],
            'consentDefaults' => $consent_defaults,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rtb_settings(): array
    {
        $settings = get_option('fp_exp_rtb', []);
        $settings = is_array($settings) ? $settings : [];

        $defaults = [
            'mode' => 'off',
            'timeout' => 30,
            'block_capacity' => false,
            'templates' => [],
            'fallback' => [],
        ];

        $settings = wp_parse_args($settings, $defaults);

        $mode = is_string($settings['mode']) ? strtolower(sanitize_text_field($settings['mode'])) : 'off';
        if (! in_array($mode, ['off', 'confirm', 'pay_later'], true)) {
            $mode = 'off';
        }

        $settings['mode'] = $mode;
        $settings['timeout'] = max(5, absint($settings['timeout']));
        $settings['block_capacity'] = ! empty($settings['block_capacity']);
        $settings['templates'] = is_array($settings['templates']) ? $settings['templates'] : [];
        $settings['fallback'] = is_array($settings['fallback']) ? $settings['fallback'] : [];

        return $settings;
    }

    public static function rtb_mode(): string
    {
        $settings = self::rtb_settings();

        return (string) ($settings['mode'] ?? 'off');
    }

    public static function rtb_mode_for_experience(int $experience_id): string
    {
        if ($experience_id > 0 && ! self::experience_uses_rtb($experience_id)) {
            return 'off';
        }

        return self::rtb_mode();
    }

    public static function rtb_hold_timeout(): int
    {
        $settings = self::rtb_settings();

        return max(5, absint($settings['timeout'] ?? 30));
    }

    public static function experience_uses_rtb(int $experience_id): bool
    {
        if ($experience_id <= 0) {
            return false;
        }

        $value = get_post_meta($experience_id, '_fp_use_rtb', true);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        $normalized = strtolower((string) $value);

        return in_array($normalized, ['1', 'yes', 'true', 'on'], true);
    }

    public static function rtb_block_capacity(int $experience_id): bool
    {
        $settings = self::rtb_settings();

        if ('off' === $settings['mode']) {
            return false;
        }

        if ($experience_id > 0 && ! self::experience_uses_rtb($experience_id)) {
            return false;
        }

        return ! empty($settings['block_capacity']);
    }

    public static function meeting_points_enabled(): bool
    {
        $value = get_option('fp_exp_enable_meeting_points', 'yes');

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return ! in_array($normalized, ['0', 'no', 'off', 'false', ''], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<string, string>
     */
    public static function read_utm_cookie(): array
    {
        if (empty($_COOKIE['fp_exp_utm'])) {
            return [];
        }

        $decoded = json_decode(stripslashes((string) $_COOKIE['fp_exp_utm']), true);

        if (! is_array($decoded)) {
            return [];
        }

        $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid'];
        $sanitised = [];

        foreach ($allowed as $key) {
            if (! empty($decoded[$key])) {
                $sanitised[$key] = sanitize_text_field((string) $decoded[$key]);
            }
        }

        return $sanitised;
    }

    public static function hit_rate_limit(string $key, int $limit, int $window): bool
    {
        $limit = max(1, $limit);
        $window = max(1, $window);

        $bucket_key = 'fp_exp_rl_' . md5($key);
        $bucket = get_transient($bucket_key);
        $now = time();

        if (! is_array($bucket) || empty($bucket['expires']) || $bucket['expires'] <= $now) {
            set_transient($bucket_key, [
                'count' => 1,
                'expires' => $now + $window,
            ], $window);

            return false;
        }

        if (($bucket['count'] ?? 0) >= $limit) {
            return true;
        }

        $bucket['count'] = ($bucket['count'] ?? 0) + 1;
        $ttl = max(1, (int) $bucket['expires'] - $now);
        set_transient($bucket_key, $bucket, $ttl);

        return false;
    }

    public static function client_fingerprint(): string
    {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return 'user_' . $user_id;
        }

        $candidates = [];

        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
            $forwarded = sanitize_text_field((string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
            foreach (explode(',', $forwarded) as $ip) {
                $ip = trim($ip);
                if ($ip) {
                    $candidates[] = $ip;
                }
            }
        }

        if (! empty($_SERVER['REMOTE_ADDR'])) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
            $candidates[] = sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
        }

        $identifier = '';
        foreach ($candidates as $candidate) {
            if ($candidate) {
                $identifier = $candidate;
                break;
            }
        }

        if ('' === $identifier) {
            return 'guest_anon';
        }

        return 'ip_' . hash('sha256', $identifier);
    }
}
