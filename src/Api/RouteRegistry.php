<?php

declare(strict_types=1);

namespace FP_Exp\Api;

use FP_Exp\Api\Controllers\AvailabilityController;
use FP_Exp\Api\Controllers\CalendarController;
use FP_Exp\Api\Controllers\DiagnosticController;
use FP_Exp\Api\Controllers\GiftController;
use FP_Exp\Api\Controllers\SettingsController;
use FP_Exp\Api\Controllers\ToolsController;
use FP_Exp\Api\Middleware\AuthenticationMiddleware;

/**
 * Registry for REST API routes.
 * 
 * Separates route registration logic from RestRoutes class.
 */
final class RouteRegistry
{
    private AvailabilityController $availability_controller;
    private CalendarController $calendar_controller;
    private ToolsController $tools_controller;
    private DiagnosticController $diagnostic_controller;
    private ?GiftController $gift_controller;
    private ?SettingsController $settings_controller;
    private RestRoutes $rest_routes;

    /**
     * @param AvailabilityController $availability_controller
     * @param CalendarController $calendar_controller
     * @param ToolsController $tools_controller
     * @param DiagnosticController $diagnostic_controller
     * @param RestRoutes $rest_routes Parent RestRoutes instance (for legacy callbacks)
     * @param GiftController|null $gift_controller
     * @param SettingsController|null $settings_controller
     */
    public function __construct(
        AvailabilityController $availability_controller,
        CalendarController $calendar_controller,
        ToolsController $tools_controller,
        DiagnosticController $diagnostic_controller,
        RestRoutes $rest_routes,
        ?GiftController $gift_controller = null,
        ?SettingsController $settings_controller = null
    ) {
        $this->availability_controller = $availability_controller;
        $this->calendar_controller = $calendar_controller;
        $this->tools_controller = $tools_controller;
        $this->diagnostic_controller = $diagnostic_controller;
        $this->rest_routes = $rest_routes;
        $this->gift_controller = $gift_controller;
        $this->settings_controller = $settings_controller;
    }

    /**
     * Register all REST API routes.
     */
    public function registerAll(): void
    {
        $this->registerAvailabilityRoutes();
        $this->registerCalendarRoutes();
        $this->registerToolsRoutes();
        $this->registerDiagnosticRoutes();
        $this->registerGiftRoutes();
        $this->registerSettingsRoutes();
        $this->registerCheckoutRoutes();
    }

    /**
     * Register availability routes.
     */
    private function registerAvailabilityRoutes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/availability',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'publicEndpoint'],
                'callback' => [$this->availability_controller, 'getVirtualAvailability'],
                'args' => [
                    'experience' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'start' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'end' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    /**
     * Register calendar routes.
     */
    private function registerCalendarRoutes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/calendar/slots',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this->calendar_controller, 'getSlots'],
                'args' => [
                    'start' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'end' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'experience' => [
                        'required' => false,
                        'sanitize_callback' => 'absint',
                    ],
                    'view' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]
        );

