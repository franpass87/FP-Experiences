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
        add_action('wp_ajax_fp_exp_get_nonce', [$this, 'ajax_get_nonce']);
        add_action('wp_ajax_nopriv_fp_exp_get_nonce', [$this, 'ajax_get_nonce']);
        add_action('wp_ajax_fp_exp_unlock_cart', [$this, 'ajax_unlock_cart']);
        add_action('wp_ajax_nopriv_fp_exp_unlock_cart', [$this, 'ajax_unlock_cart']);
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

        // Endpoint per generare nonce fresco (non cachabile)
        register_rest_route(
            'fp-exp/v1',
            '/checkout/nonce',
            [
                'methods' => 'GET',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    // Endpoint pubblico - genera nonce fresco
                    // Nessuna autenticazione richiesta (nonce stesso è la sicurezza)
                    return true;
                },
                'callback' => function (WP_REST_Request $request) {
                    nocache_headers();
                    
                    return rest_ensure_response([
                        'nonce' => wp_create_nonce('fp-exp-checkout'),
                    ]);
                },
            ]
        );

        // Lightweight endpoint per verificare lo stato del carrello lato server
        register_rest_route(
            'fp-exp/v1',
            '/cart/status',
            [
                'methods' => 'GET',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    // Endpoint pubblico - nessuna autenticazione richiesta
                    // (solo lettura, nessun dato sensibile)
                    return true;
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
                    // Permetti POST da stesso dominio (referer check)
                    // Non richiediamo nonce qui perché anche restNonce può essere cachato
                    if ($request->get_method() !== 'POST') {
                        return false;
                    }
                    
                    // Verifica referer stesso dominio
                    $referer = sanitize_text_field((string) $request->get_header('referer'));
                    if (!$referer) {
                        return false;
                    }
                    
                    $home = home_url();
                    $parsed_home = wp_parse_url($home);
                    $parsed_referer = wp_parse_url($referer);
                    
                    if ($parsed_home && $parsed_referer && 
                        isset($parsed_home['host'], $parsed_referer['host']) &&
                        $parsed_home['host'] === $parsed_referer['host']) {
                        return true;
                    }
                    
                    return false;
                },
                'callback' => function (WP_REST_Request $request) {
                    nocache_headers();

                    $experience_id = (int) $request->get_param('experience_id');
                    $slot_id = (int) $request->get_param('slot_id');
                    $slot_start = sanitize_text_field((string) $request->get_param('slot_start'));
                    $slot_end = sanitize_text_field((string) $request->get_param('slot_end'));

                    // tickets e addons possono essere mappe o array
                    $tickets = $request->get_param('tickets');
                    $addons = $request->get_param('addons');
                    $tickets = is_array($tickets) ? $tickets : [];
                    $addons = is_array($addons) ? $addons : [];

                    if ($experience_id <= 0) {
                        return new WP_Error('fp_exp_set_cart_invalid', __('Experience ID non valido.', 'fp-experiences'), ['status' => 400]);
                    }

                    $item = [
                        'experience_id' => $experience_id,
                        'slot_id' => max(0, $slot_id),
                        'tickets' => $tickets,
                        'addons' => $addons,
                        'totals' => [],
                    ];

                    // Aggiungi slot_start e slot_end se forniti (per slot dinamici)
                    if ($slot_start && $slot_end) {
                        $item['slot_start'] = $slot_start;
                        $item['slot_end'] = $slot_end;
                    }

                    $items = [$item];

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

        // Endpoint per sbloccare manualmente il carrello se rimasto bloccato
        register_rest_route(
            'fp-exp/v1',
            '/cart/unlock',
            [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    // Permetti POST da stesso dominio (referer check)
                    if ($request->get_method() !== 'POST') {
                        return false;
                    }
                    
                    // Verifica referer stesso dominio
                    $referer = sanitize_text_field((string) $request->get_header('referer'));
                    if (!$referer) {
                        return false;
                    }
                    
                    $home = home_url();
                    $parsed_home = wp_parse_url($home);
                    $parsed_referer = wp_parse_url($referer);
                    
                    if ($parsed_home && $parsed_referer && 
                        isset($parsed_home['host'], $parsed_referer['host']) &&
                        $parsed_home['host'] === $parsed_referer['host']) {
                        return true;
                    }
                    
                    return false;
                },
                'callback' => function () {
                    $this->cart->unlock();

                    return rest_ensure_response([
                        'ok' => true,
                        'locked' => $this->cart->is_locked(),
                    ]);
                },
            ]
        );
    }

    public function ajax_get_nonce(): void
    {
        nocache_headers();
        
        wp_send_json_success([
            'nonce' => wp_create_nonce('fp-exp-checkout'),
        ]);
    }

    public function ajax_unlock_cart(): void
    {
        nocache_headers();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';

        if (! wp_verify_nonce($nonce, 'fp-exp-checkout')) {
            wp_send_json_error(['message' => __('Nonce non valido.', 'fp-experiences')], 403);
        }

        $this->cart->unlock();

        wp_send_json_success(['locked' => $this->cart->is_locked()]);
    }

    public function check_checkout_permission(WP_REST_Request $request): bool
    {
        // Permetti richieste pubbliche con verifica base anti-abuso
        // La verifica del nonce fp-exp-checkout verrà fatta nel metodo
        // process_checkout() perché il nonce è nel body della richiesta POST,
        // non negli header, quindi non accessibile qui nella permission_callback
        
        // DEBUG: Log informazioni richiesta
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-EXP-CHECKOUT] Permission callback called');
            error_log('[FP-EXP-CHECKOUT] Method: ' . $request->get_method());
            error_log('[FP-EXP-CHECKOUT] Referer: ' . $request->get_header('referer'));
            error_log('[FP-EXP-CHECKOUT] Origin: ' . $request->get_header('origin'));
        }
        
        // Verifica base: stessa origine e metodo POST
        if ($request->get_method() !== 'POST') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-EXP-CHECKOUT] ❌ Permission denied: not POST');
            }
            return false;
        }
        
        // Verifica che la richiesta provenga dallo stesso sito
        $result = Helpers::verify_public_rest_request($request);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-EXP-CHECKOUT] ' . ($result ? '✅' : '❌') . ' verify_public_rest_request: ' . ($result ? 'true' : 'false'));
        }
        
        return $result;
    }

    public function handle_ajax(): void
    {
        nocache_headers();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $payload = [
            'contact' => isset($_POST['contact']) ? $_POST['contact'] : [],
            'billing' => isset($_POST['billing']) ? $_POST['billing'] : [],
            'consent' => isset($_POST['consent']) ? $_POST['consent'] : [],
        ];

        // Supporta payload JSON serializzati come stringa
        foreach (['contact', 'billing', 'consent'] as $key) {
            if (is_string($payload[$key] ?? null)) {
                $decoded = json_decode((string) $payload[$key], true);
                if (is_array($decoded)) {
                    $payload[$key] = $decoded;
                } else {
                    $payload[$key] = [];
                }
            }
        }

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
            $status = (int) ($result->get_error_data()['status'] ?? 400);
            $response = new WP_REST_Response([
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
                'data' => ['status' => $status],
            ], $status);
            $response->set_headers([
                'Content-Type' => 'application/json; charset=utf-8',
            ]);
            
            return $response;
        }

        // Assicurati che la risposta sia sempre un oggetto WP_REST_Response valido
        $response = new WP_REST_Response($result, 200);
        $response->set_headers([
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
        
        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    private function process_checkout(string $nonce, array $payload)
    {
        // Verifica presenza del nonce
        if (empty($nonce)) {
            return new WP_Error('fp_exp_missing_nonce', __('Sessione non valida. Aggiorna la pagina e riprova.', 'fp-experiences'), [
                'status' => 403,
            ]);
        }

        // Verifica validità del nonce
        if (! wp_verify_nonce($nonce, 'fp-exp-checkout')) {
            return new WP_Error('fp_exp_invalid_nonce', __('La sessione è scaduta. Aggiorna la pagina e riprova.', 'fp-experiences'), [
                'status' => 403,
            ]);
        }

        // Reset: sblocca sempre il carrello prima di continuare, per evitare stati appesi
        $this->cart->unlock();

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

        // Verifica che l'ordine sia valido
        if (! is_object($order) || ! method_exists($order, 'get_id') || ! method_exists($order, 'get_checkout_payment_url')) {
            $this->cart->unlock();

            return new WP_Error('fp_exp_checkout_invalid_order', __('Ordine non valido.', 'fp-experiences'), [
                'status' => 500,
            ]);
        }

        $order_id = $order->get_id();
        $payment_url = $order->get_checkout_payment_url(true);

        // Verifica che i dati siano validi prima di restituirli
        if (! $order_id || ! $payment_url) {
            $this->cart->unlock();

            return new WP_Error('fp_exp_checkout_invalid_response', __('Impossibile generare URL di pagamento.', 'fp-experiences'), [
                'status' => 500,
            ]);
        }

        return [
            'order_id' => $order_id,
            'payment_url' => $payment_url,
        ];
    }
}
