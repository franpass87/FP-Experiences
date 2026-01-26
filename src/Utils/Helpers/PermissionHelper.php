<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function get_role;
use function in_array;
use function is_user_logged_in;
use function wp_get_current_user;

/**
 * Helper for permission and capability checks.
 * 
 * Integrato con FP Restaurant Reservations per condividere i ruoli.
 * Gli utenti con ruoli di FP Restaurant (operatore) hanno automaticamente accesso anche a FP Experiences.
 */
final class PermissionHelper
{
    /**
     * Verifica se FP Restaurant Reservations è attivo.
     */
    private static function isRestaurantActive(): bool
    {
        return class_exists('\FP\Resv\Core\Roles');
    }

    /**
     * Ottiene i ruoli di FP Restaurant che hanno accesso alle prenotazioni.
     * 
     * @return array<string> Array di nomi di ruoli
     */
    private static function getRestaurantRoles(): array
    {
        if (! self::isRestaurantActive()) {
            return [];
        }

        $roles = [];
        
        // Ruoli standard di FP Restaurant
        $restaurant_manager = get_role('fp_restaurant_manager');
        $reservations_viewer = get_role('fp_reservations_viewer');
        
        if ($restaurant_manager) {
            $roles[] = 'fp_restaurant_manager';
        }
        
        if ($reservations_viewer) {
            $roles[] = 'fp_reservations_viewer';
        }

        return $roles;
    }

    /**
     * Verifica se l'utente ha accesso a FP Restaurant.
     */
    private static function userHasRestaurantAccess(): bool
    {
        if (! self::isRestaurantActive()) {
            return false;
        }

        $user = wp_get_current_user();
        if (! $user || ! $user->exists()) {
            return false;
        }

        // Verifica se l'utente ha le capabilities di FP Restaurant
        return $user->has_cap('manage_fp_reservations') 
            || $user->has_cap('view_fp_reservations_manager');
    }

    /**
     * Check if user can manage FP Experiences.
     * 
     * Ora verifica anche l'accesso a FP Restaurant.
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

        // Se l'utente ha accesso a FP Restaurant, può gestire anche FP Experiences
        if (self::userHasRestaurantAccess()) {
            return true;
        }

        return $user && ! empty($user->allcaps['manage_options']);
    }

    /**
     * Check if user can operate FP Experiences.
     * 
     * Ora verifica anche l'accesso a FP Restaurant.
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

        // Se l'utente ha accesso a FP Restaurant, può operare anche FP Experiences
        if (self::userHasRestaurantAccess()) {
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

        // Se l'utente ha accesso a FP Restaurant, usa quella capability
        if (self::userHasRestaurantAccess()) {
            if ($user->has_cap('manage_fp_reservations')) {
                return 'manage_fp_reservations';
            }
            if ($user->has_cap('view_fp_reservations_manager')) {
                return 'view_fp_reservations_manager';
            }
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

        // Se l'utente ha accesso a FP Restaurant, usa quella capability
        if (self::userHasRestaurantAccess()) {
            if ($user->has_cap('manage_fp_reservations')) {
                return 'manage_fp_reservations';
            }
            if ($user->has_cap('view_fp_reservations_manager')) {
                return 'view_fp_reservations_manager';
            }
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

        // Se l'utente ha accesso a FP Restaurant, usa quella capability
        if (self::userHasRestaurantAccess()) {
            if ($user->has_cap('manage_fp_reservations')) {
                return 'manage_fp_reservations';
            }
            if ($user->has_cap('view_fp_reservations_manager')) {
                return 'view_fp_reservations_manager';
            }
        }

        return self::managementCapability();
    }

    /**
     * Ensure that administrators (role and current user) always retain the FP Experiences capabilities.
     * 
     * Ora aggiunge anche le capabilities di FP Experiences ai ruoli di FP Restaurant,
     * in modo che gli operatori abbiano accesso ad entrambi i plugin.
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

        // Aggiungi capabilities agli amministratori
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($caps as $cap) {
                if (! $admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }

        // Se FP Restaurant è attivo, aggiungi le capabilities di FP Experiences ai suoi ruoli
        if (self::isRestaurantActive()) {
            $restaurant_roles = self::getRestaurantRoles();
            
            foreach ($restaurant_roles as $role_name) {
                $role = get_role($role_name);
                
                if ($role) {
                    foreach ($caps as $cap) {
                        if (! $role->has_cap($cap)) {
                            $role->add_cap($cap);
                            if ($debug) {
                                error_log("[FP Experiences] Added cap '{$cap}' to restaurant role '{$role_name}'");
                            }
                        }
                    }
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