        // Legacy routes that still use RestRoutes callbacks
        register_rest_route(
            'fp-exp/v1',
            '/calendar/slot/(?P<id>\d+)/move',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this->rest_routes, 'move_calendar_slot'],
                'args' => [
                    'start' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'end' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/slot/capacity/(?P<id>\d+)',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this->rest_routes, 'update_slot_capacity'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/recurrence/preview',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this->rest_routes, 'preview_recurrence_slots'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/recurrence/generate',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this->rest_routes, 'generate_recurrence_slots'],
            ]
        );
    }

    /**
     * Register tools routes.
     */
    private function registerToolsRoutes(): void
    {
        // Tools routes that use ToolsController
        if (method_exists($this->tools_controller, 'resyncBrevo')) {
            register_rest_route(
                'fp-exp/v1',
                '/tools/resync-brevo',
                [
                    'methods' => 'POST',
                    'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                    'callback' => [$this->tools_controller, 'resyncBrevo'],
                ]
            );
        } else {
            // Fallback to legacy method
            register_rest_route(
                'fp-exp/v1',
                '/tools/resync-brevo',
                [
                    'methods' => 'POST',
                    'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                    'callback' => [$this->rest_routes, 'tool_resync_brevo'],
                ]
            );
        }

        // Other tools routes use legacy callbacks for now
        // TODO: Move these to ToolsController in future refactoring
        $legacy_tools = [
            'replay-events' => 'tool_replay_events',
            'resync-roles' => 'tool_resync_roles',
            'ping' => 'tool_ping',
            'clear-cache' => 'tool_clear_cache',
            'resync-pages' => 'tool_resync_pages',
            'fix-corrupted-arrays' => 'tool_fix_corrupted_arrays',
            'backup-branding' => 'tool_backup_branding',
            'restore-branding' => 'tool_restore_branding',
            'cleanup-duplicate-page-ids' => 'tool_cleanup_duplicate_page_ids',
            'cleanup-old-slots' => 'tool_cleanup_old_slots',
            'rebuild-availability-meta' => 'tool_rebuild_availability_meta',
            'recreate-virtual-product' => 'tool_recreate_virtual_product',
            'fix-virtual-product-quantity' => 'tool_fix_virtual_product_quantity',
            'fix-experience-prices' => 'tool_fix_experience_prices',
            'create-tables' => 'tool_create_tables',
        ];

        foreach ($legacy_tools as $route => $method) {
            register_rest_route(
                'fp-exp/v1',
                '/tools/' . $route,
                [
                    'methods' => 'POST',
                    'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                    'callback' => [$this->rest_routes, $method],
                ]
            );
        }
    }

    /**
     * Register diagnostic routes.
     */
    private function registerDiagnosticRoutes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/tools/repair-slot-capacities',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this->diagnostic_controller, 'repairSlotCapacities'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/diagnostic',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this->diagnostic_controller, 'diagnostic'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/diagnostic/checkout',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this->diagnostic_controller, 'checkout'],
            ]
        );
    }

    /**
     * Register gift routes.
     */
    private function registerGiftRoutes(): void
    {
        if ($this->gift_controller === null) {
            return;
        }

        // Public: purchase a gift voucher (frontend form)
        register_rest_route(
            'fp-exp/v1',
            '/gift/purchase',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'publicEndpoint'],
                'callback' => [$this->gift_controller, 'purchase'],
                'args' => [
                    'experience_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'quantity' => [
                        'required' => false,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // Public: get voucher info by code
        register_rest_route(
            'fp-exp/v1',
            '/gift/voucher/(?P<code>[A-Za-z0-9\-]+)',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'publicEndpoint'],
                'callback' => [$this->gift_controller, 'getVoucher'],
            ]
        );

        // Public: redeem a voucher (legacy URL used by frontend)
        register_rest_route(
            'fp-exp/v1',
            '/gift/redeem',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'publicEndpoint'],
                'callback' => [$this->gift_controller, 'redeem'],
                'args' => [
                    'code' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'slot_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // Admin: create voucher manually
        register_rest_route(
            'fp-exp/v1',
            '/gift/voucher',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this->gift_controller, 'createVoucher'],
            ]
        );

        // Public: redeem voucher (new URL pattern)
        register_rest_route(
            'fp-exp/v1',
            '/gift/voucher/(?P<code>[a-zA-Z0-9-]+)/redeem',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'publicEndpoint'],
                'callback' => [$this->gift_controller, 'redeemVoucher'],
            ]
        );
    }

    /**
     * Register settings routes.
     */
    private function registerSettingsRoutes(): void
    {
        if ($this->settings_controller === null) {
            return;
        }

        register_rest_route(
            'fp-exp/v1',
            '/settings',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this->settings_controller, 'getSettings'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/settings',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this->settings_controller, 'updateSettings'],
            ]
        );
    }

    /**
     * Register checkout routes (legacy for now).
     */
    private function registerCheckoutRoutes(): void
    {
        // Checkout routes still use legacy RestRoutes methods
        // TODO: Move to CheckoutController in future refactoring
        register_rest_route(
            'fp-exp/v1',
            '/checkout',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'publicEndpoint'],
                'callback' => [$this->rest_routes, 'process_checkout'],
            ]
        );
    }
}








