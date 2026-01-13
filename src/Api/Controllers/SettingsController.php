<?php

declare(strict_types=1);

namespace FP_Exp\Api\Controllers;

use FP_Exp\Application\Settings\GetSettingsUseCase;
use FP_Exp\Application\Settings\UpdateSettingsUseCase;
use FP_Exp\Core\Container\ContainerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for settings operations.
 */
final class SettingsController
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get settings.
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function getSettings(WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'fp_exp_unauthorized',
                'Unauthorized',
                ['status' => 403]
            );
        }

        try {
            $useCase = $this->container->make(GetSettingsUseCase::class);
            $settings = $useCase->execute();

            return new WP_REST_Response([
                'success' => true,
                'data' => $settings,
            ], 200);
        } catch (\Throwable $e) {
            return new \WP_Error(
                'fp_exp_settings_error',
                'Failed to get settings: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update settings.
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function updateSettings(WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'fp_exp_unauthorized',
                'Unauthorized',
                ['status' => 403]
            );
        }

        $settings = $request->get_json_params();

        if (empty($settings)) {
            return new \WP_Error(
                'fp_exp_invalid_settings',
                'Settings data is required',
                ['status' => 400]
            );
        }

        try {
            $useCase = $this->container->make(UpdateSettingsUseCase::class);
            $result = $useCase->execute($settings);

            if (is_wp_error($result)) {
                return $result;
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Settings updated successfully',
            ], 200);
        } catch (\Throwable $e) {
            return new \WP_Error(
                'fp_exp_settings_error',
                'Failed to update settings: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Permission callback for settings.
     *
     * @param WP_REST_Request $request REST request
     * @return bool True if allowed
     */
    public function checkPermission(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }
}







