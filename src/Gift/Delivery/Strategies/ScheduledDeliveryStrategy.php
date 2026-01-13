<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Delivery\Strategies;

use FP_Exp\Gift\Delivery\DeliveryStrategyInterface;
use FP_Exp\Gift\Email\VoucherEmailSender;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\DeliverySchedule;

use function current_time;

use const MINUTE_IN_SECONDS;

/**
 * Strategy for scheduled voucher delivery.
 *
 * Delivers voucher at a specific date/time.
 */
final class ScheduledDeliveryStrategy implements DeliveryStrategyInterface
{
    private VoucherEmailSender $email_sender;
    private VoucherRepository $repository;

    public function __construct(
        ?VoucherEmailSender $email_sender = null,
        ?VoucherRepository $repository = null
    ) {
        $this->email_sender = $email_sender ?? new VoucherEmailSender();
        $this->repository = $repository ?? new VoucherRepository();
    }

    /**
     * Deliver the voucher if scheduled time has arrived.
     */
    public function deliver(int $voucher_id): void
    {
        if (! $this->shouldDeliver($voucher_id)) {
            return;
        }

        $this->email_sender->sendVoucherEmail($voucher_id);
    }

    /**
     * Check if voucher should be delivered (scheduled time has passed).
     */
    public function shouldDeliver(int $voucher_id): bool
    {
        $delivery = $this->repository->getDelivery($voucher_id);
        $send_at = (int) ($delivery['send_at'] ?? 0);

        if ($send_at <= 0) {
            return false;
        }

        $now = current_time('timestamp', true);

        // Deliver if scheduled time has passed (within 1 minute tolerance)
        return $send_at <= ($now + MINUTE_IN_SECONDS);
    }

    /**
     * Get strategy name.
     */
    public function getName(): string
    {
        return 'scheduled';
    }
}















