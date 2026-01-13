<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function apply_filters;
use function get_option;
use function get_post_meta;
use function is_array;
use function is_numeric;

/**
 * Helper for RTB (Real-Time Booking) related functions.
 */
final class RTBHelper
{
    /**
     * Get RTB settings.
     *
     * @return array<string, mixed>
     */
    public static function getSettings(): array
    {
        $settings = get_option('fp_exp_rtb', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Get RTB mode.
     */
    public static function getMode(): string
    {
        $settings = self::getSettings();

        return $settings['mode'] ?? 'disabled';
    }

    /**
     * Get RTB mode for specific experience.
     */
    public static function getModeForExperience(int $experience_id): string
    {
        $meta = get_post_meta($experience_id, '_fp_exp_rtb_mode', true);

        if (is_string($meta) && $meta !== '') {
            return $meta;
        }

        return self::getMode();
    }

    /**
     * Get RTB hold timeout in seconds.
     */
    public static function getHoldTimeout(): int
    {
        $settings = self::getSettings();
        $timeout = isset($settings['hold_timeout']) && is_numeric($settings['hold_timeout'])
            ? (int) $settings['hold_timeout']
            : 600;

        return max(60, $timeout);
    }

    /**
     * Check if experience uses RTB.
     */
    public static function experienceUsesRTB(int $experience_id): bool
    {
        $mode = self::getModeForExperience($experience_id);

        return $mode !== 'disabled';
    }

    /**
     * Check if RTB blocks capacity.
     */
    public static function blocksCapacity(int $experience_id): bool
    {
        $mode = self::getModeForExperience($experience_id);

        if ($mode === 'disabled') {
            return false;
        }

        $meta = get_post_meta($experience_id, '_fp_exp_rtb_block_capacity', true);

        return ! empty($meta);
    }

    /**
     * Check if meeting points are enabled.
     */
    public static function meetingPointsEnabled(): bool
    {
        $settings = self::getSettings();

        return ! empty($settings['meeting_points_enabled']);
    }

    /**
     * Check if meeting points import is enabled.
     */
    public static function meetingPointsImportEnabled(): bool
    {
        $settings = self::getSettings();

        return ! empty($settings['meeting_points_import_enabled']);
    }
}















