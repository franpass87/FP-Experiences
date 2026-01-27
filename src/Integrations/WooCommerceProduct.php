<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

use DateTimeImmutable;
use DateTimeZone;
use FP_Exp\Utils\Helpers;

use function absint;
use function add_filter;
use function add_action;
use function get_post_meta;
use function get_the_post_thumbnail;
use function get_the_title;
use function has_post_thumbnail;
use function is_admin;
use function is_array;
use function is_numeric;
use function sanitize_text_field;
use function __;
use function esc_html__;
use function ucfirst;
use function wc_price;
use function wp_doing_ajax;
use function wp_date;
use function wp_timezone;
use function get_option;

/**
 * Customizes WooCommerce cart/checkout display for experience items
 */
final class WooCommerceProduct implements HookableInterface
{
    public function register_hooks(): void
    {
        $this->register();
    }

    public function register(): void
    {
        // Set dynamic price for experience items (CRITICAL for checkout)
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price'], 10, 1);
        
        // STORE API: Set price when item is added to cart (for Blocks compatibility)
        add_filter('woocommerce_add_cart_item', [$this, 'set_price_on_add_to_cart'], 10, 2);
        
        // STORE API: Set price when cart is loaded from session (for Blocks compatibility)
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'set_price_on_add_to_cart'], 10, 2);
        
        // Customize cart item display
        add_filter('woocommerce_cart_item_name', [$this, 'customize_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'customize_cart_item_price'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        
        // Use experience image instead of virtual product placeholder
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'customize_cart_item_thumbnail'], 10, 3);
        
        // Save order item meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
        
        // Customize order item display
        add_filter('woocommerce_order_item_name', [$this, 'customize_order_item_name'], 10, 2);
        
        // Display order item meta with timezone conversion
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'format_order_item_meta_value'], 10, 3);

        // Hide technical meta keys from order display (admin)
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hide_technical_order_meta']);

        // Hide technical meta keys from order display (frontend - thank you page, order details)
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'filter_order_item_meta_frontend'], 10, 2);

        // Format order item meta keys with human-readable labels
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'format_order_item_meta_key'], 10, 3);

        // Show RTB notice on thank you page
        add_action('woocommerce_thankyou', [$this, 'show_rtb_thankyou_notice'], 5, 1);

        // Customize order totals display for RTB
        add_filter('woocommerce_get_order_item_totals', [$this, 'customize_rtb_order_totals'], 10, 3);

        // Disable WooCommerce emails for experience orders (FP-Experiences sends its own emails)
        add_filter('woocommerce_email_enabled_new_order', [$this, 'maybe_disable_wc_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this, 'maybe_disable_wc_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [$this, 'maybe_disable_wc_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', [$this, 'maybe_disable_wc_email'], 10, 2);
    }

    /**
     * Disable WooCommerce emails for orders containing experiences
     * FP-Experiences has its own email system with customized templates
     *
     * @param bool $enabled Whether the email is enabled
     * @param \WC_Order|null $order The order object
     * @return bool
     */
    public function maybe_disable_wc_email(bool $enabled, $order): bool
    {
        if (! $enabled || ! $order instanceof \WC_Order) {
            return $enabled;
        }

        // Check if order contains experience items
        foreach ($order->get_items() as $item) {
            $experience_id = $item->get_meta('fp_exp_experience_id');
            if ($experience_id) {
                // This is an experience order - disable WC email
                // FP-Experiences will send its own email via the Emails class
                return false;
            }
        }

        return $enabled;
    }

    /**
     * Show informative notice on thank you page for RTB orders
     *
     * @param int $order_id The order ID
     */
    public function show_rtb_thankyou_notice(int $order_id): void
    {
        if (! $order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        // Check if this is an RTB order
        $is_rtb = $order->get_meta('_fp_exp_rtb_mode');
        $created_via = $order->get_created_via();

        if (! $is_rtb && $created_via !== 'fp-exp-rtb') {
            return;
        }

        // Get reservation status
        $reservation_id = $order->get_meta('_fp_exp_reservation_id');
        $status_message = '';

        if ($reservation_id) {
            $reservation = \FP_Exp\Booking\Reservations::get_by_id((int) $reservation_id);
            if ($reservation) {
                $status = $reservation['status'] ?? '';
                switch ($status) {
                    case \FP_Exp\Booking\Reservations::STATUS_PENDING_REQUEST:
                        $status_message = __('In attesa di conferma dal nostro team.', 'fp-experiences');
                        break;
                    case \FP_Exp\Booking\Reservations::STATUS_APPROVED_PENDING_PAYMENT:
                        $status_message = __('Approvata! Completa il pagamento per confermare.', 'fp-experiences');
                        break;
                    case \FP_Exp\Booking\Reservations::STATUS_APPROVED_CONFIRMED:
                        $status_message = __('Confermata! Ti aspettiamo.', 'fp-experiences');
                        break;
                }
            }
        }

        echo '<div class="woocommerce-info fp-exp-rtb-notice" style="margin-bottom: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #0073aa;">';
        echo '<strong>' . esc_html__('Richiesta di prenotazione', 'fp-experiences') . '</strong><br>';
        echo esc_html__('La tua richiesta è stata ricevuta. Il nostro team la esaminerà e ti contatterà per confermare la disponibilità.', 'fp-experiences');
        if ($status_message) {
            echo '<br><em>' . esc_html($status_message) . '</em>';
        }
        echo '</div>';
    }

    /**
     * Customize order totals display for RTB orders
     *
     * @param array $total_rows Order total rows
     * @param \WC_Order $order The order object
     * @param string $tax_display Tax display mode
     * @return array
     */
    public function customize_rtb_order_totals(array $total_rows, $order, string $tax_display): array
    {
        if (! $order) {
            return $total_rows;
        }

        // Check if this is an RTB order
        $is_rtb = $order->get_meta('_fp_exp_rtb_mode');
        $created_via = $order->get_created_via();

        if (! $is_rtb && $created_via !== 'fp-exp-rtb') {
            return $total_rows;
        }

        // Get total from reservation meta if available
        $reservation_id = $order->get_meta('_fp_exp_reservation_id');
        $expected_total = 0;

        if ($reservation_id) {
            $reservation = \FP_Exp\Booking\Reservations::get_by_id((int) $reservation_id);
            if ($reservation && isset($reservation['total_gross'])) {
                $expected_total = (float) $reservation['total_gross'];
            }
        }

        // If order total is 0 but we have an expected total, show it differently
        $order_total = (float) $order->get_total();

        if ($order_total <= 0 && $expected_total > 0) {
            // Modify the total row to show expected amount
            if (isset($total_rows['order_total'])) {
                $total_rows['order_total']['value'] = sprintf(
                    '<del>%s</del> <span style="color: #666;">%s</span>',
                    wc_price(0),
                    sprintf(
                        /* translators: %s: expected total amount */
                        __('Da pagare dopo conferma: %s', 'fp-experiences'),
                        wc_price($expected_total)
                    )
                );
            }
        } elseif ($order_total <= 0) {
            // No expected total, just show pending message
            if (isset($total_rows['order_total'])) {
                $total_rows['order_total']['value'] = '<span style="color: #666;">' . 
                    esc_html__('In attesa di conferma', 'fp-experiences') . '</span>';
            }
        }

        return $total_rows;
    }

    /**
     * Hide technical meta keys from order item display (admin)
     * These are internal identifiers that shouldn't be shown to customers
     *
     * @param array $hidden_meta Array of meta keys to hide
     * @return array
     */
    public function hide_technical_order_meta(array $hidden_meta): array
    {
        $fp_hidden_keys = $this->get_hidden_meta_keys();

        return array_merge($hidden_meta, $fp_hidden_keys);
    }

    /**
     * Filter order item meta for frontend display (thank you page, order details)
     * Removes technical meta and formats labels
     *
     * @param array $formatted_meta Array of formatted meta data
     * @param \WC_Order_Item $item The order item
     * @return array
     */
    public function filter_order_item_meta_frontend(array $formatted_meta, $item): array
    {
        $hidden_keys = $this->get_hidden_meta_keys();
        
        // Labels for meta keys that should be shown with nice names
        $key_labels = [
            'fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            'fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
            '_fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            '_fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
        ];

        foreach ($formatted_meta as $meta_id => $meta) {
            $key = $meta->key ?? '';
            
            // Remove hidden meta
            if (in_array($key, $hidden_keys, true)) {
                unset($formatted_meta[$meta_id]);
                continue;
            }
            
            // Format display key with nice label
            if (isset($key_labels[$key])) {
                $meta->display_key = $key_labels[$key];
            }
        }

        return $formatted_meta;
    }

    /**
     * Get list of meta keys that should be hidden from display
     *
     * @return array
     */
    private function get_hidden_meta_keys(): array
    {
        return [
            'fp_exp_experience_id',
            'fp_exp_slot_id',
            'fp_exp_item',
            '_fp_exp_experience_id',
            '_fp_exp_slot_id',
            '_fp_exp_item',
            '_fp_exp_tickets',
            '_fp_exp_addons',
            '_reduced_stock',
        ];
    }

    /**
     * Format order item meta keys with human-readable labels
     *
     * @param string $display_key The meta key to display
     * @param \WC_Meta_Data $meta The meta data object
     * @param \WC_Order_Item $item The order item
     * @return string
     */
    public function format_order_item_meta_key(string $display_key, $meta, $item): string
    {
        $key_labels = [
            'fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            'fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
            '_fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            '_fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
        ];

        $meta_key = $meta->key ?? $display_key;

        return $key_labels[$meta_key] ?? $display_key;
    }

    /**
     * Set dynamic price for experience items in cart
     * This is CRITICAL - without this, the cart total will be 0
     */
    public function set_cart_item_price($cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Only for experience items
            if (empty($cart_item['fp_exp_item'])) {
                continue;
            }

            $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
            
            if ($experience_id <= 0) {
                continue;
            }

            // Get experience price
            $exp_price = get_post_meta($experience_id, '_fp_price', true);
            
            if (!is_numeric($exp_price) || $exp_price <= 0) {
                error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price: ' . $exp_price);
                continue;
            }

            // Set price per person
            // _fp_price è il prezzo per persona
            // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
            $price_per_person = (float) $exp_price;

            // Set the price per person on the product
            $cart_item['data']->set_price($price_per_person);
            
            // Get quantity for logging
            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
            $total = $price_per_person * $quantity;
            
            error_log('[FP-EXP-WC] ✅ Set price for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person x ' . $quantity . ' = ' . $total . ' EUR total');
        }
    }

    /**
     * Set price when item is added to cart or loaded from session
     * This ensures Store API (WooCommerce Blocks) gets correct price
     * 
     * @param array $cart_item Cart item data
     * @param mixed $session_values Session data or cart item key
     * @return array Modified cart item
     */
    public function set_price_on_add_to_cart(array $cart_item, $session_values = null): array
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $cart_item;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id <= 0) {
            return $cart_item;
        }

        // Get experience price
        $exp_price = get_post_meta($experience_id, '_fp_price', true);
        
        if (!is_numeric($exp_price) || $exp_price <= 0) {
            error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price on add_to_cart: ' . $exp_price);
            return $cart_item;
        }

        // Set price per person
        // _fp_price è il prezzo per persona
        // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
        $price_per_person = (float) $exp_price;

        // Set the price per person on the product
        if (isset($cart_item['data']) && is_object($cart_item['data'])) {
            $cart_item['data']->set_price($price_per_person);
            error_log('[FP-EXP-WC-STOREAPI] ✅ Set price on add_to_cart for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person');
        }

        return $cart_item;
    }

    /**
     * Use experience featured image instead of virtual product placeholder
     * 
     * @param string $thumbnail Product thumbnail HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified thumbnail HTML
     */
    public function customize_cart_item_thumbnail(string $thumbnail, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $thumbnail;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id <= 0) {
            return $thumbnail;
        }

        // Get experience featured image
        if (!has_post_thumbnail($experience_id)) {
            return $thumbnail;
        }

        // Get the thumbnail with appropriate size
        $image = get_the_post_thumbnail(
            $experience_id,
            'woocommerce_thumbnail',
            [
                'class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail',
                'alt' => get_the_title($experience_id),
            ]
        );

        return $image ?: $thumbnail;
    }

    /**
     * Customize cart item name to show experience title
     */
    public function customize_cart_item_name(string $name, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $name;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id > 0) {
            return get_the_title($experience_id);
        }

        return $name;
    }

    /**
     * Customize cart item price to show experience price
     */
    public function customize_cart_item_price(string $price, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $price;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id > 0) {
            $exp_price = get_post_meta($experience_id, '_fp_price', true);
            
            if (is_numeric($exp_price) && $exp_price > 0) {
                return wc_price($exp_price);
            }
        }

        return $price;
    }

    /**
     * Customize order item name
     */
    public function customize_order_item_name(string $name, $item): string
    {
        $experience_id = absint($item->get_meta('fp_exp_experience_id'));
        
        if ($experience_id > 0) {
            return get_the_title($experience_id);
        }

        return $name;
    }

    /**
     * Display custom cart item data in cart/checkout
     */
    public function display_cart_item_data(array $item_data, array $cart_item): array
    {
        // Check if this is an experience item
        if (empty($cart_item['fp_exp_item'])) {
            return $item_data;
        }

        // Display slot date/time (convert from UTC to local timezone)
        if (!empty($cart_item['fp_exp_slot_start'])) {
            $slot_start_utc = $cart_item['fp_exp_slot_start'];
            $formatted_time = $this->format_datetime_for_display($slot_start_utc);
            
            $item_data[] = [
                'key' => __('Data e ora', 'fp-experiences'),
                'value' => $formatted_time,
            ];
        }

        // Display tickets
        if (!empty($cart_item['fp_exp_tickets']) && is_array($cart_item['fp_exp_tickets'])) {
            foreach ($cart_item['fp_exp_tickets'] as $type => $qty) {
                if ($qty > 0) {
                    $item_data[] = [
                        'key' => ucfirst(sanitize_text_field($type)),
                        'value' => absint($qty),
                    ];
                }
            }
        }

        return $item_data;
    }

    /**
     * Save experience meta to order item when order is created
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order): void
    {
        // Check if this is an experience item
        if (empty($values['fp_exp_item'])) {
            return;
        }

        // Save all fp_exp_* meta
        $meta_keys = [
            'fp_exp_experience_id',
            'fp_exp_slot_id',
            'fp_exp_slot_start',
            'fp_exp_slot_end',
            'fp_exp_tickets',
            'fp_exp_addons',
        ];

        foreach ($meta_keys as $key) {
            if (isset($values[$key])) {
                $item->add_meta_data($key, $values[$key], true);
            }
        }

        error_log('[FP-EXP-WC] Saved order item meta for experience: ' . ($values['fp_exp_experience_id'] ?? 'unknown'));
    }

    /**
     * Format order item meta value with timezone conversion
     * 
     * @param mixed $value Meta value
     * @param object $meta Meta object
     * @param object $item Order item object
     * @return string Formatted value
     */
    public function format_order_item_meta_value($value, $meta, $item)
    {
        // Only process slot_start and slot_end meta keys
        if (!in_array($meta->key, ['fp_exp_slot_start', 'slot_start', 'fp_exp_slot_end', 'slot_end'], true)) {
            return $value;
        }

        // Convert from UTC to local timezone
        return $this->format_datetime_for_display((string) $value);
    }

    /**
     * Format datetime string from UTC to local timezone for display
     * 
     * @param string $datetime_utc Datetime string in UTC (format: Y-m-d H:i:s)
     * @return string Formatted datetime in local timezone
     */
    private function format_datetime_for_display(string $datetime_utc): string
    {
        if (empty($datetime_utc)) {
            return '';
        }

        try {
            // Parse UTC datetime
            $utc_datetime = new DateTimeImmutable($datetime_utc, new DateTimeZone('UTC'));
            
            // Convert to local timezone
            $timezone = wp_timezone();
            $local_datetime = $utc_datetime->setTimezone($timezone);
            
            // Format using WordPress date/time format settings
            $date_format = get_option('date_format', 'F j, Y');
            $time_format = get_option('time_format', 'H:i');
            
            return wp_date($date_format . ' ' . $time_format, $local_datetime->getTimestamp());
        } catch (\Exception $e) {
            // Fallback: return original value if conversion fails
            return $datetime_utc;
        }
    }
}

