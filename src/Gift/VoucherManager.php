<?php

declare(strict_types=1);

namespace FP_Exp\Gift;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP_Exp\Booking\Pricing;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use WC_Order;
use WC_Order_Item_Product;
use WP_Error;
use WP_Post;

use function absint;
use function add_action;
use function do_action;
use function add_query_arg;
use function array_filter;
use function array_map;
use function array_values;
use function bin2hex;
use function random_bytes;
use function date_i18n;
use function current_time;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_user_id;
use function get_option;
use function get_locale;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_post_modified_time;
use function get_post_time;
use function get_the_excerpt;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function home_url;
use function in_array;
use function is_array;
use function is_email;
use function is_string;
use function is_wp_error;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function strtotime;
use function time;
use function update_post_meta;
use function wp_get_scheduled_event;
use function wp_date;
use function wp_insert_post;
use function wp_mail;
use function wp_schedule_event;
use function wp_schedule_single_event;
use function wp_unschedule_event;
use function wp_timezone;
use function wc_create_order;
use function wc_get_order;
use function wp_kses_post;
use function explode;
use function wc_get_checkout_url;
use function wp_safe_redirect;
use function is_singular;
use function get_the_ID;

use const DAY_IN_SECONDS;
use const HOUR_IN_SECONDS;
use const MINUTE_IN_SECONDS;

final class VoucherManager
{
    private const CRON_HOOK = 'fp_exp_gift_send_reminders';
    private const DELIVERY_CRON_HOOK = 'fp_exp_gift_send_scheduled_voucher';

    public function register_hooks(): void
    {
        add_action('init', [$this, 'maybe_schedule_cron']);
        add_action(self::CRON_HOOK, [$this, 'process_reminders']);
        add_action(self::DELIVERY_CRON_HOOK, [$this, 'maybe_send_scheduled_voucher']);
        add_action('woocommerce_payment_complete', [$this, 'handle_payment_complete'], 20);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_order_cancelled'], 20);
        add_action('woocommerce_order_fully_refunded', [$this, 'handle_order_cancelled'], 20);
        
        // Gestione checkout WooCommerce per gift
        add_filter('woocommerce_checkout_get_value', [$this, 'prefill_checkout_fields'], 999, 2); // Priority 999 per override user loggato
        
        // FIX: Usa hook più affidabili che vengono sempre eseguiti
        add_action('woocommerce_checkout_order_processed', [$this, 'process_gift_order_after_checkout'], 10, 3);
        add_action('woocommerce_thankyou', [$this, 'process_gift_order_on_thankyou'], 5, 1); // Backup hook se il primo non funziona
        
        // FIX EMAIL: JavaScript per forzare pre-compilazione anche con utenti loggati
        add_action('wp_footer', [$this, 'output_gift_checkout_script'], 999);
        
        // Personalizza nome e prezzo prodotto gift nel cart/checkout
        add_filter('woocommerce_cart_item_name', [$this, 'customize_gift_cart_name'], 99, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'set_gift_cart_price'], 10, 3);
        add_filter('woocommerce_cart_item_permalink', '__return_null', 999); // Forza rimozione link con callback built-in
        add_filter('woocommerce_order_item_permalink', '__return_null', 999);
        
