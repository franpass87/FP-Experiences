<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use FP_Exp\Core\Hook\HookableInterface;
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
use function is_wp_error;
use function nocache_headers;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function home_url;
use function wp_json_encode;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_verify_nonce;
use function wp_parse_url;
use function apply_filters;

use const MINUTE_IN_SECONDS;

final class Checkout implements HookableInterface
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
                    
                    // Genera nonce usando session ID invece di user ID
                    // per funzionare anche con utenti non loggati
                    $session_id = $this->cart->get_session_id();
                    $nonce = wp_create_nonce('fp-exp-checkout-' . $session_id);
                    
                    return rest_ensure_response([
                        'nonce' => $nonce,
                        'session_id' => $session_id, // Per debug
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
                    $allowed = $this->verify_public_cart_request($request);

                    $this->debug_log('cart_set_permission', 'Cart set permission evaluated', [
                        'allowed' => $allowed,
                        'method' => $request->get_method(),
                        'referer_present' => (bool) $request->get_header('referer'),
                        'origin_present' => (bool) $request->get_header('origin'),
                    ]);

                    return $allowed;
                },
                'callback' => function (WP_REST_Request $request) {
                    try {
                        nocache_headers();

                        $experience_id = (int) $request->get_param('experience_id');
                        $slot_id = (int) $request->get_param('slot_id');
                        $slot_start = sanitize_text_field((string) $request->get_param('slot_start'));
                        $slot_end = sanitize_text_field((string) $request->get_param('slot_end'));

                        $tickets = $request->get_param('tickets');
                        $addons = $request->get_param('addons');
                        $tickets = is_array($tickets) ? $tickets : [];
                        $addons = is_array($addons) ? $addons : [];

                        $this->debug_log('cart_set', 'Cart set request received', [
                            'experience_id' => $experience_id,
                            'slot_id' => $slot_id,
                            'slot_start' => $slot_start,
                            'slot_end' => $slot_end,
                            'tickets_total' => array_sum(array_map('intval', $tickets)),
                            'ticket_types' => array_slice(array_keys($tickets), 0, 5),
                            'addons_count' => count($addons),
                        ]);

                        if ($experience_id <= 0) {
                            $this->debug_log('cart_set', 'Invalid experience ID in cart set request', [
                                'experience_id' => $experience_id,
                            ]);
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

                        $this->debug_log('cart_set', 'Cart set successfully', [
                            'experience_id' => $experience_id,
                            'slot_id' => $item['slot_id'],
                            'cart_has_items' => $this->cart->has_items(),
                        ]);

                        return rest_ensure_response([
                            'ok' => true,
                            'has_items' => $this->cart->has_items(),
                        ]);
                    } catch (\Throwable $e) {
                        $this->debug_log('cart_set_error', 'Exception while setting cart', [
                            'message' => $e->getMessage(),
                            'file' => method_exists($e, 'getFile') ? $e->getFile() : '',
                            'line' => method_exists($e, 'getLine') ? (string) $e->getLine() : '',
                        ]);
                        return new WP_Error('fp_exp_cart_exception', $e->getMessage(), ['status' => 500]);
                    }
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
                    $allowed = $this->verify_public_cart_request($request);

                    $this->debug_log('cart_unlock_permission', 'Cart unlock permission evaluated', [
                        'allowed' => $allowed,
                        'method' => $request->get_method(),
                        'referer_present' => (bool) $request->get_header('referer'),
                        'origin_present' => (bool) $request->get_header('origin'),
                    ]);

                    return $allowed;
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

    private function verify_public_cart_request(WP_REST_Request $request): bool
    {
        if ($request->get_method() !== 'POST') {
            return false;
        }

        if ($this->request_matches_site_origin($request)) {
            return true;
        }

        $header_nonce = sanitize_text_field((string) $request->get_header('x-wp-nonce'));
        if ($header_nonce && wp_verify_nonce($header_nonce, 'wp_rest')) {
            return true;
        }

        return (bool) apply_filters('fp_exp_cart_public_request_allowed', false, $request);
    }

    private function request_matches_site_origin(WP_REST_Request $request): bool
    {
        $home = home_url();
        $parsed_home = wp_parse_url($home);

        if (! $parsed_home || empty($parsed_home['host'])) {
            return false;
        }

        $hosts_to_check = [
            sanitize_text_field((string) $request->get_header('referer')),
            sanitize_text_field((string) $request->get_header('origin')),
        ];

        $allowed_hosts = array_filter((array) apply_filters('fp_exp_cart_allowed_hosts', [], $request));

        foreach ($hosts_to_check as $header_value) {
            if ('' === $header_value) {
                continue;
            }

            $parsed = wp_parse_url($header_value);
            if (! $parsed || empty($parsed['host'])) {
                continue;
            }

            if (strcasecmp($parsed['host'], (string) $parsed_home['host']) === 0) {
                return true;
            }

            foreach ($allowed_hosts as $allowed_host) {
                if (strcasecmp($parsed['host'], (string) $allowed_host) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ajax_get_nonce(): void
    {
        nocache_headers();
        
        // Genera nonce usando session ID invece di user ID
        $session_id = $this->cart->get_session_id();
        $nonce = wp_create_nonce('fp-exp-checkout-' . $session_id);
        
        wp_send_json_success([
            'nonce' => $nonce,
            'session_id' => $session_id, // Per debug
        ]);
    }

    public function ajax_unlock_cart(): void
    {
        nocache_headers();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $session_id = $this->cart->get_session_id();

        if (! wp_verify_nonce($nonce, 'fp-exp-checkout-' . $session_id)) {
            wp_send_json_error(['message' => __('Nonce non valido.', 'fp-experiences')], 403);
        }

        $this->cart->unlock();

        wp_send_json_success(['locked' => $this->cart->is_locked()]);
    }

    public function check_checkout_permission(WP_REST_Request $request): bool
    {
        // Permetti richieste pubbliche con verifica base anti-abuso.
        // La verifica del nonce fp-exp-checkout viene effettuata in process_checkout()
        // perché il nonce risiede nel corpo della richiesta POST.

        $this->debug_log('checkout_permission', 'Permission callback invoked', [
            'method' => $request->get_method(),
            'referer_present' => (bool) $request->get_header('referer'),
            'origin_present' => (bool) $request->get_header('origin'),
        ]);

        if ($request->get_method() !== 'POST') {
            $this->debug_log('checkout_permission', 'Permission denied: not a POST request', [
                'method' => $request->get_method(),
            ]);
            return false;
        }

        $referer = sanitize_text_field((string) $request->get_header('referer'));
        $origin = sanitize_text_field((string) $request->get_header('origin'));
        $home = home_url();

        $referer_valid = false;
        $origin_valid = false;

        if ($referer) {
            $parsed_referer = wp_parse_url($referer);
            $parsed_home = wp_parse_url($home);

            if ($parsed_referer && $parsed_home &&
                isset($parsed_referer['host'], $parsed_home['host']) &&
                strcasecmp($parsed_referer['host'], (string) $parsed_home['host']) === 0) {
                $referer_valid = true;
            }
        }

        if ($origin) {
            $parsed_origin = wp_parse_url($origin);
            $parsed_home = wp_parse_url($home);

            if ($parsed_origin && $parsed_home &&
                isset($parsed_origin['host'], $parsed_home['host']) &&
                strcasecmp($parsed_origin['host'], (string) $parsed_home['host']) === 0) {
                $origin_valid = true;
            }
        }

        $result = $referer_valid || $origin_valid;

        if (! $result) {
            $header_nonce = sanitize_text_field((string) $request->get_header('x-wp-nonce'));

            if ($header_nonce && wp_verify_nonce($header_nonce, 'wp_rest')) {
                $result = true;
            }
        }

        $this->debug_log('checkout_permission', 'Permission evaluation completed', [
            'referer_valid' => $referer_valid,
            'origin_valid' => $origin_valid,
            'result' => $result,
        ]);

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

        $this->debug_log('checkout_rest', 'REST checkout invoked', [
            'nonce_prefix' => substr($nonce, 0, 12),
            'has_contact' => is_array($payload['contact'] ?? null),
            'has_billing' => is_array($payload['billing'] ?? null),
            'has_consent' => is_array($payload['consent'] ?? null),
        ]);

        $result = $this->process_checkout($nonce, $payload);

        $this->debug_log('checkout_rest', 'REST checkout processed', [
            'result' => is_wp_error($result) ? 'error' : 'success',
        ]);

        if ($result instanceof WP_Error) {
            $this->debug_log('checkout_rest_error', 'REST checkout failed', [
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ]);

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

        $this->debug_log('checkout_rest', 'REST checkout succeeded', [
            'order_id' => $result['order_id'] ?? null,
        ]);

        // Assicurati che la risposta sia sempre un oggetto WP_REST_Response valido
        $response = new WP_REST_Response($result, 200);
        $response->set_headers([
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
        
        return $response;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function summarize_cart_item(array $item): array
    {
        $tickets = is_array($item['tickets'] ?? null) ? $item['tickets'] : [];
        $ticket_total = 0;

        if ($tickets) {
            $ticket_total = array_sum(array_map(static fn ($value) => (int) $value, $tickets));
        }

        return [
            'experience_id' => (int) ($item['experience_id'] ?? 0),
            'slot_id' => (int) ($item['slot_id'] ?? 0),
            'slot_start' => isset($item['slot_start']) ? (string) $item['slot_start'] : '',
            'slot_end' => isset($item['slot_end']) ? (string) $item['slot_end'] : '',
            'tickets_total' => $ticket_total,
            'ticket_types' => array_slice(array_keys($tickets), 0, 5),
            'is_gift' => ! empty($item['is_gift']) || ! empty($item['gift_voucher']),
        ];
    }

    private function debug_log(string $channel, string $message, array $context = []): void
    {
        if (! Helpers::debug_logging_enabled()) {
            return;
        }

        Helpers::log_debug($channel, $message, $context);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    private function process_checkout(string $nonce, array $payload)
    {
        $this->debug_log('checkout_process', 'Starting checkout processing', [
            'nonce_present' => ! empty($nonce),
            'session_id' => $this->cart->get_session_id(),
        ]);

        if (empty($nonce)) {
            $this->debug_log('checkout_process', 'Checkout aborted: missing nonce', []);
            return new WP_Error('fp_exp_missing_nonce', __('Sessione non valida. Aggiorna la pagina e riprova.', 'fp-experiences'), [
                'status' => 403,
            ]);
        }

        // Verifica validità del nonce con session ID
        $session_id = $this->cart->get_session_id();
        $verify_result = wp_verify_nonce($nonce, 'fp-exp-checkout-' . $session_id);

        $this->debug_log('checkout_process', 'Nonce verification result', [
            'session_id' => $session_id,
            'verified' => (bool) $verify_result,
        ]);

        if (! $verify_result) {
            $this->debug_log('checkout_process', 'Checkout aborted: nonce verification failed', [
                'session_id' => $session_id,
            ]);
            return new WP_Error('fp_exp_invalid_nonce', __('La sessione è scaduta. Aggiorna la pagina e riprova.', 'fp-experiences'), [
                'status' => 403,
            ]);
        }

        $this->debug_log('checkout_process', 'Nonce valid, continuing checkout', [
            'session_id' => $session_id,
        ]);

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

        $this->debug_log('checkout_process', 'Cart snapshot before validation', [
            'items' => array_map([$this, 'summarize_cart_item'], $cart['items']),
        ]);

        foreach ($cart['items'] as &$item) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
            // Skip slot validation for gift vouchers (they don't have slots until redemption)
            $is_gift = ! empty($item['is_gift']) || ! empty($item['gift_voucher']);
            
            if ($is_gift) {
                $this->debug_log('checkout_process', 'Skipping gift voucher slot validation', [
                    'experience_id' => (int) ($item['experience_id'] ?? 0),
                ]);
                continue;
            }
            
            $slot_id = (int) ($item['slot_id'] ?? 0);

            if ($slot_id <= 0) {
                $experience_id = (int) ($item['experience_id'] ?? 0);
                $start = is_string($item['slot_start'] ?? null) ? (string) $item['slot_start'] : '';
                $end = is_string($item['slot_end'] ?? null) ? (string) $item['slot_end'] : '';

                $this->debug_log('checkout_process', 'Cart item missing slot_id, attempting ensure_slot_for_occurrence', [
                    'experience_id' => $experience_id,
                    'slot_start' => $start,
                    'slot_end' => $end,
                ]);

                if ($experience_id > 0 && $start && $end) {
                    $ensured = Slots::ensure_slot_for_occurrence($experience_id, $start, $end);
                    
                    // Handle WP_Error from ensure_slot_for_occurrence
                    if (is_wp_error($ensured)) {
                        $this->debug_log('checkout_process_error', 'ensure_slot_for_occurrence returned WP_Error', [
                            'experience_id' => $experience_id,
                            'slot_start' => $start,
                            'slot_end' => $end,
                            'error_code' => $ensured->get_error_code(),
                            'error_message' => $ensured->get_error_message(),
                        ]);
                        
                        // Return the detailed error directly
                        return new WP_Error(
                            'fp_exp_slot_invalid',
                            __('Lo slot selezionato non è più disponibile. Per favore:', 'fp-experiences') . "\n" .
                            '• ' . __('Ricarica la pagina e seleziona una nuova data', 'fp-experiences') . "\n" .
                            '• ' . __('Verifica che il calendario mostri slot disponibili', 'fp-experiences') . "\n" .
                            '• ' . __('Contatta il supporto se il problema persiste', 'fp-experiences') . "\n\n" .
                            'Dettagli tecnici: ' . $ensured->get_error_message(),
                            array_merge(
                                ['status' => 400],
                                ['error_details' => $ensured->get_error_data()]
                            )
                        );
                    }
                    
                    $this->debug_log('checkout_process', 'ensure_slot_for_occurrence succeeded', [
                        'experience_id' => $experience_id,
                        'slot_id' => $ensured,
                    ]);
                    
                    if ($ensured > 0) {
                        $slot_id = $ensured;
                        $item['slot_id'] = $slot_id;
                    }
                }

                if ($slot_id <= 0) {
                    $this->debug_log('checkout_process_error', 'Slot validation failed: slot_id still zero', [
                        'experience_id' => $experience_id,
                        'slot_start' => $start,
                        'slot_end' => $end,
                        'cart_item' => $this->summarize_cart_item($item),
                    ]);
                    
                    // Include comprehensive debug data
                    $error_data = [
                        'status' => 400,
                        'debug' => [
                            'experience_id' => $experience_id,
                            'slot_start' => $start,
                            'slot_end' => $end,
                            'slot_id_in_item' => (int) ($item['slot_id'] ?? 0),
                            'is_gift' => ! empty($item['is_gift']),
                            'gift_voucher' => ! empty($item['gift_voucher']),
                            'item_keys' => array_keys($item),
                            'availability_meta' => get_post_meta($experience_id, '_fp_exp_availability', true),
                        ],
                    ];
                    
                    // More helpful error message
                    $user_message = __('Lo slot selezionato non è più disponibile. Per favore:', 'fp-experiences') . "\n" .
                        '• ' . __('Ricarica la pagina e seleziona una nuova data', 'fp-experiences') . "\n" .
                        '• ' . __('Verifica che il calendario mostri slot disponibili', 'fp-experiences') . "\n" .
                        '• ' . __('Contatta il supporto se il problema persiste', 'fp-experiences');
                    
                    return new WP_Error('fp_exp_slot_invalid', $user_message, $error_data);
                }
            }

            $requested = is_array($item['tickets'] ?? null) ? $item['tickets'] : [];
            $capacity = Slots::check_capacity($slot_id, $requested);

            if (! $capacity['allowed']) {
                $message = isset($capacity['message']) ? (string) $capacity['message'] : __('Lo slot selezionato è al completo.', 'fp-experiences');

                 $this->debug_log('checkout_process_error', 'Slot capacity validation failed', [
                     'slot_id' => $slot_id,
                     'experience_id' => (int) ($item['experience_id'] ?? 0),
                     'tickets_total' => array_sum(array_map(static fn ($value) => (int) $value, $requested)),
                 ]);

                return new WP_Error('fp_exp_capacity', $message, [
                    'status' => 409,
                ]);
            }
        }

        $this->cart->lock();

        $order = $this->orders->create_order($cart, $payload);

        if ($order instanceof WP_Error) {
            $this->cart->unlock();

            $this->debug_log('checkout_process_error', 'Order creation returned WP_Error', [
                'code' => $order->get_error_code(),
                'message' => $order->get_error_message(),
            ]);

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

        $this->debug_log('checkout_process', 'Checkout completed successfully', [
            'order_id' => $order_id,
            'payment_url_generated' => ! empty($payment_url),
        ]);

        return [
            'order_id' => $order_id,
            'payment_url' => $payment_url,
        ];
    }
}
