<?php

declare(strict_types=1);

namespace FP_Exp\Api;

use FP_Exp\Activation;
use FP_Exp\Api\Controllers\AvailabilityController;
use FP_Exp\Api\Controllers\CalendarController;
use FP_Exp\Api\Controllers\DiagnosticController;
use FP_Exp\Api\Controllers\GiftController;
use FP_Exp\Api\Controllers\ToolsController;
use FP_Exp\Api\Middleware\AuthenticationMiddleware;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Booking\AvailabilityService;
use FP_Exp\Booking\Recurrence;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Gift\VoucherTable;
use FP_Exp\Migrations\Migrations\CleanupDuplicatePageIds;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function __;
use function array_diff;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_values;
use function absint;
use function add_action;
use function apply_filters;
use function current_time;
use function date_i18n;
use function delete_post_meta;
use function do_action;
use function get_current_user_id;
use function get_post_meta;
use function get_posts;
use function get_role;
use function get_the_title;
use function home_url;
use function implode;
use function is_array;
use function is_wp_error;
use function rest_ensure_response;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function sort;
use function strtoupper;
use function update_option;
use function update_post_meta;
use function wp_delete_post;
use function wp_insert_post;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_strip_all_tags;

use const MINUTE_IN_SECONDS;

final class RestRoutes implements HookableInterface
{
    private ?VoucherManager $voucher_manager;

    // Refactored controllers (can be injected via constructor or lazy-loaded)
    private ?GiftController $gift_controller = null;
    private ?AvailabilityController $availability_controller = null;
    private ?CalendarController $calendar_controller = null;
    private ?ToolsController $tools_controller = null;
    private ?DiagnosticController $diagnostic_controller = null;
    private ?SettingsController $settings_controller = null;
    private ?RouteRegistry $route_registry = null;

    /**
     * RestRoutes constructor.
     *
     * @param VoucherManager|null $voucher_manager Optional VoucherManager
     * @param AvailabilityController|null $availability_controller Optional (will be lazy-loaded if not provided)
     * @param CalendarController|null $calendar_controller Optional (will be lazy-loaded if not provided)
     * @param ToolsController|null $tools_controller Optional (will be lazy-loaded if not provided)
     * @param DiagnosticController|null $diagnostic_controller Optional (will be lazy-loaded if not provided)
     * @param GiftController|null $gift_controller Optional (will be lazy-loaded if not provided)
     * @param SettingsController|null $settings_controller Optional (will be lazy-loaded if not provided)
     * @param RouteRegistry|null $route_registry Optional (will be created if not provided)
     */
    public function __construct(
        ?VoucherManager $voucher_manager = null,
        ?AvailabilityController $availability_controller = null,
        ?CalendarController $calendar_controller = null,
        ?ToolsController $tools_controller = null,
        ?DiagnosticController $diagnostic_controller = null,
        ?GiftController $gift_controller = null,
        ?SettingsController $settings_controller = null,
        ?RouteRegistry $route_registry = null
    ) {
        $this->voucher_manager = $voucher_manager;
        $this->availability_controller = $availability_controller;
        $this->calendar_controller = $calendar_controller;
        $this->tools_controller = $tools_controller;
        $this->diagnostic_controller = $diagnostic_controller;
        $this->gift_controller = $gift_controller;
        $this->settings_controller = $settings_controller;
        $this->route_registry = $route_registry;
    }

