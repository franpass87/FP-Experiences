<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Cron;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Gift\Delivery\VoucherDeliveryService;

/**
 * Cron job for processing scheduled voucher deliveries.
 *
 * Delegates to VoucherDeliveryService.
 */
final class VoucherDeliveryCron implements HookableInterface
{
    public const CRON_HOOK = 'fp_exp_gift_send_scheduled_voucher';

    private VoucherDeliveryService $delivery_service;

    public function __construct(?VoucherDeliveryService $delivery_service = null)
    {
        $this->delivery_service = $delivery_service ?? new VoucherDeliveryService();
    }

    public function register_hooks(): void
    {
        $this->register();
    }

    /**
     * Register cron hooks.
     */
    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'process']);
    }

    /**
     * Process scheduled delivery (called by cron).
     *
     * @param int $voucher_id
     */
    public function process(int $voucher_id): void
    {
        $this->delivery_service->processScheduledDelivery($voucher_id);
    }
}















