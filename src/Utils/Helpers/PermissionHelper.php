<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function get_role;
use function in_array;
use function is_user_logged_in;
use function wp_get_current_user;

/**
 * Helper for permission and capability checks.
 */
final class PermissionHelper
{
    /**
     * Check if user can manage FP Experiences.
     */
    public static function canManage(): bool
    {
        $user = wp_get_current_user();

        if ($user && ! empty($user->allcaps['fp_exp_admin_access'])) {
            return true;
        }

        if ($user && ! empty($user->allcaps['fp_exp_manage'])) {
            return true;
        }

        return $user && ! empty($user->allcaps['manage_options']);
    }

    /**
     * Check if user can operate FP Experiences.
     */
    public static function canOperate(): bool
    {
        $user = wp_get_current_user();

        if ($user && ! empty($user->allcaps['fp_exp_operate'])) {
            return true;
        }

        if ($user && (! empty($user->allcaps['manage_woocommerce']) || ! empty($user->allcaps['edit_shop_orders']))) {
            return true;
        }

        return self::canManage();
    }

    /**
     * Check if user can access guides.
     */
    public static function canAccessGuides(): bool
    {
        $user = wp_get_current_user();

        if ($user && ! empty($user->allcaps['fp_exp_guide'])) {
            return true;
        }

        return self::canOperate();
    }

    /**
     * Get management capability name.
     */
    public static function managementCapability(): string
    {
        $user = wp_get_current_user();

        if ($user && ! empty($user->allcaps['fp_exp_admin_access'])) {
            return 'fp_exp_admin_access';
        }

        if ($user && ! empty($user->allcaps['fp_exp_manage'])) {
            return 'fp_exp_manage';
        }

        return 'manage_options';
    }

    /**
     * Get operations capability name.
     */
    public static function operationsCapability(): string
    {
        $user = wp_get_current_user();

        if ($user && ! empty($user->allcaps['fp_exp_operate'])) {
            return 'fp_exp_operate';
        }

        if ($user && ! empty($user->allcaps['fp_exp_manage'])) {
            return 'fp_exp_manage';
        }

        if ($user && ! empty($user->allcaps['fp_exp_admin_access'])) {
            return 'fp_exp_admin_access';
        }

        if ($user && ! empty($user->allcaps['manage_woocommerce'])) {
            return 'manage_woocommerce';
        }

        return self::managementCapability();
    }

    /**
     * Get guide capability name.
     */
    public static function guideCapability(): string
    {
        $user = wp_get_current_user();

        if ($user && ! empty($user->allcaps['fp_exp_guide'])) {
            return 'fp_exp_guide';
        }

        if ($user && ! empty($user->allcaps['fp_exp_operate'])) {
            return 'fp_exp_operate';
        }

        if ($user && ! empty($user->allcaps['fp_exp_manage'])) {
            return 'fp_exp_manage';
        }

        if ($user && ! empty($user->allcaps['fp_exp_admin_access'])) {
            return 'fp_exp_admin_access';
        }

        if ($user && ! empty($user->allcaps['manage_woocommerce'])) {
            return 'manage_woocommerce';
        }

        return self::managementCapability();
    }

    /**
     * Ensure that administrators (role and current user) always retain the FP Experiences capabilities.
     *
     * Called on admin_init to repair installations where role propagation did not run correctly.
     */
    public static function ensureAdminCapabilities(): void
    {
        if (! is_user_logged_in()) {
            return;
        }

        $debug = defined('WP_DEBUG') && WP_DEBUG;
        if ($debug) {
            error_log('[FP Experiences] ensure_admin_capabilities triggered');
        }

        $caps = [
            'fp_exp_admin_access',
            'fp_exp_manage',
            'fp_exp_operate',
            'fp_exp_guide',
        ];

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($caps as $cap) {
                if (! $admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }

        $user = wp_get_current_user();
        if (! $user || ! $user->exists()) {
            return;
        }

        $is_administrator = in_array('administrator', (array) $user->roles, true) || $user->has_cap('manage_options');
        if (! $is_administrator) {
            return;
        }

        foreach ($caps as $cap) {
            if (! $user->has_cap($cap)) {
                $user->add_cap($cap);
                if ($debug) {
                    error_log('[FP Experiences] Added cap to user: ' . $cap);
                }
            }
        }
    }
}















