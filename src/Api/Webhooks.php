<?php

declare(strict_types=1);

namespace FP_Exp\Api;

use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function __;
use function add_action;
use function array_filter;
use function current_time;
use function get_option;
use function get_transient;
use function hash_equals;
use function hash_hmac;
use function md5;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;

use const MINUTE_IN_SECONDS;
use const FP_EXP_VERSION;

/**
 * Handles webhook endpoints for external integrations.
 */
final class Webhooks
{
    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/ping',
            [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => static function (): bool {
                    return Helpers::can_manage_fp();
                },
                'callback' => [$this, 'handle_ping'],
            ]
        );

        register_rest_route(
            'fp-exp/v1',
            '/brevo',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => static function (): bool {
                    return true;
                },
                'callback' => [$this, 'handle_brevo_event'],
                'args' => [
                    'signature' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'nonce' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    public function handle_ping(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response([
            'status' => 'ok',
            'time' => current_time('mysql'),
            'version' => FP_EXP_VERSION,
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle_brevo_event(WP_REST_Request $request)
    {
        if (! $this->verify_brevo_signature($request)) {
            Logger::log('brevo-webhook', 'Rejected Brevo webhook: invalid signature or nonce.');

            return new WP_Error(
                'fp_exp_brevo_signature',
                __('Invalid Brevo webhook signature.', 'fp-experiences'),
                ['status' => 401]
            );
        }

        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];

        $event = sanitize_key((string) ($payload['event'] ?? 'unknown'));
        $message_id = sanitize_text_field((string) ($payload['message-id'] ?? ''));
        $event_time = sanitize_text_field((string) ($payload['date'] ?? ''));

        Logger::log(
            'brevo-webhook',
            'Brevo webhook received.',
            array_filter([
                'event' => $event,
                'message_id' => $message_id,
                'date' => $event_time,
            ])
        );

        return rest_ensure_response([
            'received' => true,
            'event' => $event,
        ]);
    }

    private function verify_brevo_signature(WP_REST_Request $request): bool
    {
        $settings = get_option('fp_exp_brevo', []);
        $secret = isset($settings['webhook_secret']) ? (string) $settings['webhook_secret'] : '';

        if ('' === $secret) {
            return false;
        }

        $signature = $request->get_header('x-fp-exp-signature') ?: (string) $request->get_param('signature');
        $nonce = $request->get_header('x-fp-exp-nonce') ?: (string) $request->get_param('nonce');

        $signature = sanitize_text_field((string) $signature);
        $nonce = sanitize_text_field((string) $nonce);

        if ('' === $signature || '' === $nonce) {
            return false;
        }

        $body = $request->get_body() ?: '';
        $expected = hash_hmac('sha256', $nonce . '|' . $body, $secret);

        if (! $expected || ! hash_equals($expected, $signature)) {
            return false;
        }

        $replay_key = 'fp_exp_brevo_nonce_' . md5($nonce);
        if (false !== get_transient($replay_key)) {
            return false;
        }

        set_transient($replay_key, 1, 15 * MINUTE_IN_SECONDS);

        return true;
    }
}
