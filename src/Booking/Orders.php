<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use Exception;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Tax;
use WP_Error;

use function __;
use function absint;
use function add_action;
use function add_filter;
use function apply_filters;
use function array_sum;
use function do_action;
use function function_exists;
use function get_locale;
use function get_option;
use function is_array;
use function is_wp_error;
use function sanitize_email;
use function sanitize_text_field;
use function wc_create_order;
use function wc_get_order;

final class Orders implements HookableInterface
{
    private Cart $cart;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    public function register_hooks(): void
    {
        add_filter('woocommerce_data_stores', [$this, 'register_order_item_store']);
        add_filter('woocommerce_order_item_types', [$this, 'register_order_item_type']);
        add_action('woocommerce_payment_complete', [$this, 'handle_payment_complete']);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_order_cancelled']);
        add_action('woocommerce_order_status_failed', [$this, 'handle_order_failed']);
    }

    /**
     * @param array<string, string> $stores
     *
     * @return array<string, string>
     */
    public function register_order_item_store(array $stores): array
    {
        $stores['order-item-fp_experience_item'] = 'WC_Order_Item_Data_Store';

        return $stores;
    }

    /**
     * @param array<string, string> $types
     *
     * @return array<string, string>
     */
    public function register_order_item_type(array $types): array
    {
        $types['fp_experience_item'] = __('Experience item', 'fp-experiences');

        return $types;
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $payload
     *
     * @return WC_Order|WP_Error
     */
    public function create_order(array $cart, array $payload)
    {
        if (! function_exists('wc_create_order')) {
            return new WP_Error('fp_exp_missing_wc', __('WooCommerce is required to process experience checkout.', 'fp-experiences'));
        }

        try {
            $order = wc_create_order([
                'status' => 'pending',
            ]);
        } catch (Exception $exception) {
            return new WP_Error('fp_exp_order_failed', __('Impossibile creare l’ordine. Riprova.', 'fp-experiences'));
        }

        if (is_wp_error($order)) {
            return new WP_Error('fp_exp_order_failed', __('Impossibile creare l’ordine. Riprova.', 'fp-experiences'));
        }

        if (empty($cart['items'])) {
            $order->delete(true);

            return new WP_Error('fp_exp_cart_empty', __('Your experience cart is empty.', 'fp-experiences'));
        }

        $order->set_created_via('fp-exp');
        $order->set_currency($cart['currency'] ?? get_option('woocommerce_currency', 'EUR'));
        $order->set_prices_include_tax(false);
        $order->set_shipping_total(0.0);
        $order->set_shipping_tax(0.0);
        $order->update_meta_data('_fp_exp_session', $this->cart->get_session_id());
        $order->update_meta_data('_fp_exp_cart_snapshot', $cart);
        $order->update_meta_data('_fp_exp_isolated_checkout', 'yes');
        
        // FIX: Imposta un metodo di pagamento di default (bonifico bancario)
        // WooCommerce richiede un metodo di pagamento per permettere il pagamento dell'ordine
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $default_gateway = 'bacs'; // Bonifico bancario
        
        if (isset($available_gateways[$default_gateway])) {
            $order->set_payment_method($default_gateway);
        } elseif (!empty($available_gateways)) {
            // Se bonifico non disponibile, usa il primo gateway disponibile
            $first_gateway = array_key_first($available_gateways);
            $order->set_payment_method($first_gateway);
        }

        $contact = $this->normalize_contact($payload['contact'] ?? []);
        $billing = $this->normalize_billing($payload['billing'] ?? []);

        $order->set_billing_first_name($billing['first_name'] ?: $contact['first_name']);
        $order->set_billing_last_name($billing['last_name'] ?: $contact['last_name']);
        $order->set_billing_email($contact['email']);
        $order->set_billing_phone($contact['phone']);
        $order->set_billing_country($billing['country']);
        $order->set_billing_postcode($billing['postcode']);
        $order->set_billing_city($billing['city']);
        $order->set_billing_address_1($billing['address_1']);

        $line_total = 0.0;
        $tax_total = 0.0;
        $tax_class = apply_filters('fp_exp_vat_class', 'standard');
        $utm_data = Helpers::read_utm_cookie();

        foreach ($cart['items'] ?? [] as $item) {
            $normalized = $this->recalculate_item_totals($item);
            $line_item = $this->create_line_item($normalized, $tax_class);

            if ($line_item instanceof WP_Error) {
                $order->delete(true);

                return $line_item;
            }

            $line_total += (float) $line_item->get_total();
            $tax_total += array_sum($line_item->get_taxes()['total'] ?? []);

            $order->add_item($line_item);

            $reservation_result = $this->persist_reservation($order, $normalized, $utm_data);

            if ($reservation_result instanceof WP_Error) {
                return $reservation_result;
            }
        }

        $order->set_cart_tax($tax_total);
        $order->set_total($line_total + $tax_total);

        $order->update_meta_data('_fp_exp_contact', $contact);
        $order->update_meta_data('_fp_exp_billing', $billing);
        $order->update_meta_data('_fp_exp_consent_marketing', ! empty($payload['consent']['marketing']) ? 'yes' : 'no');
        if (! empty($utm_data)) {
            $order->update_meta_data('_fp_exp_utm', $utm_data);
        }

        $order->save();

        // FIX: Aggiungi l'ordine alla sessione WooCommerce per permettere il pagamento
        // WooCommerce verifica la sessione quando si accede alla pagina di pagamento
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('order_awaiting_payment', $order->get_id());
            WC()->session->save_data();
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return WC_Order_Item_Product|WP_Error
     */
    private function create_line_item(array $item, string $tax_class)
    {
        // Usa WC_Order_Item_Product invece di WC_Order_Item base
        // perché ha tutti i metodi necessari (set_name, set_total, etc.)
        $order_item = new WC_Order_Item_Product();
        $order_item->set_name($item['title'] ?? __('Experience booking', 'fp-experiences'));

        $subtotal = (float) ($item['totals']['subtotal'] ?? 0.0);
        $total = (float) ($item['totals']['total'] ?? 0.0);

        if ($total <= 0.0) {
            $total = $subtotal;
        }

        if ($total < 0) {
            $total = 0.0;
        }

        $order_item->set_subtotal($subtotal);
        $order_item->set_total($total);

        if ($tax_class) {
            $rates = WC_Tax::get_rates($tax_class);

            if (! empty($rates)) {
                $taxes = WC_Tax::calc_tax($total, $rates, false);
                $order_item->set_taxes([
                    'total' => $taxes,
                    'subtotal' => $taxes,
                ]);
            }
        }

        // Marca come experience item (non prodotto WooCommerce normale)
        $order_item->add_meta_data('_fp_exp_item_type', 'experience', true);
        $order_item->add_meta_data('experience_id', absint($item['experience_id'] ?? 0), true);
        $order_item->add_meta_data('experience_title', sanitize_text_field((string) ($item['title'] ?? '')), true);
        $order_item->add_meta_data('slot_id', absint($item['slot_id'] ?? 0), true);
        $order_item->add_meta_data('slot_start', sanitize_text_field((string) ($item['slot_start'] ?? '')), true);
        $order_item->add_meta_data('slot_end', sanitize_text_field((string) ($item['slot_end'] ?? '')), true);
        $order_item->add_meta_data('tickets', $item['tickets'] ?? [], true);
        $order_item->add_meta_data('addons', $item['addons'] ?? [], true);
        $order_item->add_meta_data('fp_exp_tax_class', $tax_class, true);

        return $order_item;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function recalculate_item_totals(array $item): array
    {
        $experience_id = absint($item['experience_id'] ?? 0);
        $slot_id = absint($item['slot_id'] ?? 0);

        if ($experience_id <= 0 || $slot_id <= 0) {
            return $item;
        }

        $tickets = is_array($item['tickets'] ?? null) ? $item['tickets'] : [];
        $addons = is_array($item['addons'] ?? null) ? $item['addons'] : [];
        $slot = Slots::get_slot($slot_id);

        if (! $slot || (int) ($slot['experience_id'] ?? 0) !== $experience_id) {
            return $item;
        }

        $breakdown = Pricing::calculate_breakdown(
            $experience_id,
            (string) ($slot['start_datetime'] ?? ''),
            $tickets,
            $addons
        );

        $item['totals'] = is_array($item['totals'] ?? null) ? $item['totals'] : [];
        $item['totals']['subtotal'] = (float) ($breakdown['subtotal'] ?? $item['totals']['subtotal'] ?? 0.0);
        $item['totals']['total'] = (float) ($breakdown['total'] ?? $item['totals']['total'] ?? 0.0);
        $item['slot_start'] = $slot['start_datetime'] ?? ($item['slot_start'] ?? '');
        $item['slot_end'] = $slot['end_datetime'] ?? ($item['slot_end'] ?? '');
        $item['tickets'] = $breakdown['tickets'] ?? $item['tickets'];
        $item['addons'] = $breakdown['addons'] ?? $item['addons'];

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $utm
     *
     * @return true|WP_Error
     */
    private function persist_reservation(WC_Order $order, array $item, array $utm = [])
    {
        $slot_id = absint($item['slot_id'] ?? 0);
        $tickets = $item['tickets'] ?? [];
        
        $reservation_id = Reservations::create([
            'order_id' => $order->get_id(),
            'experience_id' => absint($item['experience_id'] ?? 0),
            'slot_id' => $slot_id,
            'status' => Reservations::STATUS_PENDING,
            'pax' => $tickets,
            'addons' => $item['addons'] ?? [],
            'utm' => $utm,
            'locale' => get_locale(),
            'total_gross' => (float) ($item['totals']['total'] ?? 0.0),
            'tax_total' => (float) ($item['totals']['tax'] ?? 0.0),
        ]);

        if ($reservation_id <= 0) {
            Reservations::delete_by_order($order->get_id());
            $order->delete(true);

            return new WP_Error('fp_exp_reservation_failed', __('Impossibile registrare la prenotazione. Riprova.', 'fp-experiences'));
        }

        // FIX: Double-check capacity after creating reservation to prevent race condition overbooking
        // In high-concurrency scenarios, multiple requests might pass the initial capacity check
        // simultaneously. This post-creation verification catches overbooking and rolls back.
        if ($slot_id > 0 && !empty($tickets)) {
            $slot = Slots::get_slot($slot_id);
            
            if ($slot) {
                $capacity_total = absint($slot['capacity_total']);
                
                if ($capacity_total > 0) {
                    $snapshot = Slots::get_capacity_snapshot($slot_id);
                    
                    if ($snapshot['total'] > $capacity_total) {
                        // Overbooking detected! Rollback this reservation
                        Reservations::delete($reservation_id);
                        Reservations::delete_by_order($order->get_id());
                        $order->delete(true);
                        
                        return new WP_Error(
                            'fp_exp_capacity_exceeded',
                            __('Lo slot selezionato si è appena esaurito. Riprova con un altro orario.', 'fp-experiences'),
                            ['status' => 409]
                        );
                    }
                }
            }
        }

        do_action('fp_exp_reservation_created', $reservation_id, $order->get_id());

        return true;
    }

    public function handle_payment_complete(int $order_id): void
    {
        $reservation_ids = Reservations::get_ids_by_order($order_id);

        if (! $reservation_ids) {
            return;
        }

        foreach ($reservation_ids as $reservation_id) {
            if (Reservations::update_status($reservation_id, Reservations::STATUS_PAID)) {
                do_action('fp_exp_reservation_paid', $reservation_id, $order_id);
            }
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $session_id = (string) $order->get_meta('_fp_exp_session');

        if ($session_id) {
            $this->cart->purge_session($session_id);
        }
    }

    public function handle_order_cancelled(int $order_id): void
    {
        $reservation_ids = Reservations::get_ids_by_order($order_id);

        if (! $reservation_ids) {
            return;
        }

        foreach ($reservation_ids as $reservation_id) {
            if (Reservations::update_status($reservation_id, Reservations::STATUS_CANCELLED)) {
                do_action('fp_exp_reservation_cancelled', $reservation_id, $order_id);
            }
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $session_id = (string) $order->get_meta('_fp_exp_session');

        if ($session_id) {
            $this->cart->purge_session($session_id);
        }
    }

    public function handle_order_failed(int $order_id): void
    {
        // Trattiamo i failed come i cancelled per sbloccare il carrello e permettere un nuovo tentativo
        $this->handle_order_cancelled($order_id);
    }

    /**
     * @param mixed $data
     *
     * @return array<string, string>
     */
    private function normalize_contact($data): array
    {
        $data = is_array($data) ? $data : [];

        return [
            'first_name' => sanitize_text_field((string) ($data['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($data['last_name'] ?? '')),
            'email' => sanitize_email((string) ($data['email'] ?? '')),
            'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
        ];
    }

    /**
     * @param mixed $data
     *
     * @return array<string, string>
     */
    private function normalize_billing($data): array
    {
        $data = is_array($data) ? $data : [];

        return [
            'first_name' => sanitize_text_field((string) ($data['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($data['last_name'] ?? '')),
            'address_1' => sanitize_text_field((string) ($data['address'] ?? $data['address_1'] ?? '')),
            'city' => sanitize_text_field((string) ($data['city'] ?? '')),
            'postcode' => sanitize_text_field((string) ($data['postcode'] ?? '')),
            'country' => sanitize_text_field((string) ($data['country'] ?? '')),
        ];
    }
}
