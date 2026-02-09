<?php

declare(strict_types=1);

namespace FP_Exp\Gift;

use DateTimeImmutable;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Services\Options\OptionsInterface;
use DateTimeZone;
use Exception;
use FP_Exp\Booking\Pricing;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Gift\Cron\VoucherDeliveryCron;
use FP_Exp\Gift\Cron\VoucherReminderCron;
use FP_Exp\Gift\Delivery\VoucherDeliveryService;
use FP_Exp\Gift\Email\VoucherEmailSender;
use FP_Exp\Gift\Integration\WooCommerce\GiftProductManager;
use FP_Exp\Gift\Integration\WooCommerce\WooCommerceIntegration;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\Services\VoucherCreationService;
use FP_Exp\Gift\Services\VoucherRedemptionService;
use FP_Exp\Gift\Services\VoucherValidationService;
use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Utils\Helpers;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Product_Simple;
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
use function get_post_status;
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
use function update_option;
use function wp_get_scheduled_event;
use function wp_date;
use function wp_insert_post;
use function wp_mail;
use function wp_schedule_event;
use function wp_schedule_single_event;
use function wp_unschedule_event;
use function wp_timezone;
use function wc_create_order;
use function wc_get_product;
use function wc_get_order;
use function wp_kses_post;
use function explode;
use function wc_get_checkout_url;
use function wp_safe_redirect;
use function is_singular;
use function get_the_ID;
use function wp_set_object_terms;

use const DAY_IN_SECONDS;
use const HOUR_IN_SECONDS;
use const MINUTE_IN_SECONDS;

final class VoucherManager implements HookableInterface
{
    private const CRON_HOOK = 'fp_exp_gift_send_reminders';
    private const DELIVERY_CRON_HOOK = 'fp_exp_gift_send_scheduled_voucher';

    // Refactored services
    private ?VoucherCreationService $creation_service = null;
    private ?VoucherRedemptionService $redemption_service = null;
    private ?VoucherRepository $repository = null;
    private ?VoucherEmailSender $email_sender = null;
    private ?VoucherDeliveryService $delivery_service = null;
    private ?GiftProductManager $product_manager = null;
    private ?VoucherReminderCron $reminder_cron = null;
    private ?VoucherDeliveryCron $delivery_cron = null;
    private ?WooCommerceIntegration $wc_integration = null;
    private ?OptionsInterface $options = null;

    /**
     * VoucherManager constructor.
     *
     * @param OptionsInterface|null $options Optional OptionsInterface (will try to get from container if not provided)
     */
    public function __construct(?OptionsInterface $options = null)
    {
        $this->options = $options;
    }