        // FIX PREZZO: Hook più efficaci per gestire prezzo dinamico gift
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_gift_price_to_cart_data'], 10, 3);
        add_filter('woocommerce_add_cart_item', [$this, 'set_gift_price_on_add'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'set_gift_price_from_session'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_dynamic_gift_price'], 10, 1);
        
        // Previeni accesso diretto alla pagina prodotto gift (causa fatal error)
        add_action('template_redirect', [$this, 'block_gift_product_page']);
        
        // Fix: Nascondi prodotto gift dalle query principali
        add_action('pre_get_posts', [$this, 'exclude_gift_product_from_queries']);
        
        // Fix: Nascondi prodotto gift anche dalle query WooCommerce (wc_get_products)
        add_filter('woocommerce_product_query_meta_query', [$this, 'exclude_gift_from_wc_queries'], 10, 2);
        
        // SOLUZIONE A: Template Override personalizzato per checkout
        add_filter('woocommerce_locate_template', [$this, 'locate_gift_template'], 10, 3);
        
        // OPZIONE 1: Validazione coupon WooCommerce gift
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_gift_coupon'], 10, 3);
        add_filter('woocommerce_coupon_error', [$this, 'custom_gift_coupon_error'], 10, 3);
    }

    public function maybe_schedule_cron(): void
    {
        if (! Helpers::gift_enabled()) {
            $this->clear_cron();

            return;
        }

        $scheduled = wp_get_scheduled_event(self::CRON_HOOK);
        $target = $this->resolve_next_cron_timestamp();

        if (! $scheduled) {
            wp_schedule_event($target, 'daily', self::CRON_HOOK);

            return;
        }

        if (abs($scheduled->timestamp - $target) > HOUR_IN_SECONDS) {
            wp_unschedule_event($scheduled->timestamp, self::CRON_HOOK);
            wp_schedule_event($target, 'daily', self::CRON_HOOK);
        }
    }

    public function clear_cron(): void
    {
        $scheduled = wp_get_scheduled_event(self::CRON_HOOK);
        if ($scheduled) {
            wp_unschedule_event($scheduled->timestamp, self::CRON_HOOK);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create_purchase(array $payload)
    {
        if (! Helpers::gift_enabled()) {
            return new WP_Error('fp_exp_gift_disabled', esc_html__('Gift vouchers are currently disabled.', 'fp-experiences'));
        }

        $experience_id = absint((string) ($payload['experience_id'] ?? 0));
        $experience = get_post($experience_id);

        if (! $experience instanceof WP_Post || 'fp_experience' !== $experience->post_type) {
            return new WP_Error('fp_exp_gift_experience', esc_html__('Experience not found.', 'fp-experiences'));
        }

        $quantity = absint((string) ($payload['quantity'] ?? 1));
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $addons_requested = [];
        if (isset($payload['addons']) && is_array($payload['addons'])) {
            $addons_requested = array_values(array_filter(array_map(static function ($value) {
                if (is_string($value)) {
                    return sanitize_key($value);
                }

                if (is_array($value) && isset($value['slug'])) {
                    return sanitize_key((string) $value['slug']);
                }

                return '';
            }, $payload['addons'])));
        }

        $pricing_addons = Pricing::get_addons($experience_id);
        $addons_selected = [];
        $addons_total = 0.0;

        foreach ($addons_requested as $slug) {
            if (! isset($pricing_addons[$slug])) {
                continue;
            }

            $addon = $pricing_addons[$slug];
            $line_total = (float) ($addon['price'] ?? 0.0);
            $allow_multiple = ! empty($addon['allow_multiple']);

            if ($allow_multiple) {
                $line_total *= $quantity;
            }

            $addons_total += max(0.0, $line_total);
            $addons_selected[$slug] = $allow_multiple ? max(1, $quantity) : 1;
        }

        $tickets = Pricing::get_ticket_types($experience_id);
        $ticket_price = null;
        foreach ($tickets as $ticket) {
            $price = (float) ($ticket['price'] ?? 0.0);
            if (null === $ticket_price || $price < $ticket_price) {
                $ticket_price = $price;
            }
        }

        $base_price = (float) get_post_meta($experience_id, '_fp_base_price', true);
        if ($base_price < 0) {
            $base_price = 0.0;
        }

        $total = $base_price;
        if (null !== $ticket_price && $ticket_price > 0) {
            $total += $ticket_price * $quantity;
        }

        $total += $addons_total;

        if ($total <= 0) {
            return new WP_Error('fp_exp_gift_total', esc_html__('Unable to calculate a price for the voucher.', 'fp-experiences'));
        }

        $purchaser = $this->sanitize_contact($payload['purchaser'] ?? []);
        $recipient = $this->sanitize_contact($payload['recipient'] ?? []);
        $recipient['message'] = isset($payload['message']) ? sanitize_textarea_field((string) $payload['message']) : '';
        $delivery = $this->normalize_delivery($payload['delivery'] ?? []);

        if (! $purchaser['email']) {
            return new WP_Error('fp_exp_gift_purchaser_email', esc_html__('Provide the purchaser email address.', 'fp-experiences'));
        }

        if (! $recipient['email']) {
            return new WP_Error('fp_exp_gift_recipient_email', esc_html__('Provide the recipient email address.', 'fp-experiences'));
        }

        $currency = get_option('woocommerce_currency', 'EUR');

        if (! function_exists('WC')) {
            return new WP_Error('fp_exp_gift_wc', esc_html__('WooCommerce is required to purchase gift vouchers.', 'fp-experiences'));
        }

        // FLUSSO OPZIONE C: Salva dati in session + Redirect a checkout standard
        // WooCommerce creerà l'ordine durante il checkout, noi aggiungiamo il voucher dopo

        // Genera codice voucher in anticipo
        $code = $this->generate_code();
        $valid_until = $this->calculate_valid_until();

            // FIX SESSION: Salva in transient E session (doppia protezione)
            $gift_pending_data = [
                'experience_id' => $experience_id,
                'experience_title' => $experience->post_title,
                'quantity' => $quantity,
                'addons' => $addons_selected,
                'purchaser' => $purchaser,
                'recipient' => $recipient,
                'delivery' => $delivery,
                'total' => $total,
                'currency' => $currency,
                'code' => $code,
                'valid_until' => $valid_until,
            ];

            $prefill_data = [
                'billing_first_name' => $purchaser['name'],
                'billing_email' => $purchaser['email'],
                'billing_phone' => $purchaser['phone'],
            ];

            if (WC()->session) {
                WC()->session->set('fp_exp_gift_pending', $gift_pending_data);
                WC()->session->set('fp_exp_gift_prefill', $prefill_data);
                
                // Salva anche in transient usando session ID come chiave
                $session_id = WC()->session->get_customer_id();
                error_log("FP Experiences: Saving gift transient with session_id: {$session_id}");
                
                if ($session_id) {
                    $transient_key = 'fp_exp_gift_' . $session_id;
                    $saved = set_transient($transient_key, [
                        'pending' => $gift_pending_data,
                        'prefill' => $prefill_data,
                    ], HOUR_IN_SECONDS);
                    error_log("FP Experiences: Transient saved: " . ($saved ? 'YES' : 'NO') . " with key: {$transient_key}");
                } else {
                    error_log("FP Experiences: WARNING - No session_id available for transient!");
                }
            }

        // Svuota e aggiungi gift product al cart
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (WC()->cart) {
            WC()->cart->empty_cart();
            
            // Aggiungi un prodotto semplice virtuale al cart con i dati gift
            // Usa il prodotto gift (ID 199) come base
            $gift_product_id = (int) get_option('fp_exp_gift_product_id', 199);
            
            // OPZIONE 1 FIX: Salva TUTTI i dati gift nei cart_item_data (più affidabile di session/transient)
            $cart_item_data = [
                '_fp_exp_item_type' => 'gift',
                'gift_voucher' => 'yes',
                'experience_id' => $experience_id,
                'experience_title' => $experience->post_title,
                'gift_quantity' => $quantity,
                '_fp_exp_gift_price' => (float) $total,
                // Dati completi gift per recupero in checkout
                '_fp_exp_gift_full_data' => $gift_pending_data,
                '_fp_exp_gift_prefill_data' => $prefill_data,
            ];

            $cart_item_key = WC()->cart->add_to_cart($gift_product_id, 1, 0, [], $cart_item_data);
            
            WC()->cart->calculate_totals();
        }

        // Redirect al checkout WooCommerce STANDARD
        $checkout_url = function_exists('wc_get_checkout_url') 
            ? wc_get_checkout_url()
            : home_url('/checkout/');

        return [
            'checkout_url' => $checkout_url,
            'value' => $total,
            'currency' => $currency,
            'experience_title' => $experience->post_title,
            'code' => $code,
        ];
    }

    public function handle_payment_complete(int $order_id): void
    {
        $voucher_ids = $this->get_voucher_ids_from_order($order_id);
        if (! $voucher_ids) {
            return;
        }

        foreach ($voucher_ids as $voucher_id) {
            $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
            if ('pending' !== $status) {
                continue;
            }

            update_post_meta($voucher_id, '_fp_exp_gift_status', 'active');
            $this->append_log($voucher_id, 'activated', $order_id);
            $this->sync_voucher_table($voucher_id);

            $delivery = get_post_meta($voucher_id, '_fp_exp_gift_delivery', true);
            $delivery = is_array($delivery) ? $delivery : [];
            $send_at = isset($delivery['send_at']) ? (int) $delivery['send_at'] : 0;
            $now = current_time('timestamp', true);

            if ($send_at > ($now + MINUTE_IN_SECONDS)) {
                $this->schedule_delivery($voucher_id, $send_at);
                $this->append_log($voucher_id, 'scheduled', $order_id);

                continue;
            }

            $this->clear_delivery_schedule($voucher_id);
            $this->send_voucher_email($voucher_id);
        }
    }

    public function handle_order_cancelled(int $order_id): void
    {
        $voucher_ids = $this->get_voucher_ids_from_order($order_id);
        if (! $voucher_ids) {
            return;
        }

        foreach ($voucher_ids as $voucher_id) {
            $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
            if (in_array($status, ['redeemed', 'cancelled', 'expired'], true)) {
                continue;
            }

            $this->clear_delivery_schedule($voucher_id);
            $delivery = get_post_meta($voucher_id, '_fp_exp_gift_delivery', true);
            if (is_array($delivery)) {
                $delivery['send_at'] = 0;
                unset($delivery['scheduled_at']);
                update_post_meta($voucher_id, '_fp_exp_gift_delivery', $delivery);
            }
            update_post_meta($voucher_id, '_fp_exp_gift_status', 'cancelled');
            $this->append_log($voucher_id, 'cancelled', $order_id);
            $this->sync_voucher_table($voucher_id);
        }
    }

    public function maybe_send_scheduled_voucher($voucher_id): void
    {
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
        if ('active' !== $status) {
            $this->clear_delivery_schedule($voucher_id);

            return;
        }

        $delivery = get_post_meta($voucher_id, '_fp_exp_gift_delivery', true);
        $delivery = is_array($delivery) ? $delivery : [];
        $sent_at = isset($delivery['sent_at']) ? (int) $delivery['sent_at'] : 0;

        if ($sent_at > 0) {
            $this->clear_delivery_schedule($voucher_id);

            return;
        }

        $send_at = isset($delivery['send_at']) ? (int) $delivery['send_at'] : 0;
        $now = current_time('timestamp', true);

        if ($send_at > ($now + MINUTE_IN_SECONDS)) {
            $this->schedule_delivery($voucher_id, $send_at);

            return;
        }

        $this->send_voucher_email($voucher_id);
    }

    public function process_reminders(): void
    {
        if (! Helpers::gift_enabled()) {
            return;
        }

        $now = current_time('timestamp', true);
        $offsets = Helpers::gift_reminder_offsets();
        $batch_size = 50;
        $page = 1;

        do {
            $voucher_ids = get_posts([
                'post_type' => VoucherCPT::POST_TYPE,
                'post_status' => 'any',
                'posts_per_page' => $batch_size,
                'paged' => $page,
                'fields' => 'ids',
                'meta_key' => '_fp_exp_gift_status',
                'meta_value' => 'active',
                'no_found_rows' => true,
            ]);

            if (! $voucher_ids) {
                break;
            }

            $voucher_ids = array_map('absint', $voucher_ids);

            foreach ($voucher_ids as $voucher_id) {
                if ($voucher_id <= 0) {
                    continue;
                }

                $valid_until = (int) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true);

                if ($valid_until > 0 && $valid_until <= $now) {
                    update_post_meta($voucher_id, '_fp_exp_gift_status', 'expired');
                    $this->append_log($voucher_id, 'expired');
                    $this->sync_voucher_table($voucher_id);
                    $this->send_expired_email($voucher_id);
                    continue;
                }

                if ($valid_until <= 0) {
                    continue;
                }

                $sent = get_post_meta($voucher_id, '_fp_exp_gift_reminders_sent', true);
                $sent = is_array($sent) ? array_map('absint', $sent) : [];

                foreach ($offsets as $offset) {
                    if (in_array($offset, $sent, true)) {
                        continue;
                    }

                    $reminder_timestamp = $valid_until - ($offset * DAY_IN_SECONDS);
                    if ($reminder_timestamp <= $now && $valid_until > $now) {
                        $this->send_reminder_email($voucher_id, $offset, $valid_until);
                        $sent[] = $offset;
                    }
                }

                $sent = array_values(array_unique(array_map('absint', $sent)));
                update_post_meta($voucher_id, '_fp_exp_gift_reminders_sent', $sent);
            }

            $page++;
        } while (count($voucher_ids) === $batch_size);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_voucher_by_code(string $code)
    {
        $code = sanitize_key($code);
        if ('' === $code) {
            return new WP_Error('fp_exp_gift_code', esc_html__('Voucher code not provided.', 'fp-experiences'));
        }

        $voucher = $this->find_voucher_by_code($code);
        if (! $voucher) {
            return new WP_Error('fp_exp_gift_not_found', esc_html__('Voucher not found.', 'fp-experiences'));
        }

        return $this->build_voucher_payload($voucher);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function redeem_voucher(string $code, array $payload)
    {
        $voucher = $this->find_voucher_by_code($code);
        if (! $voucher) {
            return new WP_Error('fp_exp_gift_not_found', esc_html__('Voucher not found.', 'fp-experiences'));
        }

        $voucher_id = $voucher->ID;
        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
        if ('active' !== $status) {
            return new WP_Error('fp_exp_gift_not_active', esc_html__('This voucher cannot be redeemed.', 'fp-experiences'));
        }

        $valid_until = (int) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true);
        $now = current_time('timestamp', true);
        if ($valid_until > 0 && $valid_until < $now) {
            update_post_meta($voucher_id, '_fp_exp_gift_status', 'expired');
            $this->append_log($voucher_id, 'expired');
            $this->sync_voucher_table($voucher_id);

            return new WP_Error('fp_exp_gift_expired', esc_html__('This voucher has expired.', 'fp-experiences'));
        }

        $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
        $slot_id = absint((string) ($payload['slot_id'] ?? 0));
        if ($slot_id <= 0) {
            return new WP_Error('fp_exp_gift_slot', esc_html__('Select a timeslot to redeem the voucher.', 'fp-experiences'));
        }

        $slot = Slots::get_slot($slot_id);
        if (! $slot || (int) ($slot['experience_id'] ?? 0) !== $experience_id) {
            return new WP_Error('fp_exp_gift_invalid_slot', esc_html__('The selected slot is no longer available.', 'fp-experiences'));
        }

        if (Slots::STATUS_CANCELLED === ($slot['status'] ?? '')) {
            return new WP_Error('fp_exp_gift_cancelled_slot', esc_html__('The selected slot is no longer available.', 'fp-experiences'));
        }

        $snapshot = Slots::get_capacity_snapshot($slot_id);
        $capacity_total = absint((string) ($slot['capacity_total'] ?? 0));

        $quantity = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_quantity', true));
        if ($quantity <= 0) {
            $quantity = 1;
        }

        if ($capacity_total > 0 && ($snapshot['total'] + $quantity) > $capacity_total) {
            return new WP_Error('fp_exp_gift_capacity', esc_html__('The selected slot cannot accommodate the voucher quantity.', 'fp-experiences'));
        }

        if (! function_exists('wc_create_order')) {
            return new WP_Error('fp_exp_gift_wc', esc_html__('WooCommerce is required to redeem vouchers.', 'fp-experiences'));
        }

        try {
            $order = wc_create_order([
                'status' => 'completed',
            ]);
        } catch (Exception $exception) {
            return new WP_Error('fp_exp_gift_redeem_order', esc_html__('Unable to create the redemption order.', 'fp-experiences'));
        }

        if (is_wp_error($order)) {
            return new WP_Error('fp_exp_gift_redeem_order', esc_html__('Unable to create the redemption order.', 'fp-experiences'));
        }

        $experience = get_post($experience_id);
        $title = $experience instanceof WP_Post ? $experience->post_title : esc_html__('Experience', 'fp-experiences');

        $order->set_created_via('fp-exp-gift');
        $order->set_currency(get_option('woocommerce_currency', 'EUR'));
        $order->set_prices_include_tax(false);
        $order->set_shipping_total(0.0);
        $order->set_shipping_tax(0.0);

        $recipient = get_post_meta($voucher_id, '_fp_exp_gift_recipient', true);
        $recipient = is_array($recipient) ? $recipient : [];
        $order->set_billing_first_name($recipient['name'] ?? '');
        $order->set_billing_email($recipient['email'] ?? '');

        $item = new WC_Order_Item_Product();
        $item->set_product_id(0);
        $item->set_type('fp_experience_item');
        $item->set_name(sprintf(
            /* translators: %s: experience title. */
            esc_html__('Gift redemption – %s', 'fp-experiences'),
            $title
        ));
        $item->set_quantity(1);
        $item->set_total(0.0);
        $item->add_meta_data('experience_id', $experience_id, true);
        $item->add_meta_data('experience_title', $title, true);
        $item->add_meta_data('gift_redemption', 'yes', true);
        $item->add_meta_data('slot_id', $slot_id, true);
        $item->add_meta_data('slot_start', $slot['start_datetime'] ?? '', true);
        $item->add_meta_data('slot_end', $slot['end_datetime'] ?? '', true);
        $item->add_meta_data('gift_quantity', $quantity, true);
        $addons_payload = $this->normalize_voucher_addons($experience_id, get_post_meta($voucher_id, '_fp_exp_gift_addons', true) ?: []);
        $item->add_meta_data('gift_addons', $addons_payload, true);
        $order->add_item($item);
        $order->set_total(0.0);
        $order->calculate_totals(false);
        $order->save();

        $addons_selected = get_post_meta($voucher_id, '_fp_exp_gift_addons', true);
        $addons_selected = is_array($addons_selected) ? array_map('absint', $addons_selected) : [];

        $reservation_id = Reservations::create([
            'order_id' => $order->get_id(),
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
            'status' => Reservations::STATUS_PAID,
            'pax' => [
                'gift' => [
                    'quantity' => $quantity,
                ],
            ],
            'addons' => $addons_selected,
            'meta' => [
                'gift_code' => get_post_meta($voucher_id, '_fp_exp_gift_code', true),
                'redeemed_via' => 'gift',
            ],
            'locale' => get_locale(),
        ]);

        if ($reservation_id <= 0) {
            $order->delete(true);

            return new WP_Error('fp_exp_gift_reservation', esc_html__('Impossibile registrare il riscatto del voucher. Riprova.', 'fp-experiences'));
        }

        update_post_meta($voucher_id, '_fp_exp_gift_status', 'redeemed');
        update_post_meta($voucher_id, '_fp_exp_gift_redeemed_order_id', $order->get_id());
        update_post_meta($voucher_id, '_fp_exp_gift_redeemed_reservation_id', $reservation_id);
        $this->append_log($voucher_id, 'redeemed', $order->get_id());
        $this->sync_voucher_table($voucher_id);

        // OPZIONE 1: Invalida il coupon WooCommerce associato
        $this->invalidate_gift_coupon($voucher_id);

        $this->send_redeemed_email($voucher_id, $order->get_id(), $slot);

        $experience_permalink = $experience instanceof WP_Post ? get_permalink($experience) : '';

        do_action('fp_exp_gift_voucher_redeemed', $voucher_id, $order->get_id(), $reservation_id);

        return [
            'order_id' => $order->get_id(),
            'reservation_id' => $reservation_id,
            'experience' => [
                'id' => $experience_id,
                'title' => $title,
                'permalink' => $experience_permalink,
            ],
        ];
    }

    private function resolve_next_cron_timestamp(): int
    {
        $time_string = Helpers::gift_reminder_time();
        [$hour, $minute] = array_map('intval', explode(':', $time_string));
        $timezone = wp_timezone();

        $now = new DateTimeImmutable('now', $timezone);
        $target = $now->setTime($hour, $minute, 0);
        if ($target <= $now) {
            $target = $target->modify('+1 day');
        }

        return $target->getTimestamp();
    }

    private function generate_code(): string
    {
        try {
            $code = strtoupper(bin2hex(random_bytes(16)));
        } catch (Exception $exception) {
            $code = strtoupper(bin2hex(random_bytes(8)));
        }

        return substr($code, 0, 32);
    }

    private function calculate_valid_until(): int
    {
        $days = Helpers::gift_validity_days();
        $now = current_time('timestamp', true);

        return $now + ($days * DAY_IN_SECONDS);
    }

    /**
     * @param mixed $data
     *
     * @return array{send_on: string, send_at: int, timezone: string, scheduled_at?: int, sent_at?: int}
     */
    private function normalize_delivery($data): array
    {
        $delivery = [
            'send_on' => '',
            'send_at' => 0,
            'timezone' => 'Europe/Rome',
        ];

        if (! is_array($data)) {
            return $delivery;
        }

        $send_on = '';
        if (isset($data['send_on'])) {
            $send_on = (string) $data['send_on'];
        } elseif (isset($data['date'])) {
            $send_on = (string) $data['date'];
        }

        $send_on = sanitize_text_field($send_on);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $send_on)) {
            return $delivery;
        }

        $delivery['send_on'] = $send_on;

        $time = '09:00';
        if (isset($data['time']) && preg_match('/^\d{2}:\d{2}$/', (string) $data['time'])) {
            $time = (string) $data['time'];
        }

        try {
            $timezone = new DateTimeZone($delivery['timezone']);
        } catch (Exception $exception) {
            $wp_timezone = wp_timezone();
            $timezone = $wp_timezone instanceof DateTimeZone ? $wp_timezone : new DateTimeZone('UTC');
            $delivery['timezone'] = $timezone->getName();
        }

        try {
            $scheduled = new DateTimeImmutable(sprintf('%s %s', $send_on, $time), $timezone);
            $delivery['send_at'] = $scheduled->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        } catch (Exception $exception) {
            $delivery['send_on'] = '';
            $delivery['send_at'] = 0;
        }

        return $delivery;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{name: string, email: string, phone: string}
     */
    private function sanitize_contact($data): array
    {
        $data = is_array($data) ? $data : [];

        $name = sanitize_text_field((string) ($data['name'] ?? ($data['full_name'] ?? '')));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $phone = sanitize_text_field((string) ($data['phone'] ?? ''));

        if (! is_email($email)) {
            $email = '';
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function schedule_delivery(int $voucher_id, int $send_at): void
    {
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0 || $send_at <= 0) {
            return;
        }

        $existing = wp_get_scheduled_event(self::DELIVERY_CRON_HOOK, [$voucher_id]);
        if ($existing) {
            wp_unschedule_event($existing->timestamp, self::DELIVERY_CRON_HOOK, [$voucher_id]);
        }

        wp_schedule_single_event($send_at, self::DELIVERY_CRON_HOOK, [$voucher_id]);

        $delivery = get_post_meta($voucher_id, '_fp_exp_gift_delivery', true);
        $delivery = is_array($delivery) ? $delivery : [];
        $delivery['send_at'] = $send_at;
        $delivery['scheduled_at'] = $send_at;
        update_post_meta($voucher_id, '_fp_exp_gift_delivery', $delivery);
    }

    private function clear_delivery_schedule(int $voucher_id): void
    {
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $existing = wp_get_scheduled_event(self::DELIVERY_CRON_HOOK, [$voucher_id]);
        if ($existing) {
            wp_unschedule_event($existing->timestamp, self::DELIVERY_CRON_HOOK, [$voucher_id]);
        }
    }

    private function append_log(int $voucher_id, string $event, ?int $order_id = null): void
    {
        $logs = get_post_meta($voucher_id, '_fp_exp_gift_logs', true);
        $logs = is_array($logs) ? $logs : [];

        $logs[] = [
            'event' => $event,
            'timestamp' => time(),
            'user' => get_current_user_id(),
            'order_id' => $order_id,
        ];

        update_post_meta($voucher_id, '_fp_exp_gift_logs', $logs);
    }

    private function sync_voucher_table(int $voucher_id): void
    {
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));

        if ('' === $code) {
            return;
        }

        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
        if ('' === $status) {
            $status = 'pending';
        }

        $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
        $valid_until = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true));
        $value = (float) get_post_meta($voucher_id, '_fp_exp_gift_value', true);
        $currency = sanitize_text_field((string) get_post_meta($voucher_id, '_fp_exp_gift_currency', true));
        $created = (int) get_post_time('U', true, $voucher_id, true);
        $modified = (int) get_post_modified_time('U', true, $voucher_id, true);

