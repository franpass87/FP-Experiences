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

        if (! function_exists('wc_create_order')) {
            return new WP_Error('fp_exp_gift_wc', esc_html__('WooCommerce is required to purchase gift vouchers.', 'fp-experiences'));
        }

        try {
            $order = wc_create_order([
                'status' => 'pending',
            ]);
        } catch (Exception $exception) {
            return new WP_Error('fp_exp_gift_order', esc_html__('Impossibile creare l’ordine. Riprova.', 'fp-experiences'));
        }

        if (is_wp_error($order)) {
            return new WP_Error('fp_exp_gift_order', esc_html__('Impossibile creare l’ordine. Riprova.', 'fp-experiences'));
        }

        $order->set_created_via('fp-exp-gift');
        $order->set_currency($currency ?: 'EUR');
        $order->set_prices_include_tax(false);
        $order->set_shipping_total(0.0);
        $order->set_shipping_tax(0.0);

        $order->set_billing_first_name($purchaser['name']);
        $order->set_billing_last_name('');
        $order->set_billing_email($purchaser['email']);
        $order->set_billing_phone($purchaser['phone']);

        $item = new WC_Order_Item_Product();
        $item->set_product_id(0);
        $item->set_type('fp_experience_item');
        $item->set_name(sprintf(
            /* translators: %s: experience title. */
            esc_html__('Gift voucher – %s', 'fp-experiences'),
            $experience->post_title
        ));
        $item->set_quantity(1);
        $item->set_subtotal($total);
        $item->set_total($total);
        $item->add_meta_data('experience_id', $experience_id, true);
        $item->add_meta_data('experience_title', $experience->post_title, true);
        $item->add_meta_data('gift_voucher', 'yes', true);
        $item->add_meta_data('gift_quantity', $quantity, true);
        $item->add_meta_data('gift_addons', $addons_selected, true);
        $order->add_item($item);
        $order->set_total($total);
        $order->calculate_totals(false);

        $order->update_meta_data('_fp_exp_gift_purchase', [
            'experience_id' => $experience_id,
            'quantity' => $quantity,
            'value' => $total,
            'currency' => $order->get_currency(),
        ]);

        $order->save();

        $code = $this->generate_code();
        $valid_until = $this->calculate_valid_until();

        $voucher_id = wp_insert_post([
            'post_type' => VoucherCPT::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sprintf(
                /* translators: %s: experience title. */
                esc_html__('Gift voucher for %s', 'fp-experiences'),
                $experience->post_title
            ),
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($voucher_id)) {
            $order->delete(true);

            return new WP_Error('fp_exp_gift_voucher', esc_html__('Unable to persist the voucher.', 'fp-experiences'));
        }

        $voucher_id = (int) $voucher_id;

        if ($voucher_id <= 0) {
            $order->delete(true);

            return new WP_Error('fp_exp_gift_voucher', esc_html__('Unable to persist the voucher.', 'fp-experiences'));
        }

        update_post_meta($voucher_id, '_fp_exp_gift_code', $code);
        update_post_meta($voucher_id, '_fp_exp_gift_status', 'pending');
        update_post_meta($voucher_id, '_fp_exp_gift_experience_id', $experience_id);
        update_post_meta($voucher_id, '_fp_exp_gift_quantity', $quantity);
        update_post_meta($voucher_id, '_fp_exp_gift_addons', $addons_selected);
        update_post_meta($voucher_id, '_fp_exp_gift_purchaser', $purchaser);
        update_post_meta($voucher_id, '_fp_exp_gift_recipient', $recipient);
        update_post_meta($voucher_id, '_fp_exp_gift_order_id', $order->get_id());
        update_post_meta($voucher_id, '_fp_exp_gift_valid_until', $valid_until);
        update_post_meta($voucher_id, '_fp_exp_gift_value', $total);
        update_post_meta($voucher_id, '_fp_exp_gift_currency', $order->get_currency());
        update_post_meta($voucher_id, '_fp_exp_gift_delivery', $delivery);
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
            'code' => $code,
            'status' => 'pending',
            'experience_id' => $experience_id,
            'valid_until' => $valid_until,
            'value' => $total,
            'currency' => $order->get_currency(),
            'created_at' => time(),
        ]);

        $order->update_meta_data('_fp_exp_gift_voucher_ids', [$voucher_id]);
        $order->update_meta_data('_fp_exp_gift_code', $code);
        $order->save();

        $checkout_url = method_exists($order, 'get_checkout_payment_url')
            ? $order->get_checkout_payment_url(true)
            : add_query_arg('order-pay', $order->get_id(), home_url('/checkout/'));

        return [
            'order_id' => $order->get_id(),
            'voucher_id' => $voucher_id,
            'code' => $code,
            'checkout_url' => $checkout_url,
            'value' => $total,
            'currency' => $order->get_currency(),
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
        $message .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code)) . '</strong></p>';
        if ($valid_until > 0) {
            $message .= '<p>' . esc_html__('Valid until:', 'fp-experiences') . ' ' . esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)) . '</p>';
        }
        $message .= '<p><a href="' . esc_url($redeem_link) . '">' . esc_html__('Activate your gift', 'fp-experiences') . '</a></p>';

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
}
