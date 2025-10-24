<?php

declare(strict_types=1);

namespace FP_Exp\Api;

use FP_Exp\Activation;
use FP_Exp\Booking\AvailabilityService;
use FP_Exp\Booking\Recurrence;
use FP_Exp\Booking\Slots;
use FP_Exp\Gift\VoucherManager;
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
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_strip_all_tags;

use const MINUTE_IN_SECONDS;

final class RestRoutes
{
    private ?VoucherManager $voucher_manager;

    public function __construct(?VoucherManager $voucher_manager = null)
    {
        $this->voucher_manager = $voucher_manager;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_post_dispatch', [$this, 'enforce_no_cache'], 10, 3);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/availability',
            [
                'methods' => 'GET',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    // Public, ma con verifica leggera standard plugin (anti-abuso)
                    return Helpers::verify_public_rest_request($request);
                },
                'callback' => [$this, 'get_virtual_availability'],
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

        register_rest_route(
            'fp-exp/v1',
            '/calendar/slots',
            [
                'methods' => 'GET',
                'permission_callback' => static function (): bool {
                    return Helpers::can_operate_fp();
                },
                'callback' => [$this, 'get_calendar_slots'],
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
                'permission_callback' => static function (): bool {
                    return Helpers::can_operate_fp();
                },
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
                'permission_callback' => static function (): bool {
                    return Helpers::can_operate_fp();
                },
                'callback' => [$this, 'update_slot_capacity'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/recurrence/preview',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_operate_fp();
                },
                'callback' => [$this, 'preview_recurrence_slots'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/calendar/recurrence/generate',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_operate_fp();
                },
                'callback' => [$this, 'generate_recurrence_slots'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-brevo',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_resync_brevo'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/replay-events',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_replay_events'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-roles',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_resync_roles'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/ping',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_ping'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/clear-cache',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_clear_cache'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-pages',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_resync_pages'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/fix-corrupted-arrays',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_fix_corrupted_arrays'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/backup-branding',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_backup_branding'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/restore-branding',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'tool_restore_branding'],
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

    public function purchase_gift_voucher(WP_REST_Request $request)
    {
        if (! Helpers::gift_enabled()) {
            return new WP_Error('fp_exp_gift_disabled', esc_html__('Gift vouchers are disabled.', 'fp-experiences'), ['status' => 400]);
        }

        if (! $this->voucher_manager instanceof VoucherManager) {
            return new WP_Error('fp_exp_gift_unavailable', esc_html__('Gift manager unavailable.', 'fp-experiences'), ['status' => 500]);
        }

        $payload = [
            'experience_id' => $request->get_param('experience_id'),
            'quantity' => $request->get_param('quantity'),
            'addons' => $request->get_param('addons'),
            'purchaser' => $request->get_param('purchaser'),
            'recipient' => $request->get_param('recipient'),
            'message' => $request->get_param('message'),
            'delivery' => $request->get_param('delivery'),
        ];

        $result = $this->voucher_manager->create_purchase($payload);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);

            return $result;
        }

        return rest_ensure_response($result);
    }

    public function get_gift_voucher(WP_REST_Request $request)
    {
        if (! $this->voucher_manager instanceof VoucherManager) {
            return new WP_Error('fp_exp_gift_unavailable', esc_html__('Gift manager unavailable.', 'fp-experiences'), ['status' => 500]);
        }

        $code = sanitize_text_field((string) $request->get_param('code'));
        $result = $this->voucher_manager->get_voucher_by_code($code);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 404]);

            return $result;
        }

