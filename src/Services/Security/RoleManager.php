<?php

declare(strict_types=1);

namespace FP_Exp\Services\Security;

use FP_Exp\Activation;
use FP_Exp\Services\Options\OptionsInterface;
use WP_User;

use function get_role;
use function in_array;
use function is_admin;
use function wp_get_current_user;

/**
 * Manages WordPress roles and capabilities for FP Experiences.
 */
final class RoleManager
{
    private ?OptionsInterface $options = null;

    /**
     * RoleManager constructor.
     *
     * @param OptionsInterface|null $options Optional OptionsInterface (will try to get from container if not provided)
     */
    public function __construct(?OptionsInterface $options = null)
    {
        $this->options = $options;
    }

    /**
     * Get OptionsInterface instance.
     * Tries container first, falls back to direct instantiation for backward compatibility.
     */
    private function getOptions(): OptionsInterface
    {
        if ($this->options !== null) {
            return $this->options;
        }

        // Try to get from container
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(OptionsInterface::class)) {
                try {
                    $this->options = $container->make(OptionsInterface::class);
                    return $this->options;
                } catch (\Throwable $e) {
                    // Fall through to direct instantiation
                }
            }
        }

        // Fallback to direct instantiation
        $this->options = new \FP_Exp\Services\Options\Options();
        return $this->options;
    }

    /**
     * Update roles and capabilities if needed.
     */
    public function maybeUpdateRoles(): void
    {
        if (! is_admin()) {
            return;
        }

        $current_version = Activation::roles_version();
        $stored_version = $this->getOptions()->get('fp_exp_roles_version');

        $administrator = get_role('administrator');
        $administrator_missing_caps = false;
        /** @var array<string, bool> $manager_capabilities */
        $manager_capabilities = Activation::manager_capabilities();
        $current_user = wp_get_current_user();
        $current_user_missing_caps = false;

        if ($administrator) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if (! empty($administrator->capabilities[$capability])) {
                    continue;
                }

                $administrator_missing_caps = true;
                break;
            }
        }

        if ($current_user instanceof WP_User && in_array('administrator', $current_user->roles, true)) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if (! empty($current_user->allcaps[$capability])) {
                    continue;
                }

                $current_user_missing_caps = true;
                break;
            }
        }

        if ($stored_version === $current_version && ! $administrator_missing_caps && ! $current_user_missing_caps) {
            return;
        }

        Activation::register_roles();
        $this->getOptions()->set('fp_exp_roles_version', $current_version);

        if ($current_user_missing_caps && $current_user instanceof WP_User) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if (! empty($current_user->allcaps[$capability])) {
                    continue;
                }

                $current_user->add_cap($capability);
            }
        }
    }

    /**
     * Map meta capabilities fallback.
     *
     * @param array<string> $caps Capabilities
     * @param string $cap Capability name
     * @param int $user_id User ID
     * @param array<mixed> $args Arguments
     * @return array<string> Capabilities
     */
    public function mapMetaCapFallback(array $caps, string $cap, int $user_id, array $args): array
    {
        if (! in_array($cap, ['delete_post', 'edit_post', 'read_post'], true)) {
            return $caps;
        }

        if (! empty($args)) {
            return $caps;
        }

        $caps = ['fp_exp_admin_access'];

        return $caps;
    }
}

