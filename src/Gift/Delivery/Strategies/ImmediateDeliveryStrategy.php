<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Delivery\Strategies;

use FP_Exp\Gift\Delivery\DeliveryStrategyInterface;
use FP_Exp\Gift\Email\VoucherEmailSender;

/**
 * Strategy for immediate voucher delivery.
 *
 * Delivers voucher as soon as it becomes active.
 */
final class ImmediateDeliveryStrategy implements DeliveryStrategyInterface
{
    private VoucherEmailSender $email_sender;

    public function __construct(?VoucherEmailSender $email_sender = null)
    {
        $this->email_sender = $email_sender ?? new VoucherEmailSender();
    }

    /**
     * Deliver the voucher immediately.
     */
    public function deliver(int $voucher_id): void
    {
        $this->email_sender->sendVoucherEmail($voucher_id);
    }

    /**
     * Check if voucher should be delivered (always true for immediate).
     */
    public function shouldDeliver(int $voucher_id): bool
    {
        return true;
    }

    /**
     * Get strategy name.
     */
    public function getName(): string
    {
        return 'immediate';
    }
}