        return rest_ensure_response($result);
    }

    public function redeem_gift_voucher(WP_REST_Request $request)
    {
        if (! $this->voucher_manager instanceof VoucherManager) {
            return new WP_Error('fp_exp_gift_unavailable', esc_html__('Gift manager unavailable.', 'fp-experiences'), ['status' => 500]);
        }

        $code = sanitize_text_field((string) $request->get_param('code'));
        $payload = [
            'slot_id' => $request->get_param('slot_id'),
        ];

        $result = $this->voucher_manager->redeem_voucher($code, $payload);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);

            return $result;
        }

        return rest_ensure_response($result);
    }

    public function get_virtual_availability(WP_REST_Request $request)
    {
        $experience_id = absint((string) $request->get_param('experience'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));

        if ($experience_id <= 0 || ! $start || ! $end) {
            return new WP_Error('fp_exp_availability_params', __('Parametri non validi.', 'fp-experiences'), ['status' => 400]);
        }

        // Validazione formato date
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return new WP_Error(
                'fp_exp_invalid_date_format',
                __('Formato data non valido. Usa YYYY-MM-DD.', 'fp-experiences'),
                ['status' => 400]
            );
        }

        // Validazione range temporale
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        
        if (false === $start_ts || false === $end_ts) {
            return new WP_Error(
                'fp_exp_invalid_date',
                __('Le date fornite non sono valide.', 'fp-experiences'),
                ['status' => 400]
            );
        }
        
        if ($end_ts < $start_ts) {
            return new WP_Error(
                'fp_exp_invalid_range',
                __('La data di fine deve essere successiva alla data di inizio.', 'fp-experiences'),
                ['status' => 400]
            );
        }

        // Limita il range a max 1 anno per evitare query pesanti
        $one_year = 365 * 24 * 60 * 60;
        if (($end_ts - $start_ts) > $one_year) {
            return new WP_Error(
                'fp_exp_range_too_large',
                __('Il range di date non può superare 1 anno.', 'fp-experiences'),
                ['status' => 400]
            );
        }

        $slots = AvailabilityService::get_virtual_slots($experience_id, $start, $end);

        return rest_ensure_response([
            'slots' => array_map(
                static function (array $slot): array {
                    return [
                        'experience_id' => (int) ($slot['experience_id'] ?? 0),
                        'start' => sanitize_text_field((string) ($slot['start'] ?? '')),
                        'end' => sanitize_text_field((string) ($slot['end'] ?? '')),
                        'capacity_total' => (int) ($slot['capacity_total'] ?? 0),
                        'capacity_remaining' => (int) ($slot['capacity_remaining'] ?? 0),
                        'duration' => (int) ($slot['duration'] ?? 0),
                    ];
                },
                $slots
            ),
        ]);
    }

    public function get_calendar_slots(WP_REST_Request $request)
    {
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));
        $experience = absint((string) $request->get_param('experience'));

        if (! $start || ! $end) {
            return new WP_Error('fp_exp_calendar_range', __('Provide a valid date range.', 'fp-experiences'), ['status' => 400]);
        }

        $slots = Slots::get_slots_in_range($start, $end, [
            'experience_id' => $experience,
        ]);

        $payload = array_map(
            static function (array $slot): array {
                $per_type = [];
                if (isset($slot['capacity_per_type']) && is_array($slot['capacity_per_type'])) {
                    foreach ($slot['capacity_per_type'] as $type => $amount) {
                        $per_type[sanitize_key((string) $type)] = (int) $amount;
                    }
                }

                return [
                    'id' => (int) ($slot['id'] ?? 0),
                    'experience_id' => (int) ($slot['experience_id'] ?? 0),
                    'experience_title' => sanitize_text_field((string) ($slot['experience_title'] ?? '')),
                    'start' => sanitize_text_field((string) ($slot['start_datetime'] ?? '')),
                    'end' => sanitize_text_field((string) ($slot['end_datetime'] ?? '')),
                    'capacity_total' => (int) ($slot['capacity_total'] ?? 0),
                    'capacity_per_type' => $per_type,
                    'remaining' => (int) ($slot['remaining'] ?? 0),
                    'reserved' => (int) ($slot['reserved_total'] ?? 0),
                    'duration' => sanitize_text_field((string) ($slot['duration'] ?? '')),
                ];
            },
            $slots
        );

        return rest_ensure_response([
            'slots' => $payload,
        ]);
    }

    public function move_calendar_slot(WP_REST_Request $request)
    {
        $slot_id = absint((string) $request->get_param('id'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));

        if ($slot_id <= 0 || ! $start || ! $end) {
            return new WP_Error('fp_exp_calendar_move', __('Missing slot data.', 'fp-experiences'), ['status' => 400]);
        }

        if (Helpers::hit_rate_limit('calendar_move_' . get_current_user_id(), 10, MINUTE_IN_SECONDS)) {
            return new WP_Error('fp_exp_rate_limited', __('Troppe modifiche al calendario in poco tempo. Riprova tra qualche istante.', 'fp-experiences'), ['status' => 429]);
        }

        $moved = Slots::move_slot($slot_id, $start, $end);

        if (! $moved) {
            return new WP_Error('fp_exp_calendar_move_failed', __('Unable to move the slot to the requested time.', 'fp-experiences'), ['status' => 409]);
        }

        Logger::log('calendar', 'Slot moved', [
            'slot_id' => $slot_id,
            'start' => $start,
            'end' => $end,
        ]);

        return rest_ensure_response(['success' => true]);
    }

    public function update_slot_capacity(WP_REST_Request $request)
    {
        $slot_id = absint((string) $request->get_param('id'));
        $total = absint((string) $request->get_param('capacity_total'));
        $per_type = $request->get_param('capacity_per_type');

        if ($slot_id <= 0) {
            return new WP_Error('fp_exp_calendar_capacity', __('Invalid slot identifier.', 'fp-experiences'), ['status' => 400]);
        }

        if (! is_array($per_type)) {
            $per_type = [];
        }

        if (Helpers::hit_rate_limit('calendar_capacity_' . get_current_user_id(), 10, MINUTE_IN_SECONDS)) {
            return new WP_Error('fp_exp_rate_limited', __('Attendi prima di modificare nuovamente la capacità.', 'fp-experiences'), ['status' => 429]);
        }

        $updated = Slots::update_capacity($slot_id, $total, $per_type);

        if (! $updated) {
            return new WP_Error('fp_exp_calendar_capacity_failed', __('Unable to update capacity. Check reservations before lowering limits.', 'fp-experiences'), ['status' => 409]);
        }

        Logger::log('calendar', 'Slot capacity updated', [
            'slot_id' => $slot_id,
            'capacity_total' => $total,
            'capacity_per_type' => $per_type,
        ]);

        return rest_ensure_response(['success' => true]);
    }

    public function preview_recurrence_slots(WP_REST_Request $request)
    {
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return new WP_Error('fp_exp_recurrence_payload', __('Invalid recurrence payload.', 'fp-experiences'), ['status' => 400]);
        }

        $experience_id = isset($body['experience_id']) ? absint((string) $body['experience_id']) : 0;
        $recurrence_raw = isset($body['recurrence']) && is_array($body['recurrence']) ? $body['recurrence'] : [];
        $availability = isset($body['availability']) && is_array($body['availability']) ? $body['availability'] : [];

        $recurrence = Recurrence::sanitize($recurrence_raw);

        if (! Recurrence::is_actionable($recurrence)) {
            return rest_ensure_response([
                'preview' => [],
            ]);
        }

        $rules = Recurrence::build_rules($recurrence, [
            'slot_capacity' => absint((string) ($availability['slot_capacity'] ?? 0)),
            'buffer_before_minutes' => absint((string) ($availability['buffer_before_minutes'] ?? 0)),
            'buffer_after_minutes' => absint((string) ($availability['buffer_after_minutes'] ?? 0)),
        ]);

        if (empty($rules)) {
            return rest_ensure_response([
                'preview' => [],
            ]);
        }

        $default_capacity = absint((string) ($availability['slot_capacity'] ?? 0));
        $default_buffer_before = absint((string) ($availability['buffer_before_minutes'] ?? 0));
        $default_buffer_after = absint((string) ($availability['buffer_after_minutes'] ?? 0));

        foreach ($rules as $rule) {
            if (0 === $default_capacity && isset($rule['capacity_total'])) {
                $default_capacity = absint((string) $rule['capacity_total']);
            }
            if (0 === $default_buffer_before && isset($rule['buffer_before'])) {
                $default_buffer_before = absint((string) $rule['buffer_before']);
            }
            if (0 === $default_buffer_after && isset($rule['buffer_after'])) {
                $default_buffer_after = absint((string) $rule['buffer_after']);
            }
        }

        $options = [
            'default_duration' => absint((string) ($recurrence['duration'] ?? 60)),
            'default_capacity' => $default_capacity,
            'buffer_before' => $default_buffer_before,
            'buffer_after' => $default_buffer_after,
        ];

        $preview = Slots::preview_recurring_slots($experience_id, $rules, [], $options, 12);

        return rest_ensure_response([
            'preview' => $preview,
        ]);
    }

    public function generate_recurrence_slots(WP_REST_Request $request)
    {
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return new WP_Error('fp_exp_recurrence_payload', __('Invalid recurrence payload.', 'fp-experiences'), ['status' => 400]);
        }

        $experience_id = isset($body['experience_id']) ? absint((string) $body['experience_id']) : 0;
        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_recurrence_experience', __('Select a valid experience before generating slots.', 'fp-experiences'), ['status' => 400]);
        }

        $recurrence_raw = isset($body['recurrence']) && is_array($body['recurrence']) ? $body['recurrence'] : [];
        $availability = isset($body['availability']) && is_array($body['availability']) ? $body['availability'] : [];

        $recurrence = Recurrence::sanitize($recurrence_raw);

        if (! Recurrence::is_actionable($recurrence)) {
            return new WP_Error('fp_exp_recurrence_invalid', __('Configure at least one valid time slot for the recurrence.', 'fp-experiences'), ['status' => 422]);
        }

        $rules = Recurrence::build_rules($recurrence, [
            'slot_capacity' => absint((string) ($availability['slot_capacity'] ?? 0)),
            'buffer_before_minutes' => absint((string) ($availability['buffer_before_minutes'] ?? 0)),
            'buffer_after_minutes' => absint((string) ($availability['buffer_after_minutes'] ?? 0)),
        ]);

        if (empty($rules)) {
            return new WP_Error('fp_exp_recurrence_rules', __('Unable to build recurrence rules from the provided data.', 'fp-experiences'), ['status' => 422]);
        }

        $default_capacity = absint((string) ($availability['slot_capacity'] ?? 0));
        $default_buffer_before = absint((string) ($availability['buffer_before_minutes'] ?? 0));
        $default_buffer_after = absint((string) ($availability['buffer_after_minutes'] ?? 0));

        foreach ($rules as $rule) {
            if (0 === $default_capacity && isset($rule['capacity_total'])) {
                $default_capacity = absint((string) $rule['capacity_total']);
            }
            if (0 === $default_buffer_before && isset($rule['buffer_before'])) {
                $default_buffer_before = absint((string) $rule['buffer_before']);
            }
            if (0 === $default_buffer_after && isset($rule['buffer_after'])) {
                $default_buffer_after = absint((string) $rule['buffer_after']);
            }
        }

        $options = [
            'default_duration' => absint((string) ($recurrence['duration'] ?? 60)),
            'default_capacity' => $default_capacity,
            'buffer_before' => $default_buffer_before,
            'buffer_after' => $default_buffer_after,
            'replace_existing' => ! empty($body['replace_existing']),
        ];

        $created = Slots::generate_recurring_slots($experience_id, $rules, [], $options);
        $preview = Slots::preview_recurring_slots($experience_id, $rules, [], $options, 12);

        Logger::log('calendar', 'Recurrence slots generated', [
            'experience_id' => $experience_id,
            'created' => $created,
        ]);

        return rest_ensure_response([
            'created' => $created,
            'preview' => $preview,
        ]);
    }

    public function tool_resync_brevo(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_resync_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Attendi prima di eseguire di nuovo la sincronizzazione Brevo.', 'fp-experiences'),
            ]);
        }

        do_action('fp_exp_tools_resync_brevo');
        Logger::log('tools', 'Triggered Brevo resynchronisation request', []);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Brevo resynchronisation queued.', 'fp-experiences'),
        ]);
    }

    public function tool_replay_events(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_replay_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Il replay degli eventi è stato eseguito da poco. Riprova più tardi.', 'fp-experiences'),
            ]);
        }

        do_action('fp_exp_tools_replay_events');
        Logger::log('tools', 'Triggered lifecycle event replay', []);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Event replay initiated.', 'fp-experiences'),
        ]);
    }

    public function tool_resync_roles(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_roles_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Role synchronisation already executed. Try again in a moment.', 'fp-experiences'),
            ]);
        }

        $roles_blueprint = Activation::roles_blueprint();
        $snapshot_roles = array_keys($roles_blueprint);
        $snapshot_roles[] = 'administrator';

        $before = $this->snapshot_role_capabilities($snapshot_roles);

        try {
            Activation::register_roles();
            $version = Activation::roles_version();
            update_option('fp_exp_roles_version', $version);

            Logger::log('tools', 'Role capabilities resynchronised', [
                'version' => $version,
            ]);

            $after = $this->snapshot_role_capabilities($snapshot_roles);

            $details = [
                sprintf(
                    /* translators: %s: roles version hash. */
                    __('Roles version updated to %s.', 'fp-experiences'),
                    $version
                ),
            ];

            $overall_success = true;

            foreach ($roles_blueprint as $role_name => $definition) {
                $expected = array_keys(array_filter($definition['capabilities']));
                [$role_success, $role_details] = $this->summarise_role_capabilities(
                    $role_name,
                    $definition['label'],
                    $expected,
                    $before[$role_name] ?? ['exists' => false, 'caps' => []],
                    $after[$role_name] ?? ['exists' => false, 'caps' => []]
                );

                $overall_success = $overall_success && $role_success;
                $details = array_merge($details, $role_details);
            }

            $manager_caps = array_keys(array_filter(Activation::manager_capabilities()));
            [$admin_success, $admin_details] = $this->summarise_role_capabilities(
                'administrator',
                __('Administrator', 'fp-experiences'),
                $manager_caps,
                $before['administrator'] ?? ['exists' => false, 'caps' => []],
                $after['administrator'] ?? ['exists' => false, 'caps' => []]
            );

            $overall_success = $overall_success && $admin_success;
            $details = array_merge($details, $admin_details);

            $message = $overall_success
                ? __('FP Experiences roles resynchronised.', 'fp-experiences')
                : __('Role synchronisation completed with warnings.', 'fp-experiences');

            return rest_ensure_response([
                'success' => $overall_success,
                'message' => $message,
                'details' => $details,
            ]);
        } catch (Throwable $error) {
            Logger::log('tools', 'Role capability resync failed', [
                'error' => $error->getMessage(),
            ]);

            return rest_ensure_response([
                'success' => false,
                'message' => __('Unable to resynchronise roles. Check logs for more details.', 'fp-experiences'),
            ]);
        }
    }

    public function tool_ping(): WP_REST_Response
    {
        $response = wp_remote_get(home_url('/wp-json'));
        if (is_wp_error($response)) {
            Logger::log('tools', 'Ping failed', [
                'error' => $response->get_error_message(),
            ]);

            return rest_ensure_response([
                'success' => false,
                'status' => 0,
                'body' => sanitize_text_field($response->get_error_message()),
            ]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $body = wp_strip_all_tags((string) $body);
        if (function_exists('mb_substr')) {
            $body = mb_substr($body, 0, 500);
        } else {
            $body = substr($body, 0, 500);
        }

        Logger::log('tools', 'Ping executed', [
            'status' => $code,
        ]);

        return rest_ensure_response([
            'success' => $code >= 200 && $code < 300,
            'status' => $code,
            'body' => $body,
        ]);
    }

    public function tool_clear_cache(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_clear_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Cache already cleared recently. Try again in a moment.', 'fp-experiences'),
            ]);
        }

        global $wpdb;
        
        // Pulisci tutti i transient del plugin
        $transients_deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_fp_exp_%' 
            OR option_name LIKE '_transient_timeout_fp_exp_%'"
        );
        
        // Pulisci object cache se disponibile
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Pulisci cache degli asset versioning
        Helpers::clear_asset_version_cache();
        
        // Hook per permettere ad altri plugin/moduli di pulire la loro cache
        do_action('fp_exp_tools_clear_cache');
        
        // Pulisci i log
        Logger::clear();

        $message = sprintf(
            /* translators: %d: number of transients deleted */
            __('Plugin caches cleared (%d transients removed) and logs trimmed.', 'fp-experiences'),
            (int) $transients_deleted
        );

        return rest_ensure_response([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function tool_resync_pages(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_pages_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Experience page resync already executed. Try again in a moment.', 'fp-experiences'),
            ]);
        }

        $summary = apply_filters('fp_exp_tools_resync_pages', [
            'checked' => 0,
            'created' => 0,
        ]);

        if (! is_array($summary)) {
            $summary = [
                'checked' => 0,
                'created' => 0,
            ];
        }

        $checked = isset($summary['checked']) ? (int) $summary['checked'] : 0;
        $created = isset($summary['created']) ? (int) $summary['created'] : 0;

        Logger::log('tools', 'Experience page resync executed', [
            'checked' => $checked,
            'created' => $created,
        ]);

        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(
                /* translators: 1: checked experiences count, 2: created pages count. */
                __('Checked %1$d experiences; created %2$d pages.', 'fp-experiences'),
                $checked,
                $created
            ),
            'checked' => $checked,
            'created' => $created,
        ]);
    }

    public function tool_fix_corrupted_arrays(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_fix_arrays_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Repair tool already executed. Try again in a moment.', 'fp-experiences'),
            ]);
        }

        $meta_keys = [
            '_fp_highlights',
            '_fp_inclusions',
            '_fp_exclusions',
            '_fp_what_to_bring',
            '_fp_notes',
        ];

        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
        ]);

        $checked = count($experiences);
        $fixed = 0;
        $details = [];

        foreach ($experiences as $post_id) {
            $post_fixed = false;

            foreach ($meta_keys as $meta_key) {
                $value = get_post_meta($post_id, $meta_key, true);

                // Controlla se è la stringa "Array" corrotta
                if (is_string($value) && strtolower(trim($value)) === 'array') {
                    delete_post_meta($post_id, $meta_key);
                    $post_fixed = true;
                    continue;
                }

                // Controlla se è un array che contiene elementi "Array"
                if (is_array($value)) {
                    $original_count = count($value);
                    $cleaned = array_filter($value, static function ($item) {
                        if (! is_string($item)) {
                            return true;
                        }
                        return strtolower(trim($item)) !== 'array';
                    });

                    if (count($cleaned) !== $original_count) {
                        if (empty($cleaned)) {
                            delete_post_meta($post_id, $meta_key);
                        } else {
                            update_post_meta($post_id, $meta_key, array_values($cleaned));
                        }
                        $post_fixed = true;
                    }
                }
            }

            if ($post_fixed) {
                $fixed++;
                $title = get_the_title($post_id);
                if ($title) {
                    $details[] = sprintf(
                        /* translators: 1: experience ID, 2: experience title. */
                        __('Fixed: #%1$d %2$s', 'fp-experiences'),
                        $post_id,
                        $title
                    );
                }
            }
        }

        Logger::log('tools', 'Corrupted array fields fixed', [
            'checked' => $checked,
            'fixed' => $fixed,
        ]);

        $message = $fixed > 0
            ? sprintf(
                /* translators: 1: checked count, 2: fixed count. */
                __('Checked %1$d experiences and fixed %2$d with corrupted data.', 'fp-experiences'),
                $checked,
                $fixed
            )
            : sprintf(
                /* translators: %d: checked count. */
                __('Checked %d experiences. No corrupted data found.', 'fp-experiences'),
                $checked
            );

        return rest_ensure_response([
            'success' => true,
            'message' => $message,
            'checked' => $checked,
            'fixed' => $fixed,
            'details' => $details,
        ]);
    }

    public function tool_backup_branding(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_backup_branding_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Backup già eseguito di recente. Riprova tra qualche istante.', 'fp-experiences'),
            ]);
        }

        // Raccoglie tutte le impostazioni di branding
        $branding_settings = [
            'fp_exp_branding' => get_option('fp_exp_branding', []),
            'fp_exp_email_branding' => get_option('fp_exp_email_branding', []),
            'fp_exp_emails' => get_option('fp_exp_emails', []),
            'fp_exp_tracking' => get_option('fp_exp_tracking', []),
            'fp_exp_brevo' => get_option('fp_exp_brevo', []),
            'fp_exp_google_calendar' => get_option('fp_exp_google_calendar', []),
            'fp_exp_experience_layout' => get_option('fp_exp_experience_layout', []),
            'fp_exp_listing' => get_option('fp_exp_listing', []),
            'fp_exp_gift' => get_option('fp_exp_gift', []),
            'fp_exp_rtb' => get_option('fp_exp_rtb', []),
            'fp_exp_enable_meeting_points' => get_option('fp_exp_enable_meeting_points', 'no'),
            'fp_exp_enable_meeting_point_import' => get_option('fp_exp_enable_meeting_point_import', 'no'),
            'fp_exp_structure_email' => get_option('fp_exp_structure_email', ''),
            'fp_exp_webmaster_email' => get_option('fp_exp_webmaster_email', ''),
            'fp_exp_debug_logging' => get_option('fp_exp_debug_logging', 'no'),
        ];

        // Aggiunge metadati del backup
        $backup_data = [
            'timestamp' => current_time('mysql'),
            'version' => get_option('fp_exp_version', 'unknown'),
            'site_url' => home_url(),
            'settings' => $branding_settings,
        ];

        // Salva il backup come opzione WordPress
        $backup_saved = update_option('fp_exp_branding_backup', $backup_data);

        if (!$backup_saved) {
            Logger::log('tools', 'Branding backup failed', [
                'user_id' => get_current_user_id(),
            ]);

            return rest_ensure_response([
                'success' => false,
                'message' => __('Impossibile salvare il backup delle impostazioni.', 'fp-experiences'),
            ]);
        }

        Logger::log('tools', 'Branding backup created', [
            'user_id' => get_current_user_id(),
            'timestamp' => $backup_data['timestamp'],
        ]);

        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(
                /* translators: %s: backup timestamp */
                __('Backup delle impostazioni di branding creato con successo il %s.', 'fp-experiences'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($backup_data['timestamp']))
            ),
            'backup_info' => [
                'timestamp' => $backup_data['timestamp'],
                'version' => $backup_data['version'],
                'settings_count' => count($branding_settings),
            ],
        ]);
    }

    public function tool_restore_branding(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_restore_branding_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Restore già eseguito di recente. Riprova tra qualche istante.', 'fp-experiences'),
            ]);
        }

        // Recupera il backup
        $backup_data = get_option('fp_exp_branding_backup', null);

        if (!$backup_data || !is_array($backup_data) || !isset($backup_data['settings'])) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Nessun backup delle impostazioni di branding trovato. Esegui prima un backup.', 'fp-experiences'),
            ]);
        }

        $settings = $backup_data['settings'];
        $restored_count = 0;
        $errors = [];

        // Ripristina ogni impostazione
        foreach ($settings as $option_name => $value) {
            $result = update_option($option_name, $value);
            if ($result) {
                $restored_count++;
            } else {
                $errors[] = $option_name;
            }
        }

        if (empty($errors)) {
            Logger::log('tools', 'Branding restore completed successfully', [
                'user_id' => get_current_user_id(),
                'restored_count' => $restored_count,
                'backup_timestamp' => $backup_data['timestamp'] ?? 'unknown',
            ]);

            return rest_ensure_response([
                'success' => true,
                'message' => sprintf(
                    /* translators: 1: restored settings count, 2: backup timestamp */
                    __('Ripristinate con successo %1$d impostazioni dal backup del %2$s.', 'fp-experiences'),
                    $restored_count,
                    isset($backup_data['timestamp']) ? date_i18n(get_option('date_format'), strtotime($backup_data['timestamp'])) : __('data sconosciuta', 'fp-experiences')
                ),
                'restored_count' => $restored_count,
                'backup_info' => [
                    'timestamp' => $backup_data['timestamp'] ?? 'unknown',
                    'version' => $backup_data['version'] ?? 'unknown',
                ],
            ]);
        } else {
            Logger::log('tools', 'Branding restore completed with errors', [
                'user_id' => get_current_user_id(),
                'restored_count' => $restored_count,
                'errors' => $errors,
                'backup_timestamp' => $backup_data['timestamp'] ?? 'unknown',
            ]);

            return rest_ensure_response([
                'success' => false,
                'message' => sprintf(
                    /* translators: 1: restored count, 2: error count */
                    __('Ripristinate %1$d impostazioni, ma %2$d hanno fallito. Controlla i log per i dettagli.', 'fp-experiences'),
                    $restored_count,
                    count($errors)
                ),
                'restored_count' => $restored_count,
                'errors' => $errors,
            ]);
        }
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
}
