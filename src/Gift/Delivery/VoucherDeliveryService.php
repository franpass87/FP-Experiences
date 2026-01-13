<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Delivery;

use FP_Exp\Gift\Delivery\Strategies\ImmediateDeliveryStrategy;
use FP_Exp\Gift\Delivery\Strategies\ScheduledDeliveryStrategy;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\DeliverySchedule;

use function current_time;
use function wp_get_scheduled_event;
use function wp_schedule_single_event;
use function wp_unschedule_event;

use const MINUTE_IN_SECONDS;

/**
 * Service for managing voucher delivery.
 *
 * Handles both immediate and scheduled delivery using Strategy pattern.
 */
final class VoucherDeliveryService
{
    private const DELIVERY_CRON_HOOK = 'fp_exp_gift_send_scheduled_voucher';

    private VoucherRepository $repository;
    private ImmediateDeliveryStrategy $immediate_strategy;
    private ScheduledDeliveryStrategy $scheduled_strategy;

    public function __construct(
        ?VoucherRepository $repository = null,
        ?ImmediateDeliveryStrategy $immediate_strategy = null,
        ?ScheduledDeliveryStrategy $scheduled_strategy = null
    ) {
        $this->repository = $repository ?? new VoucherRepository();
        $this->immediate_strategy = $immediate_strategy ?? new ImmediateDeliveryStrategy();
        $this->scheduled_strategy = $scheduled_strategy ?? new ScheduledDeliveryStrategy();
    }

    /**
     * Deliver voucher using appropriate strategy.
     */
    public function deliver(int $voucher_id): void
    {
        $strategy = $this->getStrategy($voucher_id);
        $strategy->deliver($voucher_id);
    }

    /**
     * Schedule delivery for a specific timestamp.
     */
    public function scheduleDelivery(int $voucher_id, int $send_at): void
    {
        if ($voucher_id <= 0 || $send_at <= 0) {
            return;
        }

        // Clear existing schedule
        $this->clearSchedule($voucher_id);

        // Schedule new delivery
        wp_schedule_single_event($send_at, self::DELIVERY_CRON_HOOK, [$voucher_id]);

        // Update delivery metadata
        $delivery = $this->repository->getDelivery($voucher_id);
        $delivery['send_at'] = $send_at;
        $delivery['scheduled_at'] = $send_at;
        $this->repository->updateDelivery($voucher_id, $delivery);
    }

    /**
     * Clear delivery schedule.
     */
    public function clearSchedule(int $voucher_id): void
    {
        if ($voucher_id <= 0) {
            return;
        }

        $existing = wp_get_scheduled_event(self::DELIVERY_CRON_HOOK, [$voucher_id]);

        if ($existing) {
            wp_unschedule_event($existing->timestamp, self::DELIVERY_CRON_HOOK, [$voucher_id]);
        }
    }

    /**
     * Check if voucher should be delivered now.
     */
    public function shouldDeliver(int $voucher_id): bool
    {
        $strategy = $this->getStrategy($voucher_id);

        return $strategy->shouldDeliver($voucher_id);
    }

    /**
     * Get appropriate delivery strategy for voucher.
     */
    private function getStrategy(int $voucher_id): DeliveryStrategyInterface
    {
        $delivery = $this->repository->getDelivery($voucher_id);
        $send_at = (int) ($delivery['send_at'] ?? 0);
        $now = current_time('timestamp', true);

        // If send_at is in the future, use scheduled strategy
        if ($send_at > ($now + MINUTE_IN_SECONDS)) {
            return $this->scheduled_strategy;
        }

        // Otherwise, use immediate strategy
        return $this->immediate_strategy;
    }

    /**
     * Process scheduled delivery (called by cron).
     */
    public function processScheduledDelivery(int $voucher_id): void
    {
        if ($voucher_id <= 0) {
            return;
        }

        $status = $this->repository->getStatus($voucher_id);

        if (! $status->isActive()) {
            $this->clearSchedule($voucher_id);

            return;
        }

        $delivery = $this->repository->getDelivery($voucher_id);
        $sent_at = (int) ($delivery['sent_at'] ?? 0);

        // Already sent
        if ($sent_at > 0) {
            $this->clearSchedule($voucher_id);

            return;
        }

        $send_at = (int) ($delivery['send_at'] ?? 0);
        $now = current_time('timestamp', true);

        // Not yet time
        if ($send_at > ($now + MINUTE_IN_SECONDS)) {
            $this->scheduleDelivery($voucher_id, $send_at);

            return;
        }

        // Time to deliver
        $this->deliver($voucher_id);
    }
}















