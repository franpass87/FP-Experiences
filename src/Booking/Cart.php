<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WP_Error;

use function __;
use function WC;
use function absint;
use function add_action;
use function add_filter;
use function apply_filters;
use function array_sum;
use function array_values;
use function current_time;
use function delete_transient;
use function function_exists;
use function get_option;
use function get_transient;
use function is_array;
use function is_cart;
use function is_checkout;
use function is_ssl;
use function sanitize_text_field;
use function setcookie;
use function time;
use function wc_add_notice;
use function wp_generate_uuid4;
use function wp_unslash;

use const DAY_IN_SECONDS;
use const WEEK_IN_SECONDS;

final class Cart implements HookableInterface
{
    private const COOKIE_NAME = 'fp_exp_sid';

    private const TRANSIENT_PREFIX = 'fp_exp_cart_';

    private const SESSION_TTL = DAY_IN_SECONDS;

    private const COOKIE_TTL = WEEK_IN_SECONDS;

    private const LOCK_TTL = 900; // 15 minuti

    private ?string $session_id = null;

    /**
     * Cart constructor.
     * 
     * @deprecated 1.2.0 Use dependency injection instead. Kept for backward compatibility.
     *             This class should be resolved via Container.
     */
    public function __construct()
    {
    }

    /**
     * Get cart instance (singleton for backward compatibility).
     * 
     * @deprecated 1.2.0 Use dependency injection via Container instead.
     *             This method is kept for backward compatibility but will be removed in version 2.0.0.
     * @return Cart
     */
    public static function instance(): Cart
    {
        // Try to get from container first (new architecture)
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(self::class)) {
                return $container->make(self::class);
            }
        }
        
        // Fallback to old singleton pattern (backward compatibility)
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        
        return $instance;
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'bootstrap_session']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'prevent_mixed_carts'], 10, 3);
        
        // Sync custom cart to WooCommerce before checkout/cart pages
        add_action('template_redirect', [$this, 'maybe_sync_to_woocommerce'], 5);
    }

    public function bootstrap_session(): void
    {
        if (null !== $this->session_id) {
            return;
        }

        $cookie = isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field(wp_unslash((string) $_COOKIE[self::COOKIE_NAME])) : '';

        if ($cookie && $this->is_valid_session($cookie)) {
            $this->session_id = $cookie;
            // FIX: Rinnova il cookie solo se esiste già (cliente che ha già interagito con esperienze).
            // NON impostare un nuovo cookie per ogni visitatore del sito, perché l'header Set-Cookie
            // può interferire con i sistemi di cache del server (Varnish, Cloudflare, LiteSpeed, ecc.)
            // causando lo strip di TUTTI i Set-Cookie, incluso quello di sessione WooCommerce.
            $this->persist_cookie($this->session_id);
        } else {
            // Genera un session ID in memoria ma NON impostare il cookie ancora.
            // Il cookie verrà impostato solo quando il cliente effettivamente aggiunge
            // qualcosa al carrello esperienze (vedi set_items()).
            $this->session_id = wp_generate_uuid4();
        }
    }

    public function get_session_id(): string
    {
        if (null === $this->session_id) {
            $this->bootstrap_session();
        }

        return $this->session_id ?? '';
    }

    /**
     * Retrieve the raw cart payload for the current session.
     *
     * @return array<string, mixed>
     */
    public function get_data(): array
    {
        $session_id = $this->get_session_id();

        if ('' === $session_id) {
            return $this->empty_payload();
        }

        $data = get_transient(self::TRANSIENT_PREFIX . $session_id);

        if (! is_array($data)) {
            return $this->empty_payload();
        }

        $data['items'] = is_array($data['items'] ?? null) ? $data['items'] : [];
        $data['locked'] = ! empty($data['locked']);
        $data['currency'] = $data['currency'] ?? get_option('woocommerce_currency', 'EUR');

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_items(): array
    {
        $data = $this->get_data();

        return is_array($data['items'] ?? null) ? $data['items'] : [];
    }

    public function has_items(): bool
    {
        return ! empty($this->get_items());
    }

    public function is_locked(): bool
    {
        $data = $this->get_data();

        if (empty($data['locked'])) {
            return false;
        }

        // Sblocca automaticamente se il lock è più vecchio del TTL
        $locked_at = isset($data['locked_at']) ? (string) $data['locked_at'] : '';
        $timestamp = $locked_at ? strtotime($locked_at) : 0;
        if ($timestamp && (time() - $timestamp) > self::LOCK_TTL) {
            $this->unlock();
            return false;
        }

        return true;
    }

    /**
     * Replace cart contents with a new payload.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $meta
     */
    public function set_items(array $items, array $meta = []): void
    {
        if ($this->is_locked()) {
            return;
        }

        $data = [
            'items' => array_map([$this, 'sanitize_item'], $items),
            'locked' => false,
            'meta' => $meta,
            'currency' => $meta['currency'] ?? get_option('woocommerce_currency', 'EUR'),
            'updated_at' => current_time('mysql', true),
        ];

        $this->store($data);
    }

    /**
     * Persist the current cart payload.
     *
     * @param array<string, mixed> $data
     */
    public function store(array $data): void
    {
        $session_id = $this->get_session_id();

        if ('' === $session_id) {
            return;
        }

        // FIX: Imposta il cookie solo quando si scrive effettivamente nel carrello esperienze.
        // Questo evita di inviare Set-Cookie su ogni richiesta, che può interferire con i cache server.
        $this->persist_cookie($session_id);

        set_transient(self::TRANSIENT_PREFIX . $session_id, $data, self::SESSION_TTL);
    }

    public function clear(): void
    {
        $session_id = $this->get_session_id();

        if ('' === $session_id) {
            return;
        }

        delete_transient(self::TRANSIENT_PREFIX . $session_id);
    }

    public function purge_session(string $session_id): void
    {
        $session_id = sanitize_text_field($session_id);

        if ('' === $session_id) {
            return;
        }

        delete_transient(self::TRANSIENT_PREFIX . $session_id);
    }

    public function lock(): void
    {
        $data = $this->get_data();
        $data['locked'] = true;
        $data['locked_at'] = current_time('mysql', true);

        $this->store($data);
    }

    public function unlock(): void
    {
        $data = $this->get_data();
        $data['locked'] = false;
        unset($data['locked_at']);

        $this->store($data);
    }

    /**
     * @return array<string, float|string>
     */
    public function get_totals(): array
    {
        $data = $this->get_data();
        $totals = [
            'subtotal' => 0.0,
            'tax' => 0.0,
            'total' => 0.0,
            'currency' => $data['currency'] ?? get_option('woocommerce_currency', 'EUR'),
        ];

        foreach ($data['items'] ?? [] as $item) {
            $totals['subtotal'] += (float) ($item['totals']['subtotal'] ?? 0.0);
            $totals['tax'] += (float) ($item['totals']['tax'] ?? ($item['totals']['tax_total'] ?? 0.0));
            $totals['total'] += (float) ($item['totals']['total'] ?? 0.0);
        }

        if ($totals['total'] <= 0.0) {
            $totals['total'] = $totals['subtotal'] + $totals['tax'];
        }

        return $totals;
    }

    public function ensure_can_modify(): ?WP_Error
    {
        if ($this->is_locked()) {
            return new WP_Error(
                'fp_exp_cart_locked',
                __('The experience cart is locked while payment is in progress.', 'fp-experiences'),
                [
                    'status' => 423, // Locked
                ]
            );
        }

        return $this->check_woocommerce_cart_state();
    }

    public function check_woocommerce_cart_state(): ?WP_Error
    {
        if ($this->can_mix_with_woocommerce()) {
            return null;
        }

        if (! function_exists('WC')) {
            return null;
        }

        $container = WC();

        if (! $container || ! isset($container->cart)) {
            return null;
        }

        $wc_cart = $container->cart;

        if (! $wc_cart || ! method_exists($wc_cart, 'get_cart_contents_count') || ! method_exists($wc_cart, 'get_cart')) {
            return null;
        }

        // Only block if WooCommerce cart has normal products (not experiences)
        if ((int) $wc_cart->get_cart_contents_count() > 0) {
            $cart_contents = $wc_cart->get_cart();
            $has_normal_products = false;
            
            foreach ($cart_contents as $cart_item) {
                // Check if this is NOT an experience item
                if (empty($cart_item['fp_exp_item'])) {
                    $has_normal_products = true;
                    break;
                }
            }
            
            // Only return error if there are normal products
            if ($has_normal_products) {
                return new WP_Error(
                    'fp_exp_cart_conflict',
                    __('Svuota il carrello di WooCommerce prima di prenotare un\'esperienza.', 'fp-experiences'),
                    [
                        'status' => 409, // Conflict
                    ]
                );
            }
        }

        return null;
    }

    public function prevent_mixed_carts(bool $passed, $product_id, $quantity): bool
    {
        if (! $passed || $this->can_mix_with_woocommerce()) {
            return $passed;
        }

        // Get the virtual product ID for experiences
        $virtual_product_id = \FP_Exp\Integrations\ExperienceProduct::get_product_id();
        
        // Check if the product being added is the experience virtual product
        $is_experience_product = ($virtual_product_id > 0 && absint($product_id) === $virtual_product_id);
        
        // If it's a normal product (not experience), check if experience cart is empty or locked
        if (! $is_experience_product) {
            // First, check if WooCommerce cart already has experience items
            if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
                $has_exp_items = false;
                $exp_keys = [];
                $cart_contents = WC()->cart->get_cart();
                foreach ($cart_contents as $key => $cart_item) {
                    if (!empty($cart_item['fp_exp_item'])) {
                        $has_exp_items = true;
                        $exp_keys[] = $key;
                    }
                }
                
                if ($has_exp_items) {
                    // FIX: Se il carrello custom FP è vuoto, gli item esperienza nel carrello WC
                    // sono "stalli" (residui di una sync precedente). Li rimuoviamo invece di bloccare.
                    if (! $this->has_items()) {
                        foreach ($exp_keys as $key) {
                            WC()->cart->remove_cart_item($key);
                        }
                        // Carrello WC pulito, permetti l'aggiunta del prodotto normale
                        return $passed;
                    }
                    
                    // Il carrello custom FP ha ancora item → blocco legittimo
                    if (function_exists('wc_add_notice')) {
                        wc_add_notice(__('Le esperienze non possono essere acquistate insieme ad altri prodotti. Completa prima la prenotazione o svuota il carrello.', 'fp-experiences'), 'error');
                    }
                    return false;
                }
            }
            
            // Allow normal products if experience custom cart is empty or locked
            if (! $this->has_items() || $this->is_locked()) {
                return $passed;
            }
            
            // If experience cart has items and is not locked, block normal products
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Le esperienze non possono essere acquistate insieme ad altri prodotti. Completa prima la prenotazione.', 'fp-experiences'), 'error');
            }
            
            return false;
        }
        
        // If it's an experience product, check if WooCommerce cart has normal products
        if ($is_experience_product && function_exists('WC') && WC()->cart) {
            $wc_cart = WC()->cart;
            $cart_contents = $wc_cart->get_cart();
            
            foreach ($cart_contents as $cart_item) {
                // Check if this is NOT an experience item (normal product)
                if (empty($cart_item['fp_exp_item'])) {
                    if (function_exists('wc_add_notice')) {
                        wc_add_notice(__('Le esperienze non possono essere acquistate insieme ad altri prodotti. Svuota il carrello prima di prenotare un\'esperienza.', 'fp-experiences'), 'error');
                    }
                    return false;
                }
            }
        }

        return $passed;
    }

    private function can_mix_with_woocommerce(): bool
    {
        return (bool) apply_filters('fp_exp_cart_can_mix', false);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function sanitize_item(array $item): array
    {
        $item['experience_id'] = absint($item['experience_id'] ?? 0);
        $item['slot_id'] = absint($item['slot_id'] ?? 0);
        $item['tickets'] = is_array($item['tickets'] ?? null) ? $item['tickets'] : [];
        $item['addons'] = is_array($item['addons'] ?? null) ? $item['addons'] : [];

        // Gestisci slot_start e slot_end per slot dinamici
        if (isset($item['slot_start'])) {
            $item['slot_start'] = sanitize_text_field((string) $item['slot_start']);
        }
        if (isset($item['slot_end'])) {
            $item['slot_end'] = sanitize_text_field((string) $item['slot_end']);
        }

        if (! isset($item['totals']) || ! is_array($item['totals'])) {
            $item['totals'] = [];
        }

        $item['totals']['subtotal'] = (float) ($item['totals']['subtotal'] ?? 0.0);
        $item['totals']['tax'] = (float) ($item['totals']['tax'] ?? ($item['totals']['tax_total'] ?? 0.0));
        $item['totals']['total'] = (float) ($item['totals']['total'] ?? 0.0);

        return $item;
    }

    private function empty_payload(): array
    {
        return [
            'items' => [],
            'locked' => false,
            'meta' => [],
            'currency' => get_option('woocommerce_currency', 'EUR'),
        ];
    }

    private function is_valid_session(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^[A-Za-z0-9\-]{10,}$/', $value);
    }

    private function persist_cookie(string $session_id): void
    {
        setcookie(
            self::COOKIE_NAME,
            $session_id,
            [
                'expires' => time() + self::COOKIE_TTL,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Maybe sync custom cart to WooCommerce cart on checkout/cart pages
     */
    public function maybe_sync_to_woocommerce(): void
    {
        $logCache = [];

        $log = static function (string $message) use (&$logCache): void {
            $should_log = Helpers::debug_logging_enabled() || apply_filters('fp_exp_debug_log_cart', false);

            if (! $should_log) {
                return;
            }

            $key = md5($message);
            $now = time();

            if (isset($logCache[$key]) && ($now - $logCache[$key]) < 300) {
                return;
            }

            if (function_exists('get_transient')) {
                $transientKey = 'fp_exp_cart_log_' . $key;
                if (false !== get_transient($transientKey)) {
                    return;
                }
                set_transient($transientKey, 1, 300);
            }

            $logCache[$key] = $now;

            if (Helpers::debug_logging_enabled()) {
                Helpers::log_debug('cart_sync', $message);
            } else {
                Logger::log('cart_sync', $message);
            }
        };

        $log('[FP-EXP-CART] maybe_sync_to_woocommerce() called');
        
        if (!function_exists('is_checkout') || !function_exists('is_cart')) {
            $log('[FP-EXP-CART] is_checkout/is_cart functions not available');
            return;
        }

        // Only sync on checkout or cart pages
        if (!is_checkout() && !is_cart()) {
            $log('[FP-EXP-CART] Not on checkout/cart page, skip sync');
            return;
        }

        $log('[FP-EXP-CART] On checkout/cart page, proceeding with sync...');

        if (!function_exists('WC') || !WC()->cart) {
            $log('[FP-EXP-CART] WooCommerce or cart not available');
            return;
        }

        // Get current cart content hash to detect changes
        $custom_cart = $this->get_data();
        $cart_hash = md5(serialize($custom_cart['items']));
        $session_id = $this->get_session_id();
        $last_synced_hash = WC()->session ? WC()->session->get('fp_exp_cart_hash_' . $session_id, '') : '';
        
        // Skip sync if cart content hasn't changed AND WooCommerce cart actually has experience items
        // FIX: Verifica anche che il carrello WC contenga effettivamente item esperienza,
        // perché la sessione WC potrebbe essere stata resettata/svuotata perdendo gli item
        $wc_has_exp_items = false;
        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (!empty($cart_item['fp_exp_item'])) {
                    $wc_has_exp_items = true;
                    break;
                }
            }
        }
        
        if ($last_synced_hash === $cart_hash && !empty($last_synced_hash) && $wc_has_exp_items) {
            $log('[FP-EXP-CART] Cart content unchanged (hash: ' . $cart_hash . ') and WC cart has items, skip sync');
            return;
        }
        
        if ($last_synced_hash === $cart_hash && !empty($last_synced_hash) && !$wc_has_exp_items) {
            $log('[FP-EXP-CART] ⚠️ Hash matches but WC cart is empty/missing experience items — forcing re-sync');
        }
        
        $log('[FP-EXP-CART] Cart content changed or first sync (hash: ' . $cart_hash . '), syncing...');
        
        if (empty($custom_cart['items'])) {
            $log('[FP-EXP-CART] Custom cart empty, nothing to sync');
            
            // FIX: Pulisci eventuali item esperienza "stalli" rimasti nel carrello WooCommerce
            // (es. da una sync precedente il cui transient FP è scaduto o è stato cancellato)
            if (!WC()->cart->is_empty()) {
                $stale_keys = [];
                foreach (WC()->cart->get_cart() as $key => $cart_item) {
                    if (!empty($cart_item['fp_exp_item'])) {
                        $stale_keys[] = $key;
                    }
                }
                if (!empty($stale_keys)) {
                    $log('[FP-EXP-CART] ⚠️ Removing ' . count($stale_keys) . ' stale experience items from WooCommerce cart');
                    foreach ($stale_keys as $key) {
                        WC()->cart->remove_cart_item($key);
                    }
                    // Invalida l'hash per evitare problemi futuri
                    if (WC()->session) {
                        WC()->session->set('fp_exp_cart_hash_' . $session_id, '');
                    }
                }
            }
            
            return;
        }

        $log('[FP-EXP-CART] ✅ Starting sync of ' . count($custom_cart['items']) . ' items to WooCommerce cart');

        // Check if WooCommerce cart has normal products (not experiences, not gift vouchers)
        $wc_cart = WC()->cart;
        $has_normal_products = false;
        $gift_keys_to_remove = [];
        if (!$wc_cart->is_empty()) {
            $cart_contents = $wc_cart->get_cart();
            foreach ($cart_contents as $key => $cart_item) {
                if (!empty($cart_item['fp_exp_item'])) {
                    continue;
                }
                if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
                    $gift_keys_to_remove[] = $key;
                    continue;
                }
                $has_normal_products = true;
                break;
            }
        }

        // If there are normal products in WooCommerce cart, don't sync experiences
        if ($has_normal_products) {
            $log('[FP-EXP-CART] ⚠️ WooCommerce cart contains normal products, skipping sync to prevent mixed carts');
            return;
        }

        // Remove residual gift items before syncing experience items
        if (!empty($gift_keys_to_remove)) {
            $log('[FP-EXP-CART] ⚠️ Removing ' . count($gift_keys_to_remove) . ' residual gift item(s) from WooCommerce cart');
            foreach ($gift_keys_to_remove as $key) {
                WC()->cart->remove_cart_item($key);
            }
        }

        // Clear WooCommerce cart only if it contains experience items or is empty
        if (!WC()->cart->is_empty()) {
            $log('[FP-EXP-CART] Clearing WooCommerce cart before sync (contains only experience items)');
            WC()->cart->empty_cart();
        }

        $synced_count = 0;

        // Add each custom cart item to WooCommerce
        foreach ($custom_cart['items'] as $index => $item) {
            $experience_id = absint($item['experience_id'] ?? 0);
            
            if ($experience_id <= 0) {
                continue;
            }

            // Cart item data (meta for this item)
            $cart_item_data = [
                'fp_exp_item' => true,
                'fp_exp_experience_id' => $experience_id,
                'fp_exp_slot_id' => absint($item['slot_id'] ?? 0),
                'fp_exp_slot_start' => sanitize_text_field($item['slot_start'] ?? ''),
                'fp_exp_slot_end' => sanitize_text_field($item['slot_end'] ?? ''),
                'fp_exp_tickets' => $item['tickets'] ?? [],
                'fp_exp_addons' => $item['addons'] ?? [],
            ];

            $tickets = is_array($item['tickets'] ?? null) ? $item['tickets'] : [];
            $quantity = array_sum(array_values($tickets));
            
            // Debug logging
            $log('[FP-EXP-CART] Experience ' . $experience_id . ' tickets data: ' . print_r($tickets, true));
            $log('[FP-EXP-CART] Calculated quantity: ' . $quantity);

            if ($quantity <= 0) {
                $log('[FP-EXP-CART] ⚠️ Quantity is 0, defaulting to 1');
                $quantity = 1;
            }

            // Add to WooCommerce cart using virtual product (NOT experience ID directly)
            $virtual_product_id = \FP_Exp\Integrations\ExperienceProduct::get_product_id();
            
            if ($virtual_product_id <= 0) {
                $log('[FP-EXP-CART] ❌ Virtual product not found! Cannot add to WooCommerce cart.');
                continue;
            }
            
            $added = WC()->cart->add_to_cart(
                $virtual_product_id, // Use virtual product ID, NOT experience ID
                $quantity,
                0, // No variation
                [], // No variation attributes
                $cart_item_data // Experience data in meta
            );

            if ($added) {
                $log('[FP-EXP-CART] ✅ Added experience ' . $experience_id . ' to WooCommerce cart (key: ' . $added . ')');
                $synced_count++;
            } else {
                $log('[FP-EXP-CART] ❌ FAILED to add experience ' . $experience_id . ' to WooCommerce cart');
            }
        }

        // Save cart hash to detect future changes
        if (WC()->session) {
            WC()->session->set('fp_exp_cart_hash_' . $session_id, $cart_hash);
            $log('[FP-EXP-CART] Saved cart hash: ' . $cart_hash);
        }

        $log('[FP-EXP-CART] Sync complete. Synced: ' . $synced_count . ', WC cart total: ' . WC()->cart->get_cart_contents_count());
        
        // If sync failed for all items, show warning
        if ($synced_count === 0 && count($custom_cart['items']) > 0) {
            $log('[FP-EXP-CART] ⚠️ WARNING: Cart sync failed for all items! WooCommerce cart is empty.');
            
            // Check if virtual product exists - this is usually the cause
            $virtual_product_id = \FP_Exp\Integrations\ExperienceProduct::get_product_id();
            $error_message = '';
            
            if ($virtual_product_id <= 0) {
                $log('[FP-EXP-CART] ❌ CAUSA: Prodotto virtuale non configurato!');
                $error_message = __('Configurazione mancante. Contatta l\'amministratore del sito.', 'fp-experiences');
            } else {
                $product = wc_get_product($virtual_product_id);
                if (!$product) {
                    $log('[FP-EXP-CART] ❌ CAUSA: Prodotto virtuale ID ' . $virtual_product_id . ' non esiste più!');
                    $error_message = __('Configurazione non valida. Contatta l\'amministratore del sito.', 'fp-experiences');
                } else {
                    // Other reason
                    $error_message = __('Si è verificato un problema durante l\'aggiunta delle esperienze al carrello. Riprova o contatta il supporto.', 'fp-experiences');
                }
            }
            
            // Add WooCommerce notice to inform user
            if (function_exists('wc_add_notice')) {
                wc_add_notice($error_message, 'error');
            }
        }
    }
}