    /**
     * Initialize controllers from container.
     * 
     * Uses dependency injection container exclusively (no fallback).
     * Controllers must be registered in RESTServiceProvider.
     * 
     * @throws \RuntimeException If container or controllers are not available
     */
    private function initControllers(): void
    {
        if ($this->gift_controller !== null) {
            return; // Already initialized
        }

        // Get controllers from container (new architecture)
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        
        if ($kernel === null) {
            throw new \RuntimeException('FP Experiences kernel not initialized. Cannot resolve REST controllers.');
        }
        
        $container = $kernel->container();
        
        try {
            // Get all controllers from container
            // These must be registered in RESTServiceProvider
            if ($container->has(AvailabilityController::class)) {
                $this->availability_controller = $container->make(AvailabilityController::class);
            } else {
                throw new \RuntimeException('AvailabilityController not registered in container.');
            }
            
            if ($container->has(CalendarController::class)) {
                $this->calendar_controller = $container->make(CalendarController::class);
            } else {
                throw new \RuntimeException('CalendarController not registered in container.');
            }
            
            if ($container->has(ToolsController::class)) {
                $this->tools_controller = $container->make(ToolsController::class);
            } else {
                throw new \RuntimeException('ToolsController not registered in container.');
            }
            
            if ($container->has(DiagnosticController::class)) {
                $this->diagnostic_controller = $container->make(DiagnosticController::class);
            } else {
                throw new \RuntimeException('DiagnosticController not registered in container.');
            }
            
            // GiftController is optional (only if VoucherManager is available)
            if ($container->has(GiftController::class)) {
                try {
                    $this->gift_controller = $container->make(GiftController::class);
                } catch (\Throwable $e) {
                    // GiftController initialization failed, but it's optional
                    // This can happen if VoucherManager is not available
                }
            }
            
            // SettingsController is optional
            if ($container->has(SettingsController::class)) {
                try {
                    $this->settings_controller = $container->make(SettingsController::class);
                } catch (\Throwable $e) {
                    // SettingsController initialization failed, but it's optional
                }
            }
            
            // Create RouteRegistry with all controllers (if not already created)
            if ($this->route_registry === null) {
                $this->route_registry = new RouteRegistry(
                    $this->availability_controller,
                    $this->calendar_controller,
                    $this->tools_controller,
                    $this->diagnostic_controller,
                    $this,
                    $this->gift_controller,
                    $this->settings_controller
                );
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to initialize REST controllers: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_post_dispatch', [$this, 'enforce_no_cache'], 10, 3);
    }

    public function register_routes(): void
    {
        $this->initControllers();
        
        // Use RouteRegistry to register all routes
        if ($this->route_registry !== null) {
            $this->route_registry->registerAll();
            return;
        }
        
        // Fallback: register routes directly (should not happen if container is working)
        // This is kept for backward compatibility but should be removed in future versions
        $this->registerRoutesLegacy();
    }
    
    /**
     * Legacy route registration (fallback).
     * 
     * @deprecated 1.2.0 Use RouteRegistry instead. This method will be removed in version 2.0.0.
     */
    private function registerRoutesLegacy(): void
    {
        // Controllers are now always available from container (no fallback)
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

        // Controllers are now always available from container (no fallback)
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

        register_rest_route(
            'fp-exp/v1',
            '/calendar/slot/(?P<id>\d+)/move',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this, 'move_calendar_slot'],
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
                'callback' => [$this, 'update_slot_capacity'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/recurrence/preview',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this, 'preview_recurrence_slots'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/recurrence/generate',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'operatorPermission'],
                'callback' => [$this, 'generate_recurrence_slots'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-brevo',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_resync_brevo'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/replay-events',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_replay_events'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-roles',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_resync_roles'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/ping',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_ping'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/clear-cache',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_clear_cache'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-pages',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_resync_pages'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/fix-corrupted-arrays',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_fix_corrupted_arrays'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/backup-branding',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_backup_branding'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/restore-branding',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_restore_branding'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/cleanup-duplicate-page-ids',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_cleanup_duplicate_page_ids'],
            ]
        );
        
        register_rest_route(
            'fp-exp/v1',
            '/tools/repair-slot-capacities',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_repair_slot_capacities'],
            ]
        );
        
