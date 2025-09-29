<?php

declare(strict_types=1);

namespace FP_Exp\Api;

use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function __;
use function absint;
use function add_action;
use function current_user_can;
use function do_action;
use function home_url;
use function is_array;
use function is_wp_error;
use function get_current_user_id;
use function rest_ensure_response;
use function sanitize_key;
use function sanitize_text_field;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

use const MINUTE_IN_SECONDS;

final class RestRoutes
{
    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_post_dispatch', [$this, 'enforce_no_cache'], 10, 3);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/calendar/slots',
            [
                'methods' => 'GET',
                'permission_callback' => static function (): bool {
                    return current_user_can('fp_exp_manage_calendar');
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
                    return current_user_can('fp_exp_manage_calendar');
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
                    return current_user_can('fp_exp_manage_calendar');
                },
                'callback' => [$this, 'update_slot_capacity'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/resync-brevo',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return current_user_can('fp_exp_manage_tools');
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
                    return current_user_can('fp_exp_manage_tools');
                },
                'callback' => [$this, 'tool_replay_events'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/tools/ping',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return current_user_can('fp_exp_manage_tools');
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
                    return current_user_can('fp_exp_manage_tools');
                },
                'callback' => [$this, 'tool_clear_cache'],
            ]
        );
    }

    public function enforce_no_cache($result, $server, $request)
    {
        if (! $result instanceof WP_REST_Response) {
            return $result;
        }

        $result->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $result->header('Pragma', 'no-cache');
        $result->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $result;
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
                return [
                    'id' => (int) $slot['id'],
                    'experience_id' => (int) $slot['experience_id'],
                    'experience_title' => $slot['experience_title'] ?? '',
                    'start' => $slot['start_datetime'],
                    'end' => $slot['end_datetime'],
                    'capacity_total' => (int) $slot['capacity_total'],
                    'capacity_per_type' => $slot['capacity_per_type'],
                    'remaining' => $slot['remaining'],
                    'reserved' => $slot['reserved_total'],
                    'duration' => $slot['duration'],
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
            return new WP_Error('fp_exp_rate_limited', __('Too many calendar changes in a short period. Please retry in a moment.', 'fp-experiences'), ['status' => 429]);
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
            return new WP_Error('fp_exp_rate_limited', __('Please wait before adjusting capacity again.', 'fp-experiences'), ['status' => 429]);
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

    public function tool_resync_brevo(): WP_REST_Response
    {
        if (Helpers::hit_rate_limit('tools_resync_' . get_current_user_id(), 3, MINUTE_IN_SECONDS)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Please wait before running the Brevo sync again.', 'fp-experiences'),
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
                'message' => __('Event replay recently executed. Please retry shortly.', 'fp-experiences'),
            ]);
        }

        do_action('fp_exp_tools_replay_events');
        Logger::log('tools', 'Triggered lifecycle event replay', []);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Event replay initiated.', 'fp-experiences'),
        ]);
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
                'body' => $response->get_error_message(),
            ]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

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

        do_action('fp_exp_tools_clear_cache');
        Logger::clear();

        return rest_ensure_response([
            'success' => true,
            'message' => __('Plugin caches cleared and logs trimmed.', 'fp-experiences'),
        ]);
    }
}
