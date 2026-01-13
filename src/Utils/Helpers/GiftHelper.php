<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function apply_filters;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function get_option;
use function is_array;
use function is_numeric;
use function trim;

/**
 * Helper for gift voucher related functions.
 */
final class GiftHelper
{
    /**
     * Get gift settings.
     *
     * @return array<string, mixed>
     */
    public static function getSettings(): array
    {
        $settings = get_option('fp_exp_gift', []);

        if (! is_array($settings)) {
            return [];
        }

        // Parse reminder offsets
        $reminders = isset($settings['reminder_offsets']) && is_string($settings['reminder_offsets'])
            ? $settings['reminder_offsets']
            : '';

        if ($reminders) {
            $reminders = array_map('trim', explode(',', $reminders));
        } else {
            $reminders = [];
        }

        $reminders = array_values(array_unique(array_filter(array_map(static function ($value) {
            if (! is_numeric($value)) {
                return null;
            }

            $days = (int) $value;

            return $days > 0 ? $days : null;
        }, $reminders))));

        $settings['reminder_offsets'] = $reminders;

        return $settings;
    }

    /**
     * Check if gift vouchers are enabled.
     */
    public static function isEnabled(): bool
    {
        $settings = self::getSettings();

        return ! empty($settings['enabled']);
    }

    /**
     * Get gift validity days.
     */
    public static function getValidityDays(): int
    {
        $settings = self::getSettings();
        $days = isset($settings['validity_days']) && is_numeric($settings['validity_days'])
            ? (int) $settings['validity_days']
            : 365;

        return max(1, $days);
    }

    /**
     * Get gift reminder offsets.
     *
     * @return array<int>
     */
    public static function getReminderOffsets(): array
    {
        $settings = self::getSettings();

        return $settings['reminder_offsets'] ?? [];
    }

    /**
     * Get gift reminder time.
     */
    public static function getReminderTime(): string
    {
        $settings = self::getSettings();

        return $settings['reminder_time'] ?? '09:00';
    }

    /**
     * Get gift redeem page URL.
     */
    public static function getRedeemPage(): string
    {
        $settings = self::getSettings();
        $page_id = isset($settings['redeem_page_id']) && is_numeric($settings['redeem_page_id'])
            ? (int) $settings['redeem_page_id']
            : 0;

        if ($page_id > 0) {
            $url = get_permalink($page_id);

            return $url ?: '';
        }

        return '';
    }
}















