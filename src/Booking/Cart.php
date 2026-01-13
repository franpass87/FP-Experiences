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
        } else {
            $this->session_id = wp_generate_uuid4();
        }

        $this->persist_cookie($this->session_id);
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

        if (! $wc_cart || ! method_exists($wc_cart, 'get_cart_contents_count')) {
            return null;
        }

        if ((int) $wc_cart->get_cart_contents_count() > 0) {
            return new WP_Error(
                'fp_exp_cart_conflict',
                __('Svuota il carrello di WooCommerce prima di prenotare un’esperienza.', 'fp-experiences'),
                [
                    'status' => 409, // Conflict
                ]
            );
        }

        return null;
    }

    public function prevent_mixed_carts(bool $passed, $product_id, $quantity): bool
    {
        if (! $passed || $this->can_mix_with_woocommerce()) {
            return $passed;
        }

        if (! $this->has_items()) {
            return $passed;
        }

        if (function_exists('wc_add_notice')) {
            wc_add_notice(__('Le esperienze non possono essere acquistate insieme ad altri prodotti. Completa prima la prenotazione.', 'fp-experiences'), 'error');
        }

        return false;
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
        
        // Skip sync if cart content hasn't changed
        if ($last_synced_hash === $cart_hash && !empty($last_synced_hash)) {
            $log('[FP-EXP-CART] Cart content unchanged (hash: ' . $cart_hash . '), skip sync');
            return;
        }
        
        $log('[FP-EXP-CART] Cart content changed or first sync (hash: ' . $cart_hash . '), syncing...');
        
        if (empty($custom_cart['items'])) {
            $log('[FP-EXP-CART] Custom cart empty, nothing to sync');
            return;
        }

        $log('[FP-EXP-CART] ✅ Starting sync of ' . count($custom_cart['items']) . ' items to WooCommerce cart');

        // Clear WooCommerce cart (prevent mixed carts)
        if (!WC()->cart->is_empty()) {
            $log('[FP-EXP-CART] Clearing WooCommerce cart before sync');
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
