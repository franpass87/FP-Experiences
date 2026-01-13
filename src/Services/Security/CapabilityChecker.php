<?php

declare(strict_types=1);

namespace FP_Exp\Services\Security;

use function current_user_can;
use function user_can;
use function get_current_user_id;
use function get_userdata;
use function wp_die;

/**
 * Capability checker service for permission validation.
 */
final class CapabilityChecker
{
    /**
     * Check if current user has a capability.
     *
     * @param string $capability Capability to check
     * @param int|null $objectId Optional object ID for meta capabilities
     * @return bool True if user has capability, false otherwise
     */
    public function currentUserCan(string $capability, ?int $objectId = null): bool
    {
        if ($objectId !== null) {
            return current_user_can($capability, $objectId);
        }

        return current_user_can($capability);
    }

    /**
     * Check if a specific user has a capability.
     *
     * @param int|object $user User ID or user object
     * @param string $capability Capability to check
     * @param int|null $objectId Optional object ID for meta capabilities
     * @return bool True if user has capability, false otherwise
     */
    public function userCan(int|object $user, string $capability, ?int $objectId = null): bool
    {
        if ($objectId !== null) {
            return user_can($user, $capability, $objectId);
        }

        return user_can($user, $capability);
    }

    /**
     * Check if current user can manage FP Experiences.
     *
     * @return bool True if user can manage, false otherwise
     */
    public function canManage(): bool
    {
        return $this->currentUserCan('fp_exp_admin_access');
    }

    /**
     * Check if current user can operate FP Experiences.
     *
     * @return bool True if user can operate, false otherwise
     */
    public function canOperate(): bool
    {
        return $this->currentUserCan('fp_exp_operate');
    }

    /**
     * Check if current user is a guide.
     *
     * @return bool True if user is a guide, false otherwise
     */
    public function isGuide(): bool
    {
        return $this->currentUserCan('fp_exp_guide');
    }

    /**
     * Require capability, die with error if not present.
     *
     * @param string $capability Capability to check
     * @param string|null $message Optional error message
     * @return void
     */
    public function requireCapability(string $capability, ?string $message = null): void
    {
        if (!$this->currentUserCan($capability)) {
            $message = $message ?? 'Non hai i permessi necessari per eseguire questa azione.';
            wp_die(esc_html($message));
        }
    }
}

