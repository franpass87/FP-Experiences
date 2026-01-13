<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function get_option;
use function is_array;
use function json_decode;
use function sanitize_text_field;
use function stripslashes;

/**
 * Helper for tracking and analytics related functions.
 */
final class TrackingHelper
{
    /**
     * Get tracking settings.
     *
     * @return array<string, mixed>
     */
    public static function getSettings(): array
    {
        $settings = get_option('fp_exp_tracking', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Get tracking configuration.
     *
     * @return array<string, mixed>
     */
    public static function getConfig(): array
    {
        $settings = self::getSettings();

        $config = [
            'enabled' => ! empty($settings['enabled']),
            'gtm_id' => isset($settings['gtm_id']) ? sanitize_text_field((string) $settings['gtm_id']) : '',
            'ga_id' => isset($settings['ga_id']) ? sanitize_text_field((string) $settings['ga_id']) : '',
            'consent_defaults' => isset($settings['consent_defaults']) && is_array($settings['consent_defaults'])
                ? array_map(static fn ($value) => ! empty($value), $settings['consent_defaults'])
                : [],
        ];

        return $config;
    }

    /**
     * Read UTM cookie.
     *
     * @return array<string, string>
     */
    public static function readUtmCookie(): array
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
}















