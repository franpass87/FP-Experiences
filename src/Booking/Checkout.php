<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function __;
use function add_action;
use function get_option;
use function is_array;
use function nocache_headers;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_verify_nonce;

use const MINUTE_IN_SECONDS;

final class Checkout
{
    private Cart $cart;

    private Orders $orders;

    public function __construct(Cart $cart, Orders $orders)
    {
        $this->cart = $cart;
        $this->orders = $orders;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_ajax_fp_exp_checkout', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_fp_exp_checkout', [$this, 'handle_ajax']);
    }

    public function register_rest_routes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/checkout',
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_rest'],
                'permission_callback' => [$this, 'check_checkout_permission'],
            ]
        );

        // Lightweight endpoint per verificare lo stato del carrello lato server
        register_rest_route(
            'fp-exp/v1',
            '/cart/status',
            [
                'methods' => 'GET',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    // Permettiamo richieste pubbliche ma con verifica base anti-abuso
                    return Helpers::verify_public_rest_request($request);
                },
                'callback' => function (WP_REST_Request $request) {
                    nocache_headers();

                    $payload = [
                        'has_items' => $this->cart->has_items(),
                        'locked' => $this->cart->is_locked(),
                    ];

                    if (! $payload['has_items']) {
                        $payload['code'] = 'fp_exp_cart_empty';
                        $payload['message'] = __('Il carrello esperienze è vuoto.', 'fp-experiences');
                    }

                    return rest_ensure_response($payload);
                },
            ]
        );

        // Endpoint per popolare il carrello prima del checkout (chiamato dal widget)
        register_rest_route(
            'fp-exp/v1',
            '/cart/set',
            [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    // Richiede stessa origine o nonce REST standard
                    return Helpers::verify_public_rest_request($request);
                },
                'callback' => function (WP_REST_Request $request) {
                    nocache_headers();

                    $experience_id = (int) $request->get_param('experience_id');
                    $slot_id = (int) $request->get_param('slot_id');

                    // tickets e addons possono essere mappe o array
                    $tickets = $request->get_param('tickets');
                    $addons = $request->get_param('addons');
                    $tickets = is_array($tickets) ? $tickets : [];
                    $addons = is_array($addons) ? $addons : [];

                    if ($experience_id <= 0) {
                        return new WP_Error('fp_exp_set_cart_invalid', __('Experience ID non valido.', 'fp-experiences'), ['status' => 400]);
                    }

                    $items = [[
                        'experience_id' => $experience_id,
                        'slot_id' => max(0, $slot_id),
                        'tickets' => $tickets,
                        'addons' => $addons,
                        'totals' => [],
                    ]];

                    $this->cart->set_items($items, [
                        'currency' => get_option('woocommerce_currency', 'EUR'),
                    ]);

                    return rest_ensure_response([
                        'ok' => true,
                        'has_items' => $this->cart->has_items(),
                    ]);
                },
            ]
        );
    }

    public function check_checkout_permission(WP_REST_Request $request): bool
    {
        return Helpers::verify_rest_nonce($request, 'fp-exp-checkout');
    }

    public function handle_ajax(): void
    {
        nocache_headers();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $payload = [
            'contact' => isset($_POST['contact']) && is_array($_POST['contact']) ? $_POST['contact'] : [],
            'billing' => isset($_POST['billing']) && is_array($_POST['billing']) ? $_POST['billing'] : [],
            'consent' => isset($_POST['consent']) && is_array($_POST['consent']) ? $_POST['consent'] : [],
        ];

        $result = $this->process_checkout($nonce, $payload);

        if ($result instanceof WP_Error) {
            $status = (int) ($result->get_error_data()['status'] ?? 400);
            wp_send_json_error([
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], $status);
        }

        wp_send_json_success($result);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle_rest(WP_REST_Request $request)
    {
        nocache_headers();

        $nonce = (string) $request->get_param('nonce');
        $payload = [
            'contact' => $request->get_param('contact'),
            'billing' => $request->get_param('billing'),
            'consent' => $request->get_param('consent'),
        ];

        $result = $this->process_checkout($nonce, $payload);

        if ($result instanceof WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    private function process_checkout(string $nonce, array $payload)
    {
        if (! wp_verify_nonce($nonce, 'fp-exp-checkout')) {
            return new WP_Error('fp_exp_invalid_nonce', __('La sessione è scaduta. Aggiorna la pagina e riprova.', 'fp-experiences'), [
                'status' => 403,
            ]);
        }

        if (Helpers::hit_rate_limit('checkout_' . Helpers::client_fingerprint(), 5, MINUTE_IN_SECONDS)) {
            return new WP_Error('fp_exp_checkout_rate_limited', __('Attendi prima di inviare un nuovo tentativo di checkout.', 'fp-experiences'), [
                'status' => 429,
            ]);
        }

        $guard = $this->cart->ensure_can_modify();

        if ($guard instanceof WP_Error) {
            return $guard;
        }

        if (! $this->cart->has_items()) {
            return new WP_Error('fp_exp_cart_empty', __('Il carrello esperienze è vuoto.', 'fp-experiences'), [
                'status' => 400,
            ]);
        }

        $cart = $this->cart->get_data();

        foreach ($cart['items'] as &$item) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
            $slot_id = (int) ($item['slot_id'] ?? 0);

            if ($slot_id <= 0) {
                $experience_id = (int) ($item['experience_id'] ?? 0);
                $start = is_string($item['slot_start'] ?? null) ? (string) $item['slot_start'] : '';
                $end = is_string($item['slot_end'] ?? null) ? (string) $item['slot_end'] : '';

                if ($experience_id > 0 && $start && $end) {
                    $ensured = Slots::ensure_slot_for_occurrence($experience_id, $start, $end);
                    if ($ensured > 0) {
                        $slot_id = $ensured;
                        $item['slot_id'] = $slot_id;
                    }
                }

                if ($slot_id <= 0) {
                    return new WP_Error('fp_exp_slot_invalid', __('Lo slot selezionato non è più disponibile.', 'fp-experiences'), [
                        'status' => 400,
                    ]);
                }
            }

            $requested = is_array($item['tickets'] ?? null) ? $item['tickets'] : [];
            $capacity = Slots::check_capacity($slot_id, $requested);

            if (! $capacity['allowed']) {
                $message = isset($capacity['message']) ? (string) $capacity['message'] : __('Lo slot selezionato è al completo.', 'fp-experiences');

                return new WP_Error('fp_exp_capacity', $message, [
                    'status' => 409,
                ]);
            }
        }

        $this->cart->lock();

        $order = $this->orders->create_order($cart, $payload);

        if ($order instanceof WP_Error) {
            $this->cart->unlock();

            return $order;
        }

        return [
            'order_id' => $order->get_id(),
            'payment_url' => $order->get_checkout_payment_url(true),
        ];
    }
}
