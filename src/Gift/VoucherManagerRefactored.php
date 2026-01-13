<?php

declare(strict_types=1);

namespace FP_Exp\Gift;

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
use WP_Error;

use function absint;
use function esc_html__;
use function function_exists;
use function home_url;
use function sanitize_key;
use function wc_get_checkout_url;
use function wc_load_cart;

/**
 * Voucher Manager - Refactored Version.
 *
 * Orchestrates all gift voucher operations using specialized services.
 * Maintains backward compatibility with existing code.
 *
 * @deprecated This class is being refactored. New code should use the individual services directly.
 */
final class VoucherManager
{
    // Services
    private VoucherCreationService $creation_service;
    private VoucherRedemptionService $redemption_service;
    private VoucherValidationService $validation_service;
    private VoucherRepository $repository;
    private VoucherEmailSender $email_sender;
    private VoucherDeliveryService $delivery_service;
    private GiftProductManager $product_manager;

    // Cron
    private VoucherReminderCron $reminder_cron;
    private VoucherDeliveryCron $delivery_cron;

    // WooCommerce Integration
    private WooCommerceIntegration $wc_integration;

    /**
     * Legacy cron hooks (for backward compatibility).
     */
    private const CRON_HOOK = 'fp_exp_gift_send_reminders';
    private const DELIVERY_CRON_HOOK = 'fp_exp_gift_send_scheduled_voucher';

    public function __construct()
    {
        // Initialize repository first (used by others)
        $this->repository = new VoucherRepository();

        // Initialize services
        $this->creation_service = new VoucherCreationService();
        $this->redemption_service = new VoucherRedemptionService($this->repository);
        $this->validation_service = new VoucherValidationService($this->repository);
        $this->email_sender = new VoucherEmailSender($this->repository);
        $this->delivery_service = new VoucherDeliveryService($this->repository);
        $this->product_manager = new GiftProductManager();

        // Initialize cron
        $this->reminder_cron = new VoucherReminderCron($this->repository, $this->email_sender);
        $this->delivery_cron = new VoucherDeliveryCron($this->delivery_service);

        // Initialize WooCommerce integration
        $this->wc_integration = new WooCommerceIntegration(
            $this->product_manager
        );
    }

    /**
     * Register all hooks.
     */
    public function register_hooks(): void
    {
        // Register cron hooks
        $this->reminder_cron->register();
        $this->delivery_cron->register();

        // Register WooCommerce hooks
        $this->wc_integration->register();

        // Legacy hooks for backward compatibility
        add_action(self::DELIVERY_CRON_HOOK, [$this, 'maybe_send_scheduled_voucher']);
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
     * Get voucher by code.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_voucher_by_code(string $code)
    {
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

    // ============================================
    // DEPRECATED METHODS - Backward Compatibility
    // ============================================

    /**
     * Schedule cron (delegated to VoucherReminderCron).
     *
     * @deprecated Use VoucherReminderCron::maybeSchedule() instead
     */
    public function maybe_schedule_cron(): void
    {
        $this->reminder_cron->maybeSchedule();
    }

    /**
     * Clear cron (delegated to VoucherReminderCron).
     *
     * @deprecated Use VoucherReminderCron::clear() instead
     */
    public function clear_cron(): void
    {
        $this->reminder_cron->clear();
    }

    /**
     * Process reminders (delegated to VoucherReminderCron).
     *
     * @deprecated Use VoucherReminderCron::process() instead
     */
    public function process_reminders(): void
    {
        $this->reminder_cron->process();
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
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $this->delivery_service->processScheduledDelivery($voucher_id);
    }

    /**
     * Handle payment complete (delegated to GiftOrderHandler).
     *
     * @deprecated Use GiftOrderHandler::handlePaymentComplete() instead
     */
    public function handle_payment_complete(int $order_id): void
    {
        $this->wc_integration->getOrderHandler()->handlePaymentComplete($order_id);
    }

    /**
     * Handle order cancelled (delegated to GiftOrderHandler).
     *
     * @deprecated Use GiftOrderHandler::handleOrderCancelled() instead
     */
    public function handle_order_cancelled(int $order_id): void
    {
        $this->wc_integration->getOrderHandler()->handleOrderCancelled($order_id);
    }

    // Additional deprecated methods would be added here...
    // For now, keeping the most critical ones for backward compatibility
}