    /**
     * Get OptionsInterface instance.
     * Tries container first, falls back to direct instantiation for backward compatibility.
     */
    private function getOptions(): OptionsInterface
    {
        if ($this->options !== null) {
            return $this->options;
        }

        // Try to get from container
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(OptionsInterface::class)) {
                try {
                    $this->options = $container->make(OptionsInterface::class);
                    return $this->options;
                } catch (\Throwable $e) {
                    // Fall through to direct instantiation
                }
            }
        }

        // Fallback to direct instantiation
        $this->options = new \FP_Exp\Services\Options\Options();
        return $this->options;
    }

    /**
     * Initialize refactored services (lazy loading).
     */
    private function initServices(): void
    {
        if ($this->repository === null) {
            $this->repository = new VoucherRepository();
            $this->creation_service = new VoucherCreationService();
            $this->redemption_service = new VoucherRedemptionService($this->repository);
            $this->email_sender = new VoucherEmailSender($this->repository);
            $this->delivery_service = new VoucherDeliveryService($this->repository);
            $this->product_manager = new GiftProductManager();
            $this->reminder_cron = new VoucherReminderCron($this->repository, $this->email_sender);
            $this->delivery_cron = new VoucherDeliveryCron($this->delivery_service);
            $this->wc_integration = new WooCommerceIntegration($this->product_manager);
        }
    }

    public function register_hooks(): void
    {
        $this->initServices();

        // Register refactored cron hooks
        $this->reminder_cron->register();
        $this->delivery_cron->register();

        // Register refactored WooCommerce integration
        $this->wc_integration->register();

        // Legacy hooks for backward compatibility
        add_action(self::DELIVERY_CRON_HOOK, [$this, 'maybe_send_scheduled_voucher']);
        
        // Keep old hooks for backward compatibility (will delegate to new services)
        add_action('init', [$this, 'maybe_schedule_cron']);
        add_action(self::CRON_HOOK, [$this, 'process_reminders']);
        add_action('woocommerce_payment_complete', [$this, 'handle_payment_complete'], 20);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_order_cancelled'], 20);
        add_action('woocommerce_order_fully_refunded', [$this, 'handle_order_cancelled'], 20);
        add_filter('woocommerce_checkout_get_value', [$this, 'prefill_checkout_fields'], 999, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'process_gift_order_after_checkout'], 10, 3);
        add_action('woocommerce_thankyou', [$this, 'process_gift_order_on_thankyou'], 5, 1);
        add_action('wp_footer', [$this, 'output_gift_checkout_script'], 999);
        add_filter('woocommerce_cart_item_name', [$this, 'customize_gift_cart_name'], 99, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'set_gift_cart_price'], 10, 3);
        // FIX: Usa il metodo condizionale invece di __return_null che colpiva TUTTI i prodotti
        add_filter('woocommerce_cart_item_permalink', [$this, 'remove_gift_product_link'], 999, 3);
        add_filter('woocommerce_order_item_permalink', [$this, 'remove_gift_order_link'], 999, 2);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_gift_price_to_cart_data'], 10, 3);
        add_filter('woocommerce_add_cart_item', [$this, 'set_gift_price_on_add'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'set_gift_price_from_session'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_dynamic_gift_price'], 10, 1);
        add_action('template_redirect', [$this, 'block_gift_product_page']);
        add_action('pre_get_posts', [$this, 'exclude_gift_product_from_queries']);
        add_filter('woocommerce_product_query_meta_query', [$this, 'exclude_gift_from_wc_queries'], 10, 2);
        add_filter('woocommerce_locate_template', [$this, 'locate_gift_template'], 10, 3);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_gift_coupon'], 10, 3);
        add_filter('woocommerce_coupon_error', [$this, 'custom_gift_coupon_error'], 10, 3);
    }

    /**
     * Schedule cron (delegated to VoucherReminderCron).
     *
     * @deprecated Use VoucherReminderCron::maybeSchedule() instead
     */
    public function maybe_schedule_cron(): void
    {
        $this->initServices();
        $this->reminder_cron->maybeSchedule();
    }

    /**
     * Clear cron (delegated to VoucherReminderCron).
     *
     * @deprecated Use VoucherReminderCron::clear() instead
     */
    public function clear_cron(): void
    {
        $this->initServices();
        $this->reminder_cron->clear();
    }

    /**
     * Create a purchase (prepare voucher for checkout).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    public function create_purchase(array $payload)
    {
        $this->initServices();

        // Use creation service
        $result = $this->creation_service->createPurchase($payload);

        if (is_wp_error($result)) {
            return $result;
        }

        // Get gift data
        $gift_data = $result['gift_data'] ?? [];
        $prefill_data = $result['prefill_data'] ?? [];

        if (empty($gift_data)) {
            return new WP_Error(
                'fp_exp_gift_data',
                esc_html__('Unable to prepare gift voucher data.', 'fp-experiences')
            );
        }

        // Save to session
        if (WC()->session) {
            WC()->session->set('fp_exp_gift_pending', $gift_data);
            WC()->session->set('fp_exp_gift_prefill', $prefill_data);

            // Also save to transient
            $session_id = WC()->session->get_customer_id();

            if ($session_id) {
                $transient_key = 'fp_exp_gift_' . $session_id;
                set_transient($transient_key, [
                    'pending' => $gift_data,
                    'prefill' => $prefill_data,
                ], HOUR_IN_SECONDS);
            }
        }

        // Ensure gift product exists
        $gift_product_id = $this->product_manager->ensureGiftProduct();

        if ($gift_product_id <= 0) {
            return new WP_Error(
                'fp_exp_gift_product_missing',
                esc_html__('Non è stato possibile preparare il prodotto WooCommerce per i voucher regalo.', 'fp-experiences'),
                ['status' => 500]
            );
        }

        // Load cart
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (! WC()->cart) {
            return new WP_Error(
                'fp_exp_gift_cart_unavailable',
                esc_html__('Il carrello WooCommerce non è disponibile. Ricarica la pagina e riprova.', 'fp-experiences'),
                ['status' => 500]
            );
        }

        // Empty and add gift product
        WC()->cart->empty_cart();

        $cart_item_data = [
            '_fp_exp_item_type' => 'gift',
            'gift_voucher' => 'yes',
            'experience_id' => $gift_data['experience_id'] ?? 0,
            'experience_title' => $gift_data['experience_title'] ?? '',
            'gift_quantity' => $gift_data['quantity'] ?? 1,
            '_fp_exp_gift_price' => (float) ($gift_data['total'] ?? 0),
            '_fp_exp_gift_full_data' => $gift_data,
            '_fp_exp_gift_prefill_data' => $prefill_data,
        ];

        $cart_item_key = WC()->cart->add_to_cart($gift_product_id, 1, 0, [], $cart_item_data);

        if (! $cart_item_key) {
            return new WP_Error(
                'fp_exp_gift_cart_add',
                esc_html__('Non è stato possibile aggiungere il voucher regalo al carrello. Riprova più tardi.', 'fp-experiences'),
                ['status' => 500]
            );
        }

        WC()->cart->calculate_totals();

        // Return checkout URL
        $checkout_url = function_exists('wc_get_checkout_url')
            ? wc_get_checkout_url()
            : home_url('/checkout/');

        return [
            'checkout_url' => $checkout_url,
            'value' => $gift_data['total'] ?? 0,
            'currency' => $gift_data['currency'] ?? 'EUR',
            'experience_title' => $gift_data['experience_title'] ?? '',
            'code' => $gift_data['code'] ?? '',
        ];
    }

    /**
     * Ensure gift product exists (delegated to GiftProductManager).
     *
     * @deprecated Use GiftProductManager::ensureGiftProduct() instead
     */
    private function ensure_gift_product_id(): int
    {
        $this->initServices();

        return $this->product_manager->ensureGiftProduct();
    }

    private function prepare_gift_product(int $product_id): bool
    {
        $product = wc_get_product($product_id);

        if (! $product) {
            return false;
        }

        $status = get_post_status($product_id);
        if ('trash' === $status) {
            return false;
        }

        if (! $product->is_type('simple') && class_exists(WC_Product_Simple::class)) {
            $product = new WC_Product_Simple($product_id);
        }

        if (! $product instanceof WC_Product) {
            return false;
        }

        $name = $product->get_name();
        if (! $name) {
            $name = esc_html__('Voucher regalo FP Experiences', 'fp-experiences');
        }

        $product->set_name($name);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        $product->set_sold_individually(true);
        $product->set_price(0);
        $product->set_regular_price(0);

        try {
            $product->save();
        } catch (Exception $exception) {
            error_log('FP Experiences Gift: failed saving gift product #' . $product_id . ' - ' . $exception->getMessage());

            return false;
        }

        wp_set_object_terms($product_id, 'simple', 'product_type', false);
        wp_set_object_terms($product_id, ['exclude-from-catalog', 'exclude-from-search'], 'product_visibility', false);

        update_post_meta($product_id, '_fp_exp_is_gift_product', 'yes');
        $this->getOptions()->set('fp_exp_gift_product_id', $product_id);

        return true;
    }

    private function create_gift_product_post(): int
    {
        $product_id = wp_insert_post([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => esc_html__('Voucher regalo FP Experiences', 'fp-experiences'),
            'post_content' => esc_html__('Voucher digitale utilizzato dal plugin FP Experiences. Non eliminare.', 'fp-experiences'),
            'meta_input' => [
                '_fp_exp_is_gift_product' => 'yes',
            ],
        ]);

        if (is_wp_error($product_id)) {
            error_log('FP Experiences Gift: failed creating gift product - ' . $product_id->get_error_message());

            return 0;
        }

        if (! $product_id) {
            return 0;
        }

        wp_set_object_terms($product_id, 'simple', 'product_type', false);

        return (int) $product_id;
    }

    /**
     * Handle payment complete (delegated to GiftOrderHandler).
     *
     * @deprecated Use GiftOrderHandler::handlePaymentComplete() instead
     */
    public function handle_payment_complete(int $order_id): void
    {
        $this->initServices();
        $this->wc_integration->getOrderHandler()->handlePaymentComplete($order_id);
    }

    /**
     * Handle order cancelled (delegated to GiftOrderHandler).
     *
     * @deprecated Use GiftOrderHandler::handleOrderCancelled() instead
     */
    public function handle_order_cancelled(int $order_id): void
    {
        $this->initServices();
        $this->wc_integration->getOrderHandler()->handleOrderCancelled($order_id);
    }

    /**
     * Send scheduled voucher (delegated to VoucherDeliveryService).
     *
     * @deprecated Use VoucherDeliveryService::processScheduledDelivery() instead
     *
     * @param int $voucher_id
     */
    public function maybe_send_scheduled_voucher($voucher_id): void
    {
        $this->initServices();
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $this->delivery_service->processScheduledDelivery($voucher_id);
    }

    /**
     * Process reminders (delegated to VoucherReminderCron).
     *
     * @deprecated Use VoucherReminderCron::process() instead
     */
    public function process_reminders(): void
    {
        $this->initServices();
        $this->reminder_cron->process();
    }

    /**
     * Get voucher by code.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_voucher_by_code(string $code)
    {
        $this->initServices();

        $code = sanitize_key($code);

        if ('' === $code) {
            return new WP_Error(
                'fp_exp_gift_code',
                esc_html__('Voucher code not provided.', 'fp-experiences')
            );
        }

        try {
            $voucher_code = VoucherCode::fromString($code);
        } catch (\InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_exp_gift_code',
                esc_html__('Invalid voucher code format.', 'fp-experiences')
            );
        }

        $voucher = $this->repository->findByCode($voucher_code);

        if (! $voucher) {
            return new WP_Error(
                'fp_exp_gift_not_found',
                esc_html__('Voucher not found.', 'fp-experiences')
            );
        }

        return $this->creation_service->buildVoucherPayload($voucher);
    }

    /**
     * Redeem a voucher.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    public function redeem_voucher(string $code, array $payload)
    {
        $this->initServices();

        $code = sanitize_key($code);

        if ('' === $code) {
            return new WP_Error(
                'fp_exp_gift_code',
                esc_html__('Voucher code not provided.', 'fp-experiences')
            );
        }

        try {
            $voucher_code = VoucherCode::fromString($code);
        } catch (\InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_exp_gift_code',
                esc_html__('Invalid voucher code format.', 'fp-experiences')
            );
        }

        return $this->redemption_service->redeem($voucher_code, $payload);
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
     * Output checkout script (delegated to GiftCheckoutHandler).
     *
     * @deprecated Use GiftCheckoutHandler::outputCheckoutScript() instead
     */
    public function output_gift_checkout_script(): void
    {
        $this->initServices();
        $this->wc_integration->getCheckoutHandler()->outputCheckoutScript();
    }

    /**
     * Prefill checkout fields (delegated to GiftCheckoutHandler).
     *
     * @deprecated Use GiftCheckoutHandler::prefillCheckoutFields() instead
     */
    public function prefill_checkout_fields($value, string $input)
    {
        $this->initServices();

        return $this->wc_integration->getCheckoutHandler()->prefillCheckoutFields($value, $input);
    }

    /**
     * Process gift order after checkout (delegated to GiftCheckoutHandler).
     *
     * @deprecated Use GiftCheckoutHandler::processCheckout() instead
     */
    public function process_gift_order_after_checkout(int $order_id, $posted_data, $order): void
    {
        $this->initServices();
        $this->wc_integration->getCheckoutHandler()->processCheckout($order_id, $posted_data, $order);
    }

    /**
     * Process gift order on thankyou (delegated to GiftCheckoutHandler).
     *
     * @deprecated Use GiftCheckoutHandler::processThankYou() instead
     */
    public function process_gift_order_on_thankyou(int $order_id): void
    {
        $this->initServices();
        $this->wc_integration->getCheckoutHandler()->processThankYou($order_id);
    }

    /**
     * Create gift voucher post (delegated to GiftOrderHandler).
     *
     * @deprecated Use GiftOrderHandler::createVoucherPost() instead
     */
    private function create_gift_voucher_post(int $order_id, array $gift_data): ?int
    {
        $this->initServices();

        return $this->wc_integration->getOrderHandler()->createVoucherPost($order_id, $gift_data);
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
     * Invalidate gift coupon (delegated to GiftCouponManager).
     *
     * @deprecated Use GiftCouponManager::invalidateCoupon() instead
     */
    private function invalidate_gift_coupon(int $voucher_id): void
    {
        $this->initServices();
        $this->wc_integration->getCouponManager()->invalidateCoupon($voucher_id);
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
     * Customize cart name (delegated to GiftCartHandler).
     *
     * @deprecated Use GiftCartHandler::customizeCartName() instead
     */
    public function customize_gift_cart_name(string $name, array $cart_item, string $cart_item_key): string
    {
        $this->initServices();

        return $this->wc_integration->getCartHandler()->customizeCartName($name, $cart_item, $cart_item_key);
    }

    /**
     * Set cart price (delegated to GiftCartHandler).
     *
     * @deprecated Use GiftCartHandler::setCartPrice() instead
     */
    public function set_gift_cart_price(string $price_html, array $cart_item, string $cart_item_key): string
    {
        $this->initServices();

        return $this->wc_integration->getCartHandler()->setCartPrice($price_html, $cart_item, $cart_item_key);
    }

    /**
     * Add gift price to cart data (delegated to GiftCartHandler).
     *
     * @deprecated Use GiftCartHandler::addGiftPriceToCartData() instead
     */
    public function add_gift_price_to_cart_data($cart_item_data, $product_id, $variation_id)
    {
        $this->initServices();

        return $this->wc_integration->getCartHandler()->addGiftPriceToCartData($cart_item_data, $product_id, $variation_id);
    }

    /**
     * Set gift price on add (delegated to GiftCartHandler).
     *
     * @deprecated Use GiftCartHandler::setGiftPriceOnAdd() instead
     */
    public function set_gift_price_on_add($cart_item, $cart_item_key)
    {
        $this->initServices();

        return $this->wc_integration->getCartHandler()->setGiftPriceOnAdd($cart_item, $cart_item_key);
    }

    /**
     * Set gift price from session (delegated to GiftCartHandler).
     *
     * @deprecated Use GiftCartHandler::setGiftPriceFromSession() instead
     */
    public function set_gift_price_from_session($cart_item, $values, $key)
    {
        $this->initServices();

        return $this->wc_integration->getCartHandler()->setGiftPriceFromSession($cart_item, $values, $key);
    }

    /**
     * Set dynamic gift price (delegated to GiftCartHandler).
     *
     * @deprecated Use GiftCartHandler::setDynamicGiftPrice() instead
     */
    public function set_dynamic_gift_price($cart): void
    {
        $this->initServices();
        $this->wc_integration->getCartHandler()->setDynamicGiftPrice($cart);
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
     * Rimuove link al prodotto gift negli ordini
     */
    public function remove_gift_order_link($permalink, $item)
    {
        $item_type = '';
        if (is_object($item) && method_exists($item, 'get_meta')) {
            $item_type = $item->get_meta('_fp_exp_item_type');
        }

        if ($item_type === 'gift') {
            return '';
        }

        return $permalink;
    }

    /**
     * Block gift product page (delegated to WooCommerceIntegration).
     *
     * @deprecated Use WooCommerceIntegration::blockGiftProductPage() instead
     */
    public function block_gift_product_page(): void
    {
        $this->initServices();
        $this->wc_integration->blockGiftProductPage();
    }

    /**
     * Exclude gift product from queries (delegated to WooCommerceIntegration).
     *
     * @deprecated Use WooCommerceIntegration::excludeGiftProductFromQueries() instead
     */
    public function exclude_gift_product_from_queries($query): void
    {
        $this->initServices();
        $this->wc_integration->excludeGiftProductFromQueries($query);
    }

    /**
     * Exclude gift from WC queries (delegated to WooCommerceIntegration).
     *
     * @deprecated Use WooCommerceIntegration::excludeGiftFromWcQueries() instead
     */
    public function exclude_gift_from_wc_queries($meta_query, $query): array
    {
        $this->initServices();

        return $this->wc_integration->excludeGiftFromWcQueries($meta_query, $query);
    }

    /**
     * Locate gift template (delegated to WooCommerceIntegration).
     *
     * @deprecated Use WooCommerceIntegration::locateGiftTemplate() instead
     */
    public function locate_gift_template(string $template, string $template_name, string $template_path): string
    {
        $this->initServices();

        return $this->wc_integration->locateGiftTemplate($template, $template_name, $template_path);
    }
}