        register_rest_route(
            'fp-exp/v1',
            '/tools/cleanup-old-slots',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_cleanup_old_slots'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/rebuild-availability-meta',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_rebuild_availability_meta'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/recreate-virtual-product',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_recreate_virtual_product'],
            ]
        );
        
        register_rest_route(
            'fp-exp/v1',
            '/tools/fix-virtual-product-quantity',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_fix_virtual_product_quantity'],
            ]
        );
        
        register_rest_route(
            'fp-exp/v1',
            '/tools/fix-experience-prices',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_fix_experience_prices'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/gift/purchase',
            [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return Helpers::verify_public_rest_request($request);
                },
                'callback' => [$this, 'purchase_gift_voucher'],
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

        register_rest_route(
            'fp-exp/v1',
            '/gift/voucher/(?P<code>[A-Za-z0-9\-]+)',
            [
                'methods' => 'GET',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return Helpers::verify_public_rest_request($request);
                },
                'callback' => [$this, 'get_gift_voucher'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/gift/redeem',
            [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return Helpers::verify_public_rest_request($request);
                },
                'callback' => [$this, 'redeem_gift_voucher'],
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

        register_rest_route(
            'fp-exp/v1',
            '/diagnostic/checkout',
            [
                'methods' => 'GET',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'diagnostic_checkout'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/create-tables',
            [
                'methods' => 'POST',
                'permission_callback' => [AuthenticationMiddleware::class, 'managerPermission'],
                'callback' => [$this, 'tool_create_tables'],
            ]
        );
    }

    public function enforce_no_cache($result, $server, $request)
    {
        if (! $result instanceof WP_REST_Response) {
            return $result;
        }

        if (! $request instanceof WP_REST_Request) {
            return $result;
        }

        $route = $request->get_route();

        if (strpos($route, '/fp-exp/') !== 0) {
            return $result;
        }

        $method = strtoupper($request->get_method());
        if ('GET' === $method) {
            return $result;
        }

        $result->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $result->header('Pragma', 'no-cache');
        $result->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $result;
    }

    /**
     * Purchase gift voucher (delegated to GiftController).
     *
     * @deprecated Use GiftController::purchase() instead
     */
    public function purchase_gift_voucher(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->gift_controller->purchase($request);
    }

    /**
     * Get gift voucher (delegated to GiftController).
     *
     * @deprecated Use GiftController::getVoucher() instead
     */
    public function get_gift_voucher(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->gift_controller->getVoucher($request);
    }

    /**
     * Redeem gift voucher (delegated to GiftController).
     *
     * @deprecated Use GiftController::redeem() instead
     */
    public function redeem_gift_voucher(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->gift_controller->redeem($request);
    }

    /**
     * Get virtual availability (delegated to AvailabilityController).
     *
     * @deprecated Use AvailabilityController::getVirtualAvailability() instead
     */
    public function get_virtual_availability(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->availability_controller->getVirtualAvailability($request);
    }

    /**
     * Get calendar slots (delegated to CalendarController).
     *
     * @deprecated Use CalendarController::getSlots() instead
     */
    public function get_calendar_slots(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->calendar_controller->getSlots($request);
    }

    /**
     * Move calendar slot (delegated to CalendarController).
     *
     * @deprecated Use CalendarController::moveSlot() instead
     */
    public function move_calendar_slot(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->calendar_controller->moveSlot($request);
    }

    /**
     * Update slot capacity (delegated to CalendarController).
     *
     * @deprecated Use CalendarController::updateCapacity() instead
     */
    public function update_slot_capacity(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->calendar_controller->updateCapacity($request);
    }

    /**
     * Preview recurrence slots (delegated to CalendarController).
     *
     * @deprecated Use CalendarController::previewRecurrence() instead
     */
    public function preview_recurrence_slots(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->calendar_controller->previewRecurrence($request);
    }

    /**
     * Generate recurrence slots (delegated to CalendarController).
     *
     * @deprecated Use CalendarController::generateRecurrence() instead
     */
    public function generate_recurrence_slots(WP_REST_Request $request)
    {
        $this->initControllers();

        return $this->calendar_controller->generateRecurrence($request);
    }

    /**
     * Resync Brevo (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::resyncBrevo() instead
     */
    public function tool_resync_brevo(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->resyncBrevo();
    }

    /**
     * Replay events (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::replayEvents() instead
     */
    public function tool_replay_events(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->replayEvents();
    }

    /**
     * Resync roles (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::resyncRoles() instead
     */
    public function tool_resync_roles(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->resyncRoles();
    }

    /**
     * Ping tool (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::ping() instead
     */
    public function tool_ping(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->ping();
    }

    /**
     * Clear cache (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::clearCache() instead
     */
    public function tool_clear_cache(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->clearCache();
    }

    /**
     * Resync pages (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::resyncPages() instead
     */
    public function tool_resync_pages(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->resyncPages();
    }

    /**
     * Fix corrupted arrays (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::fixCorruptedArrays() instead
     */
    public function tool_fix_corrupted_arrays(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->fixCorruptedArrays();
    }

    /**
     * Backup branding (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::backupBranding() instead
     */
    public function tool_backup_branding(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->backupBranding();
    }

    /**
     * Restore branding (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::restoreBranding() instead
     */
    public function tool_restore_branding(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->restoreBranding();
    }

    /**
     * @param array<int, string> $role_names
     *
     * @return array<string, array{exists: bool, caps: array<int, string>}>
     */
    private function snapshot_role_capabilities(array $role_names): array
    {
        $snapshot = [];

        foreach ($role_names as $role_name) {
            $role = get_role($role_name);

            if (! $role) {
                $snapshot[$role_name] = [
                    'exists' => false,
                    'caps' => [],
                ];

                continue;
            }

            $capabilities = is_array($role->capabilities) ? $role->capabilities : [];
            $caps = array_keys(array_filter($capabilities));
            sort($caps);

            $snapshot[$role_name] = [
                'exists' => true,
                'caps' => $caps,
            ];
        }

        return $snapshot;
    }

    /**
     * @param array{exists: bool, caps: array<int, string>} $before
     * @param array{exists: bool, caps: array<int, string>} $after
     * @param array<int, string> $expected
     *
     * @return array{0: bool, 1: array<int, string>}
     */
    private function summarise_role_capabilities(
        string $role_name,
        string $label,
        array $expected,
        array $before,
        array $after
    ): array {
        $details = [];

        if (! ($after['exists'] ?? false)) {
            $details[] = sprintf(
                /* translators: %s: user-friendly role name. */
                __('Role %s is not registered.', 'fp-experiences'),
                $label
            );

            return [false, $details];
        }

        if (! ($before['exists'] ?? false)) {
            $details[] = sprintf(
                /* translators: %s: user-friendly role name. */
                __('Role %s has been created.', 'fp-experiences'),
                $label
            );
        }

        $before_caps = isset($before['caps']) && is_array($before['caps']) ? $before['caps'] : [];
        $after_caps = isset($after['caps']) && is_array($after['caps']) ? $after['caps'] : [];

        $missing = array_values(array_diff($expected, $after_caps));
        $added = array_values(array_diff($after_caps, $before_caps));

        if ($missing) {
            $details[] = sprintf(
                /* translators: 1: user-friendly role name, 2: comma-separated capability list. */
                __('Role %1$s is missing capabilities: %2$s', 'fp-experiences'),
                $label,
                implode(', ', $missing)
            );
        }

        if ($added) {
            $details[] = sprintf(
                /* translators: 1: user-friendly role name, 2: comma-separated capability list. */
                __('Role %1$s gained capabilities: %2$s', 'fp-experiences'),
                $label,
                implode(', ', $added)
            );
        }

        if (! $missing && ! $added && ($before['exists'] ?? false)) {
            $details[] = sprintf(
                /* translators: %s: user-friendly role name. */
                __('Role %s was already up to date.', 'fp-experiences'),
                $label
            );
        }

        return [! $missing, $details];
    }

    /**
     * Rebuild availability meta (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::rebuildAvailabilityMeta() instead
     */
    public function tool_rebuild_availability_meta(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->rebuildAvailabilityMeta();
    }

    /**
     * Cleanup duplicate page IDs (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::cleanupDuplicatePageIds() instead
     */
    public function tool_cleanup_duplicate_page_ids(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->cleanupDuplicatePageIds();
    }
    
    /**
     * Repair slot capacities (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::repairSlotCapacities() instead
     */
    public function tool_repair_slot_capacities(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->repairSlotCapacities();
    }
    
    /**
     * Cleanup old slots (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::cleanupOldSlots() instead
     */
    public function tool_cleanup_old_slots(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->cleanupOldSlots();
    }
    
    /**
     * Diagnostic checkout (delegated to DiagnosticController).
     *
     * @deprecated Use DiagnosticController::checkout() instead
     */
    public function diagnostic_checkout(): WP_REST_Response
    {
        $this->initControllers();

        return $this->diagnostic_controller->checkout(new \WP_REST_Request());
    }
    
    /**
     * Create tables (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::createTables() instead
     */
    public function tool_create_tables(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->createTables();
    }

    /**
     * Tool: Recreate WooCommerce virtual product for experiences
     */
    /**
     * Recreate virtual product (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::recreateVirtualProduct() instead
     */
    public function tool_recreate_virtual_product(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->recreateVirtualProduct();
    }
    
    /**
     * Tool: Fix virtual product quantity settings (disable sold_individually)
     */
    /**
     * Fix virtual product quantity (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::fixVirtualProductQuantity() instead
     */
    public function tool_fix_virtual_product_quantity(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->fixVirtualProductQuantity();
    }
    
    /**
     * Tool: Fix experience prices (_fp_price meta)
     */
    /**
     * Fix experience prices (delegated to ToolsController).
     *
     * @deprecated Use ToolsController::fixExperiencePrices() instead
     */
    public function tool_fix_experience_prices(): WP_REST_Response
    {
        $this->initControllers();

        return $this->tools_controller->fixExperiencePrices();
    }

    public function tool_migrate_reservations(): WP_REST_Response
    {
        Reservations::create_table();

        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['completed', 'processing', 'on-hold', 'pending'],
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $created = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (! $order instanceof \WC_Order) {
                continue;
            }

            $order_id = $order->get_id();
            $existing = Reservations::get_ids_by_order($order_id);
            if (! empty($existing)) {
                $skipped++;
                continue;
            }

            foreach ($order->get_items() as $item) {
                $item_type = $item->get_meta('_fp_exp_item_type');
                if ('experience' !== $item_type) {
                    continue;
                }

                $experience_id = absint($item->get_meta('experience_id'));
                $slot_id = absint($item->get_meta('slot_id'));
                $tickets = $item->get_meta('tickets');
                $addons = $item->get_meta('addons');
                $tickets = is_array($tickets) ? $tickets : [];
                $addons = is_array($addons) ? $addons : [];

                $order_status = $order->get_status();
                $status = in_array($order_status, ['completed', 'processing'], true)
                    ? Reservations::STATUS_PAID
                    : Reservations::STATUS_PENDING;

                $reservation_id = Reservations::create([
                    'order_id' => $order_id,
                    'experience_id' => $experience_id,
                    'slot_id' => $slot_id,
                    'status' => $status,
                    'pax' => $tickets,
                    'addons' => $addons,
                    'total_gross' => (float) $item->get_total(),
                    'tax_total' => (float) $item->get_total_tax(),
                ]);

                if ($reservation_id > 0) {
                    $created++;
                }
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf('Migrazione completata: %d prenotazioni create, %d ordini già migrati.', $created, $skipped),
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}