        VoucherTable::upsert([
            'voucher_id' => $voucher_id,
            'code' => $code,
            'status' => $status,
            'experience_id' => $experience_id,
            'valid_until' => $valid_until,
            'value' => $value,
            'currency' => $currency,
            'created_at' => $created ?: null,
            'updated_at' => $modified ?: time(),
        ]);
    }

    private function get_voucher_ids_from_order(int $order_id): array
    {
        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return [];
        }

        $ids = $order->get_meta('_fp_exp_gift_voucher_ids');
        if (is_array($ids)) {
            return array_values(array_map('absint', $ids));
        }

        return [];
    }

    private function find_voucher_by_code(string $code): ?WP_Post
    {
        $record = VoucherTable::get_by_code($code);

        if (is_array($record) && ! empty($record['voucher_id'])) {
            $voucher = get_post(absint((string) $record['voucher_id']));

            if ($voucher instanceof WP_Post) {
                return $voucher;
            }
        }

        $vouchers = get_posts([
            'post_type' => VoucherCPT::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_fp_exp_gift_code',
            'meta_value' => $code,
        ]);

        if (! $vouchers) {
            return null;
        }

        return $vouchers[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function build_voucher_payload(WP_Post $voucher): array
    {
        $voucher_id = $voucher->ID;
        $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
        $experience = get_post($experience_id);
        $valid_until = (int) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true);
        $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));
        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));

        $slots = $this->load_upcoming_slots($experience_id);
        $addons_quantities = get_post_meta($voucher_id, '_fp_exp_gift_addons', true);
        $addons_quantities = is_array($addons_quantities) ? $addons_quantities : [];

        return [
            'voucher_id' => $voucher_id,
            'code' => $code,
            'status' => $status,
            'valid_until' => $valid_until,
            'valid_until_label' => $valid_until > 0 ? date_i18n(get_option('date_format', 'Y-m-d'), $valid_until) : '',
            'quantity' => absint((string) get_post_meta($voucher_id, '_fp_exp_gift_quantity', true)),
            'addons' => $this->normalize_voucher_addons($experience_id, $addons_quantities),
            'experience' => $this->build_experience_payload($experience),
            'slots' => $slots,
            'value' => (float) get_post_meta($voucher_id, '_fp_exp_gift_value', true),
            'currency' => (string) get_post_meta($voucher_id, '_fp_exp_gift_currency', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function build_experience_payload(?WP_Post $experience): array
    {
        if (! $experience) {
            return [];
        }

        return [
            'id' => $experience->ID,
            'title' => $experience->post_title,
            'permalink' => get_permalink($experience),
            'excerpt' => wp_kses_post(get_the_excerpt($experience)),
            'image' => get_the_post_thumbnail_url($experience, 'medium'),
        ];
    }

    /**
     * @param array<string, mixed> $addons
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalize_voucher_addons(int $experience_id, array $addons): array
    {
        if (! $addons) {
            return [];
        }

        $catalog = Pricing::get_addons($experience_id);
        $normalized = [];

        foreach ($addons as $slug => $quantity) {
            $slug_key = sanitize_key((string) $slug);

            if (! isset($catalog[$slug_key])) {
                continue;
            }

            $addon = $catalog[$slug_key];
            $qty = absint((string) $quantity);
            $normalized[] = [
                'slug' => $slug_key,
                'label' => (string) $addon['label'],
                'quantity' => $addon['allow_multiple'] ? max(1, $qty) : 1,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function load_upcoming_slots(int $experience_id): array
    {
        if ($experience_id <= 0) {
            return [];
        }

        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $end = $now->modify('+1 year');

        $slots = Slots::get_slots_in_range(
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            [
                'experience_id' => $experience_id,
                'statuses' => [Slots::STATUS_OPEN],
            ]
        );

        if (! $slots) {
            return [];
        }

        return array_values(array_map([$this, 'format_slot'], $slots));
    }

    /**
     * @param array<string, mixed> $slot
     *
     * @return array<string, mixed>
     */
    private function format_slot(array $slot): array
    {
        $start = isset($slot['start_datetime']) ? (string) $slot['start_datetime'] : '';
        $end = isset($slot['end_datetime']) ? (string) $slot['end_datetime'] : '';
        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');

        $slot['label'] = '';
        if ($start) {
            $timestamp = strtotime($start . ' UTC');
            if ($timestamp) {
                $slot['label'] = wp_date($date_format . ' ' . $time_format, $timestamp);
                $slot['start_label'] = $slot['label'];
            }
        }

        if ($end) {
            $end_timestamp = strtotime($end . ' UTC');
            if ($end_timestamp) {
                $slot['end_label'] = wp_date($date_format . ' ' . $time_format, $end_timestamp);
            }
        }

        return $slot;
    }

    private function send_voucher_email(int $voucher_id): void
    {
        $recipient = get_post_meta($voucher_id, '_fp_exp_gift_recipient', true);
        $recipient = is_array($recipient) ? $recipient : [];
        $email = sanitize_email((string) ($recipient['email'] ?? ''));
        if (! $email) {
            return;
        }

        $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
        $experience = get_post($experience_id);
        $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));
        $valid_until = (int) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true);
        $redeem_link = add_query_arg('gift', $code, Helpers::gift_redeem_page());

        $subject = sprintf(
            /* translators: %s: experience title. */
            esc_html__('You received a gift: %s', 'fp-experiences'),
            $experience instanceof WP_Post ? $experience->post_title : esc_html__('FP Experience', 'fp-experiences')
        );

        $message = '<p>' . esc_html__('You have received a gift voucher for an FP Experience!', 'fp-experiences') . '</p>';
        if ($experience instanceof WP_Post) {
            $message .= '<p><strong>' . esc_html($experience->post_title) . '</strong></p>';
        }
        
        // OPZIONE 1: Istruzioni coupon WooCommerce
        $value = get_post_meta($voucher_id, '_fp_exp_gift_value', true);
        $currency = get_post_meta($voucher_id, '_fp_exp_gift_currency', true) ?: 'EUR';
        
        $message .= '<h3>' . esc_html__('Come usare il tuo regalo:', 'fp-experiences') . '</h3>';
        $message .= '<p>' . esc_html__('Il tuo codice regalo è anche un coupon sconto da usare al checkout:', 'fp-experiences') . '</p>';
        $message .= '<p><strong style="font-size: 18px; color: #2e7d32;">' . esc_html(strtoupper($code)) . '</strong></p>';
        
        if ($value) {
            $message .= '<p>' . sprintf(
                esc_html__('Valore: %s %s', 'fp-experiences'),
                number_format((float) $value, 2, ',', '.'),
                esc_html($currency)
            ) . '</p>';
        }
        
        if ($valid_until > 0) {
            $message .= '<p>' . esc_html__('Valido fino al:', 'fp-experiences') . ' <strong>' . esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)) . '</strong></p>';
        }
        
        $message .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
        $message .= '<h4>' . esc_html__('Istruzioni:', 'fp-experiences') . '</h4>';
        $message .= '<ol style="line-height: 1.8;">';
        $message .= '<li>' . esc_html__('Visita la pagina dell\'esperienza e scegli data e orario', 'fp-experiences') . '</li>';
        $message .= '<li>' . esc_html__('Aggiungi al carrello e procedi al checkout', 'fp-experiences') . '</li>';
        $message .= '<li>' . esc_html__('Inserisci il codice coupon durante il pagamento', 'fp-experiences') . '</li>';
        $message .= '<li>' . esc_html__('Lo sconto verrà applicato automaticamente!', 'fp-experiences') . '</li>';
        $message .= '</ol>';
        
        if ($experience instanceof WP_Post) {
            $exp_link = get_permalink($experience);
            if ($exp_link) {
                $message .= '<p style="text-align: center; margin-top: 30px;">';
                $message .= '<a href="' . esc_url($exp_link) . '" style="display: inline-block; padding: 12px 30px; background-color: #2e7d32; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">';
                $message .= esc_html__('Prenota ora', 'fp-experiences');
                $message .= '</a>';
                $message .= '</p>';
            }
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);

        $purchaser = get_post_meta($voucher_id, '_fp_exp_gift_purchaser', true);
        $purchaser = is_array($purchaser) ? $purchaser : [];
        $purchaser_email = sanitize_email((string) ($purchaser['email'] ?? ''));
        if ($purchaser_email && $purchaser_email !== $email) {
            $copy = '<p>' . esc_html__('Your gift voucher was sent to the recipient.', 'fp-experiences') . '</p>';
            $copy .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code)) . '</strong></p>';
            wp_mail($purchaser_email, esc_html__('Gift voucher dispatched', 'fp-experiences'), $copy, $headers);
        }

        $delivery = get_post_meta($voucher_id, '_fp_exp_gift_delivery', true);
        $delivery = is_array($delivery) ? $delivery : [];
        $delivery['sent_at'] = current_time('timestamp', true);
        $delivery['send_at'] = 0;
        unset($delivery['scheduled_at']);
        update_post_meta($voucher_id, '_fp_exp_gift_delivery', $delivery);
        $this->clear_delivery_schedule($voucher_id);
        $this->append_log($voucher_id, 'dispatched');
    }

    private function send_reminder_email(int $voucher_id, int $offset, int $valid_until): void
    {
        $recipient = get_post_meta($voucher_id, '_fp_exp_gift_recipient', true);
        $recipient = is_array($recipient) ? $recipient : [];
        $email = sanitize_email((string) ($recipient['email'] ?? ''));
        if (! $email) {
            return;
        }

        $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));
        $redeem_link = add_query_arg('gift', $code, Helpers::gift_redeem_page());

        $subject = esc_html__('Reminder: your experience gift is waiting', 'fp-experiences');
        $message = '<p>' . sprintf(
            /* translators: %d: days left. */
            esc_html__('Your gift voucher will expire in %d day(s).', 'fp-experiences'),
            $offset
        ) . '</p>';
        $message .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code)) . '</strong></p>';
        $message .= '<p>' . esc_html__('Valid until:', 'fp-experiences') . ' ' . esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)) . '</p>';
        $message .= '<p><a href="' . esc_url($redeem_link) . '">' . esc_html__('Schedule your experience', 'fp-experiences') . '</a></p>';

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    private function send_expired_email(int $voucher_id): void
    {
        $recipient = get_post_meta($voucher_id, '_fp_exp_gift_recipient', true);
        $recipient = is_array($recipient) ? $recipient : [];
        $email = sanitize_email((string) ($recipient['email'] ?? ''));
        if (! $email) {
            return;
        }

        $subject = esc_html__('Your experience gift has expired', 'fp-experiences');
        $message = '<p>' . esc_html__('Il voucher collegato alla tua esperienza FP è scaduto. Contatta l’operatore per assistenza.', 'fp-experiences') . '</p>';

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    private function send_redeemed_email(int $voucher_id, int $order_id, array $slot): void
    {
        $recipient = get_post_meta($voucher_id, '_fp_exp_gift_recipient', true);
        $recipient = is_array($recipient) ? $recipient : [];
        $email = sanitize_email((string) ($recipient['email'] ?? ''));
        if (! $email) {
            return;
        }

        $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
        $experience = get_post($experience_id);
        $subject = esc_html__('Your gift experience is booked', 'fp-experiences');
        $message = '<p>' . esc_html__('Your gift voucher has been successfully redeemed.', 'fp-experiences') . '</p>';
        if ($experience instanceof WP_Post) {
            $message .= '<p><strong>' . esc_html($experience->post_title) . '</strong></p>';
        }
        if (! empty($slot['start_datetime'])) {
            $timestamp = strtotime((string) $slot['start_datetime'] . ' UTC');
            if ($timestamp) {
                $message .= '<p>' . esc_html__('Scheduled for:', 'fp-experiences') . ' ' . esc_html(wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'H:i'), $timestamp)) . '</p>';
            }
        }

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    /**
     * FIX EMAIL: Output script JavaScript nel footer per forzare pre-compilazione checkout
     */
    public function output_gift_checkout_script(): void
    {
        // Solo nella pagina checkout
        if (!is_checkout() || is_wc_endpoint_url('order-received')) {
            return;
        }

        // Verifica se c'è un gift in session
        if (!WC()->session) {
            return;
        }

        $gift_prefill = WC()->session->get('fp_exp_gift_prefill');
        
        if (!is_array($gift_prefill) || empty($gift_prefill)) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Dati gift da pre-compilare
            var giftData = <?php echo wp_json_encode($gift_prefill); ?>;
            
            console.log('FP-Experiences: Gift prefill data loaded', giftData);
            
            // Funzione per impostare i valori
            function setGiftCheckoutFields() {
                var changed = false;
                
                if (giftData.billing_first_name) {
                    var $field = $('#billing_first_name');
                    if ($field.length && $field.val() !== giftData.billing_first_name) {
                        $field.val(giftData.billing_first_name).trigger('change');
                        changed = true;
                        console.log('FP-Experiences: Set billing_first_name to', giftData.billing_first_name);
                    }
                }
                
                if (giftData.billing_email) {
                    var $email = $('#billing_email');
                    if ($email.length && $email.val() !== giftData.billing_email) {
                        $email.val(giftData.billing_email).trigger('change');
                        changed = true;
                        console.log('FP-Experiences: Set billing_email to', giftData.billing_email);
                    }
                }
                
                if (giftData.billing_phone) {
                    var $phone = $('#billing_phone');
                    if ($phone.length && $phone.val() !== giftData.billing_phone) {
                        $phone.val(giftData.billing_phone).trigger('change');
                        changed = true;
                        console.log('FP-Experiences: Set billing_phone to', giftData.billing_phone);
                    }
                }
                
                return changed;
            }
            
            // Imposta subito
            setTimeout(function() {
                setGiftCheckoutFields();
            }, 100);
            
            // Re-imposta dopo update_checkout
            $(document.body).on('updated_checkout', function() {
                console.log('FP-Experiences: Checkout updated, re-setting fields');
                setGiftCheckoutFields();
            });
            
            // Re-imposta periodicamente (per sicurezza con temi custom)
            var checkCount = 0;
            var checkInterval = setInterval(function() {
                if (setGiftCheckoutFields()) {
                    console.log('FP-Experiences: Fields updated (retry ' + (checkCount + 1) + ')');
                }
                checkCount++;
                if (checkCount >= 10) {
                    clearInterval(checkInterval);
                    console.log('FP-Experiences: Stopped retrying field updates');
                }
            }, 500);
        });
        </script>
        <?php
    }

    /**
     * Pre-compila i campi del checkout WooCommerce con i dati dal form gift
     */
    public function prefill_checkout_fields($value, string $input)
    {
        // Verifica se ci sono dati gift in session
        if (!function_exists('WC') || !WC()->session) {
            return $value;
        }

        $gift_data = WC()->session->get('fp_exp_gift_prefill');
        
        if (!is_array($gift_data) || empty($gift_data)) {
            return $value;
        }

        // Pre-compila i campi billing
        // SOLUZIONE A: FORZA override anche per utenti loggati
        if (isset($gift_data[$input]) && !empty($gift_data[$input])) {
            return $gift_data[$input];
        }

        return $value;
    }

    /**
     * FIX COMPLETO: Processa ordine gift dopo checkout (metadati + voucher + email)
     * Usa hook woocommerce_checkout_order_processed che viene SEMPRE eseguito
     */
    public function process_gift_order_after_checkout(int $order_id, $posted_data, $order): void
    {
        // OPZIONE 1 FIX: Recupera dati dal CART invece che da session/transient
        if (!WC()->cart) {
            error_log("FP Experiences: No cart available in checkout_order_processed for order #{$order_id}");
            return;
        }

        $gift_data = null;
        $prefill_data = null;

        // Cerca nei cart items quello con dati gift
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
                $gift_data = $cart_item['_fp_exp_gift_full_data'] ?? null;
                $prefill_data = $cart_item['_fp_exp_gift_prefill_data'] ?? null;
                error_log("FP Experiences: Found gift data in cart for order #{$order_id}");
                break;
            }
        }
        
        if (!is_array($gift_data) || empty($gift_data)) {
            error_log("FP Experiences: No gift data in cart for order #{$order_id}");
            return;
        }

        error_log("FP Experiences: Processing gift order #{$order_id} via checkout_order_processed hook");

        // 1. FIX EMAIL: Forza billing data corretti dall'acquirente gift
        if (is_array($prefill_data) && !empty($prefill_data)) {
            // Forza email corretta dall'acquirente (non dall'utente loggato)
            if (!empty($prefill_data['billing_email'])) {
                $order->set_billing_email($prefill_data['billing_email']);
                error_log("FP Experiences: Forced billing_email to {$prefill_data['billing_email']}");
            }
            
            // Forza nome/cognome dall'acquirente
            if (!empty($prefill_data['billing_first_name'])) {
                $full_name = $prefill_data['billing_first_name'];
                $parts = explode(' ', $full_name, 2);
                $order->set_billing_first_name($parts[0]);
                if (isset($parts[1])) {
                    $order->set_billing_last_name($parts[1]);
                }
                error_log("FP Experiences: Forced billing_name to {$full_name}");
            }
            
            // Forza telefono dall'acquirente
            if (!empty($prefill_data['billing_phone'])) {
                $order->set_billing_phone($prefill_data['billing_phone']);
            }
            
            // Salva modifiche billing
            $order->save();
        }

        // 2. Aggiungi metadati gift all'ordine
        $order->update_meta_data('_fp_exp_is_gift_order', 'yes');
        $order->update_meta_data('_fp_exp_gift_purchase', [
            'experience_id' => $gift_data['experience_id'],
            'quantity' => $gift_data['quantity'],
            'value' => $gift_data['total'],
            'currency' => $gift_data['currency'],
        ]);
        $order->update_meta_data('_fp_exp_gift_code', $gift_data['code']);
        $order->set_created_via('fp-exp-gift');
        $order->save();

        error_log("FP Experiences: Saved gift metadata for order #{$order_id}");

        // 3. Crea il voucher post
        $voucher_id = $this->create_gift_voucher_post($order_id, $gift_data);
        
        if ($voucher_id) {
            error_log("FP Experiences: Created gift voucher #{$voucher_id} for order #{$order_id}");
        }

        // 4. Pulisci session (se presente)
        if (WC()->session) {
            WC()->session->set('fp_exp_gift_pending', null);
            WC()->session->set('fp_exp_gift_prefill', null);
            error_log("FP Experiences: Cleared gift session data");
        }
    }

    /**
     * FIX COMPLETO: Processa ordine gift nella pagina thankyou (backup hook)
     * Questo hook viene SEMPRE eseguito nella pagina "ordine ricevuto"
     */
    public function process_gift_order_on_thankyou(int $order_id): void
    {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Verifica se è già stato processato
        if ($order->get_meta('_fp_exp_is_gift_order') === 'yes') {
            error_log("FP Experiences: Order #{$order_id} already processed as gift");
            return;
        }

        // FIX: Recupera dati da transient invece che da session (session si pulisce)
        $gift_data = null;
        $prefill_data = null;
        
        if (WC()->session) {
            $session_id = WC()->session->get_customer_id();
            error_log("FP Experiences: Looking for gift transient with session_id: {$session_id} for order #{$order_id}");
            
            if ($session_id) {
                $transient_key = 'fp_exp_gift_' . $session_id;
                $transient_data = get_transient($transient_key);
                
                error_log("FP Experiences: Transient data found: " . (is_array($transient_data) ? 'YES' : 'NO') . " with key: {$transient_key}");
                
                if (is_array($transient_data)) {
                    $gift_data = $transient_data['pending'] ?? null;
                    $prefill_data = $transient_data['prefill'] ?? null;
                    
                    error_log("FP Experiences: Retrieved gift data from transient for order #{$order_id}");
                    
                    // Pulisci transient dopo il recupero
                    delete_transient($transient_key);
                }
            } else {
                error_log("FP Experiences: No session_id available in thankyou hook for order #{$order_id}");
            }
        }
        
        if (!is_array($gift_data) || empty($gift_data)) {
            error_log("FP Experiences: No gift data in transient for order #{$order_id}");
            return;
        }

        error_log("FP Experiences: Processing gift order #{$order_id} via thankyou hook");

        // 1. FIX EMAIL: Forza billing data corretti dall'acquirente gift
        
        if (is_array($prefill_data) && !empty($prefill_data)) {
            // Forza email corretta
            if (!empty($prefill_data['billing_email'])) {
                $order->set_billing_email($prefill_data['billing_email']);
                error_log("FP Experiences: Forced billing_email to {$prefill_data['billing_email']}");
            }
            
            // Forza nome/cognome
            if (!empty($prefill_data['billing_first_name'])) {
                $full_name = $prefill_data['billing_first_name'];
                $parts = explode(' ', $full_name, 2);
                $order->set_billing_first_name($parts[0]);
                if (isset($parts[1])) {
                    $order->set_billing_last_name($parts[1]);
                }
                error_log("FP Experiences: Forced billing_name to {$full_name}");
            }
            
            // Forza telefono
            if (!empty($prefill_data['billing_phone'])) {
                $order->set_billing_phone($prefill_data['billing_phone']);
            }
            
            $order->save();
        }

        // 2. Aggiungi metadati gift
        $order->update_meta_data('_fp_exp_is_gift_order', 'yes');
        $order->update_meta_data('_fp_exp_gift_purchase', [
            'experience_id' => $gift_data['experience_id'],
            'quantity' => $gift_data['quantity'],
            'value' => $gift_data['total'],
            'currency' => $gift_data['currency'],
        ]);
        $order->update_meta_data('_fp_exp_gift_code', $gift_data['code']);
        $order->set_created_via('fp-exp-gift');
        $order->save();

        error_log("FP Experiences: Saved gift metadata for order #{$order_id}");

        // 3. Crea voucher
        $voucher_id = $this->create_gift_voucher_post($order_id, $gift_data);
        
        if ($voucher_id) {
            error_log("FP Experiences: Created gift voucher #{$voucher_id} for order #{$order_id}");
        }

        // 4. Pulisci session
        WC()->session->set('fp_exp_gift_pending', null);
        WC()->session->set('fp_exp_gift_prefill', null);
        
        error_log("FP Experiences: Cleared gift session data");
    }

    /**
     * Crea il post del gift voucher (estratto per riuso)
     */
    private function create_gift_voucher_post(int $order_id, array $gift_data): ?int
    {
        $voucher_id = wp_insert_post([
            'post_type' => 'fp_exp_gift_voucher',
            'post_title' => 'Gift Voucher - ' . $gift_data['code'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
        ]);

        if (is_wp_error($voucher_id)) {
            error_log('FP Experiences Gift: Failed to create voucher post: ' . $voucher_id->get_error_message());
            return null;
        }

        // Salva metadati
        update_post_meta($voucher_id, '_fp_exp_voucher_code', $gift_data['code']);
        update_post_meta($voucher_id, '_fp_exp_experience_id', $gift_data['experience_id']);
        update_post_meta($voucher_id, '_fp_exp_quantity', $gift_data['quantity']);
        update_post_meta($voucher_id, '_fp_exp_value', $gift_data['total']);
        update_post_meta($voucher_id, '_fp_exp_currency', $gift_data['currency']);
        update_post_meta($voucher_id, '_fp_exp_valid_until', $gift_data['valid_until']);
        update_post_meta($voucher_id, '_fp_exp_purchaser_name', $gift_data['purchaser']['name']);
        update_post_meta($voucher_id, '_fp_exp_purchaser_email', $gift_data['purchaser']['email']);
        update_post_meta($voucher_id, '_fp_exp_recipient_name', $gift_data['recipient']['name']);
        update_post_meta($voucher_id, '_fp_exp_recipient_email', $gift_data['recipient']['email']);
        update_post_meta($voucher_id, '_fp_exp_delivery_mode', $gift_data['delivery']);
        update_post_meta($voucher_id, '_fp_exp_status', 'pending');
        update_post_meta($voucher_id, '_fp_exp_wc_order_id', $order_id);

        // Addons
        if (!empty($gift_data['addons'])) {
            update_post_meta($voucher_id, '_fp_exp_addons', $gift_data['addons']);
        }

        // OPZIONE 1: Crea coupon WooCommerce per il destinatario
        $coupon_id = $this->create_woocommerce_coupon_for_gift($voucher_id, $gift_data);
        
        if ($coupon_id) {
            update_post_meta($voucher_id, '_fp_exp_wc_coupon_id', $coupon_id);
            error_log("FP Experiences: Created WooCommerce coupon #{$coupon_id} for gift voucher #{$voucher_id}");
        }

        return $voucher_id;
    }

    /**
     * Crea un coupon WooCommerce collegato al gift voucher
     */
    private function create_woocommerce_coupon_for_gift(int $voucher_id, array $gift_data): ?int
    {
        if (!class_exists('WC_Coupon')) {
            error_log('FP Experiences: WC_Coupon class not found');
            return null;
        }

        $code = strtoupper($gift_data['code']);
        $amount = (float) $gift_data['total'];
        $experience_id = (int) $gift_data['experience_id'];
        $valid_until = (int) $gift_data['valid_until'];

        // Crea il coupon
        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type('fixed_cart'); // Sconto fisso sul carrello
        $coupon->set_amount($amount);
        $coupon->set_individual_use(true); // Non può essere combinato con altri coupon
        $coupon->set_usage_limit(1); // Può essere usato una sola volta
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_limit_usage_to_x_items(0);
        
        // Data di scadenza
        if ($valid_until > 0) {
            $expiry_date = gmdate('Y-m-d', $valid_until);
            $coupon->set_date_expires($expiry_date);
        }

        // Descrizione
        $experience = get_post($experience_id);
        $experience_title = $experience instanceof WP_Post ? $experience->post_title : 'Experience';
        $coupon->set_description(sprintf(
            'Gift voucher per: %s (ID: %d)',
            $experience_title,
            $voucher_id
        ));

        // Email restriction (solo destinatario può usarlo)
        $recipient_email = $gift_data['recipient']['email'] ?? '';
        if (!empty($recipient_email)) {
            $coupon->set_email_restrictions([$recipient_email]);
        }

        // Meta data per collegamento al voucher
        $coupon->update_meta_data('_fp_exp_gift_voucher_id', $voucher_id);
        $coupon->update_meta_data('_fp_exp_experience_id', $experience_id);
        $coupon->update_meta_data('_fp_exp_is_gift_coupon', 'yes');

        try {
            $coupon_id = $coupon->save();
            return $coupon_id;
        } catch (Exception $e) {
            error_log('FP Experiences: Failed to create coupon: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Valida che il coupon gift sia usato solo per l'esperienza corretta
     */
    public function validate_gift_coupon(bool $valid, $coupon, $discount_obj): bool
    {
        // Controlla se è un coupon gift
        if (!$coupon || !$coupon->get_id()) {
            return $valid;
        }

        $is_gift_coupon = $coupon->get_meta('_fp_exp_is_gift_coupon');
        
        if ($is_gift_coupon !== 'yes') {
            return $valid; // Non è un coupon gift, validazione standard
        }

        // Recupera l'ID dell'esperienza associata al coupon
        $required_experience_id = (int) $coupon->get_meta('_fp_exp_experience_id');
        
        if (!$required_experience_id) {
            return $valid; // Nessuna restrizione specifica
        }

        // Verifica che nel carrello ci sia l'esperienza corretta
        if (!WC()->cart) {
            return false;
        }

        $has_valid_experience = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            // Controlla se c'è l'esperienza nel carrello (item RTB con experience_id)
            $item_experience_id = 0;
            
            // Caso 1: Item RTB normale
            if (isset($cart_item['experience_id'])) {
                $item_experience_id = (int) $cart_item['experience_id'];
            }
            
            // Caso 2: Meta data dell'item
            if (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_meta')) {
                $meta_exp_id = $cart_item['data']->get_meta('experience_id');
                if ($meta_exp_id) {
                    $item_experience_id = (int) $meta_exp_id;
                }
            }

            if ($item_experience_id === $required_experience_id) {
                $has_valid_experience = true;
                break;
            }
        }

        if (!$has_valid_experience) {
            // Messaggio personalizzato (gestito da custom_gift_coupon_error)
            add_filter('woocommerce_coupon_error', function($err, $err_code, $coupon_obj) use ($coupon, $required_experience_id) {
                if ($coupon_obj && $coupon_obj->get_id() === $coupon->get_id()) {
                    $experience = get_post($required_experience_id);
                    $exp_title = $experience instanceof WP_Post ? $experience->post_title : 'l\'esperienza corretta';
                    return sprintf(
                        esc_html__('Questo coupon gift può essere usato solo per "%s".', 'fp-experiences'),
                        $exp_title
                    );
                }
                return $err;
            }, 10, 3);
            
            return false;
        }

        return $valid;
    }

    /**
     * Messaggio di errore personalizzato per coupon gift
     */
    public function custom_gift_coupon_error($err, $err_code, $coupon)
    {
        // Il messaggio viene gestito direttamente in validate_gift_coupon
        return $err;
    }

    /**
     * Invalida il coupon quando il voucher viene usato
     */
    private function invalidate_gift_coupon(int $voucher_id): void
    {
        $coupon_id = (int) get_post_meta($voucher_id, '_fp_exp_wc_coupon_id', true);
        
        if (!$coupon_id) {
            return;
        }

        $coupon = new \WC_Coupon($coupon_id);
        
        if (!$coupon->get_id()) {
            return;
        }

        // Imposta usage count al massimo per renderlo non utilizzabile
        $coupon->set_usage_count($coupon->get_usage_limit());
        $coupon->save();
        
        error_log("FP Experiences: Invalidated coupon #{$coupon_id} for redeemed voucher #{$voucher_id}");
    }

    /**
     * Aggiungi metadati gift all'ordine durante la creazione al checkout
     * @deprecated Usare process_gift_order_after_checkout invece
     */
    public function add_gift_metadata_to_order($order, $data): void
    {
        if (!WC()->session) {
            return;
        }

        $gift_data = WC()->session->get('fp_exp_gift_pending');
        
        if (!is_array($gift_data) || empty($gift_data)) {
            return;
        }

        // FIX EMAIL: Forza billing data corretti dall'acquirente gift
        $prefill_data = WC()->session->get('fp_exp_gift_prefill');
        
        if (is_array($prefill_data) && !empty($prefill_data)) {
            // Forza email corretta dall'acquirente (non dall'utente loggato)
            if (!empty($prefill_data['billing_email'])) {
                $order->set_billing_email($prefill_data['billing_email']);
            }
            
            // Forza nome/cognome dall'acquirente
            if (!empty($prefill_data['billing_first_name'])) {
                $full_name = $prefill_data['billing_first_name'];
                $parts = explode(' ', $full_name, 2);
                $order->set_billing_first_name($parts[0]);
                if (isset($parts[1])) {
                    $order->set_billing_last_name($parts[1]);
                }
            }
            
            // Forza telefono dall'acquirente
            if (!empty($prefill_data['billing_phone'])) {
                $order->set_billing_phone($prefill_data['billing_phone']);
            }
        }

        // Aggiungi metadati gift all'ordine
        $order->update_meta_data('_fp_exp_is_gift_order', 'yes');
        $order->update_meta_data('_fp_exp_gift_purchase', [
            'experience_id' => $gift_data['experience_id'],
            'quantity' => $gift_data['quantity'],
            'value' => $gift_data['total'],
            'currency' => $gift_data['currency'],
        ]);
        $order->update_meta_data('_fp_exp_gift_code', $gift_data['code']);
        $order->set_created_via('fp-exp-gift');
    }

    /**
     * Crea il voucher post dopo che WooCommerce ha creato l'ordine al checkout
     */
    public function create_voucher_on_checkout($order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        if (!WC()->session) {
            return;
        }

        $gift_data = WC()->session->get('fp_exp_gift_pending');
        
        if (!is_array($gift_data) || empty($gift_data)) {
            return;
        }

        // Crea il voucher post
        $voucher_id = wp_insert_post([
            'post_type' => VoucherCPT::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sprintf(
                esc_html__('Gift voucher for %s', 'fp-experiences'),
                $gift_data['experience_title']
            ),
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($voucher_id) || $voucher_id <= 0) {
            return;
        }

        // Salva tutti i metadata del voucher
        update_post_meta($voucher_id, '_fp_exp_gift_code', $gift_data['code']);
        update_post_meta($voucher_id, '_fp_exp_gift_status', 'pending');
        update_post_meta($voucher_id, '_fp_exp_gift_experience_id', $gift_data['experience_id']);
        update_post_meta($voucher_id, '_fp_exp_gift_quantity', $gift_data['quantity']);
        update_post_meta($voucher_id, '_fp_exp_gift_addons', $gift_data['addons']);
        update_post_meta($voucher_id, '_fp_exp_gift_purchaser', $gift_data['purchaser']);
        update_post_meta($voucher_id, '_fp_exp_gift_recipient', $gift_data['recipient']);
        update_post_meta($voucher_id, '_fp_exp_gift_order_id', $order->get_id());
        update_post_meta($voucher_id, '_fp_exp_gift_valid_until', $gift_data['valid_until']);
        update_post_meta($voucher_id, '_fp_exp_gift_value', $gift_data['total']);
        update_post_meta($voucher_id, '_fp_exp_gift_currency', $gift_data['currency']);
        update_post_meta($voucher_id, '_fp_exp_gift_delivery', $gift_data['delivery']);
        update_post_meta($voucher_id, '_fp_exp_gift_logs', [
            [
                'event' => 'created',
                'timestamp' => time(),
                'user' => get_current_user_id(),
                'order_id' => $order->get_id(),
            ],
        ]);

        VoucherTable::upsert([
            'voucher_id' => $voucher_id,
            'code' => $gift_data['code'],
            'status' => 'pending',
            'experience_id' => $gift_data['experience_id'],
            'valid_until' => $gift_data['valid_until'],
            'value' => $gift_data['total'],
            'currency' => $gift_data['currency'],
            'created_at' => time(),
        ]);

        // Link voucher all'ordine
        $order->update_meta_data('_fp_exp_gift_voucher_ids', [$voucher_id]);
        $order->save();

        // Pulisci session
        WC()->session->set('fp_exp_gift_pending', null);
        WC()->session->set('fp_exp_gift_prefill', null);
    }

    /**
     * Personalizza nome del gift nel cart
     */
    public function customize_gift_cart_name(string $name, array $cart_item, string $cart_item_key): string
    {
        if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
            $title = $cart_item['experience_title'] ?? '';
            if ($title) {
                return sprintf(
                    esc_html__('Gift voucher – %s', 'fp-experiences'),
                    $title
                );
            }
        }

        return $name;
    }

    /**
     * Imposta prezzo dinamico per gift nel cart
     */
    public function set_gift_cart_price(string $price_html, array $cart_item, string $cart_item_key): string
    {
        if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
            $gift_data = WC()->session ? WC()->session->get('fp_exp_gift_pending') : null;
            
            if (is_array($gift_data) && isset($gift_data['total'])) {
                return wc_price($gift_data['total']);
            }
        }

        return $price_html;
    }

    /**
     * FIX PREZZO: Aggiunge il prezzo gift ai cart item data
     */
    public function add_gift_price_to_cart_data($cart_item_data, $product_id, $variation_id)
    {
        // Se è un gift, aggiungi il prezzo ai dati del cart item
        if (isset($cart_item_data['_fp_exp_item_type']) && $cart_item_data['_fp_exp_item_type'] === 'gift') {
            $gift_data = WC()->session ? WC()->session->get('fp_exp_gift_pending') : null;
            
            if (is_array($gift_data) && !empty($gift_data['total'])) {
                $cart_item_data['_fp_exp_gift_price'] = (float) $gift_data['total'];
            }
        }
        
        return $cart_item_data;
    }

    /**
     * FIX PREZZO: Setta il prezzo quando l'item viene aggiunto al cart
     */
    public function set_gift_price_on_add($cart_item, $cart_item_key)
    {
        if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift' && isset($cart_item['_fp_exp_gift_price'])) {
            $price = (float) $cart_item['_fp_exp_gift_price'];
            
            if ($price > 0 && isset($cart_item['data'])) {
                $cart_item['data']->set_price($price);
            }
        }
        
        return $cart_item;
    }

    /**
     * FIX PREZZO: Setta il prezzo quando l'item viene caricato dalla session
     */
    public function set_gift_price_from_session($cart_item, $values, $key)
    {
        if (($values['_fp_exp_item_type'] ?? '') === 'gift' && isset($values['_fp_exp_gift_price'])) {
            $price = (float) $values['_fp_exp_gift_price'];
            
            if ($price > 0 && isset($cart_item['data'])) {
                $cart_item['data']->set_price($price);
            }
        }
        
        return $cart_item;
    }

    /**
     * Imposta prezzo dinamico nel prodotto gift prima del calcolo totali
     * (Backup per sicurezza, i nuovi hook sopra dovrebbero gestirlo)
     */
    public function set_dynamic_gift_price($cart): void
    {
        if (!WC()->session) {
            return;
        }

        $gift_data = WC()->session->get('fp_exp_gift_pending');
        
        if (!is_array($gift_data) || empty($gift_data)) {
            return;
        }

        $price = (float) ($gift_data['total'] ?? 0);
        
        if ($price <= 0) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift' && isset($cart_item['data'])) {
                // SOLUZIONE A: Forza il prezzo come float
                $cart_item['data']->set_price($price);
                $cart_item['data']->set_regular_price($price);
            }
        }
    }

    /**
     * Rimuove link al prodotto gift per evitare errori nel checkout
     */
    public function remove_gift_product_link($permalink, $cart_item, $cart_item_key = '')
    {
        if (is_array($cart_item) && ($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
            return ''; // Ritorna stringa vuota per rimuovere il link
        }

        return $permalink;
    }

    /**
     * Blocca accesso diretto alla pagina prodotto gift (previene fatal error)
     */
    public function block_gift_product_page(): void
    {
        if (!is_singular('product')) {
            return;
        }

        $gift_product_id = (int) get_option('fp_exp_gift_product_id', 199);
        
        if (get_the_ID() === $gift_product_id) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    }

    /**
     * Escludi prodotto gift dalle query WooCommerce (tutte le query, non solo main)
     */
    public function exclude_gift_product_from_queries($query): void
    {
        // Escludi da TUTTE le query prodotti sul frontend (main query E secondarie come "Novità in negozio")
        if (!is_admin() && isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'product') {
            $gift_product_id = (int) get_option('fp_exp_gift_product_id', 199);
            
            if ($gift_product_id > 0) {
                $existing_excludes = (array) $query->get('post__not_in');
                $query->set('post__not_in', array_merge($existing_excludes, [$gift_product_id]));
            }
        }
    }

    /**
     * Escludi prodotto gift dalle query WooCommerce (wc_get_products, widget, etc.)
     */
    public function exclude_gift_from_wc_queries($meta_query, $query): array
    {
        if (!is_admin()) {
            $gift_product_id = (int) get_option('fp_exp_gift_product_id', 199);
            
            if ($gift_product_id > 0) {
                // Aggiungi meta query per escludere gift product
                $meta_query[] = [
                    'key' => '_fp_exp_is_gift_product',
                    'compare' => 'NOT EXISTS',
                ];
            }
        }
        
        return $meta_query;
    }

    /**
     * SOLUZIONE A: Localizza template WooCommerce personalizzati per gift vouchers
     * 
     * Questo metodo intercetta il caricamento dei template WooCommerce e,
     * se c'è un gift voucher nel cart, usa il nostro template custom
     * che evita errori causati da link al prodotto virtuale.
     */
    public function locate_gift_template(string $template, string $template_name, string $template_path): string
    {
        // Verifica se siamo nel checkout e se c'è un gift nel cart
        if ($template_name !== 'checkout/review-order.php') {
            return $template;
        }

        // Verifica se c'è un gift voucher nel cart
        if (!function_exists('WC') || !WC()->cart) {
            return $template;
        }

        $has_gift = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
                $has_gift = true;
                break;
            }
        }

        if (!$has_gift) {
            return $template;
        }

        // Carica il nostro template personalizzato
        $plugin_template = FP_EXP_PLUGIN_DIR . 'templates/woocommerce/' . $template_name;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }
}
