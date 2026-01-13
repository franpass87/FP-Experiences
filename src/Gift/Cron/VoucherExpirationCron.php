<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Cron;

use FP_Exp\Gift\Email\VoucherEmailSender;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use FP_Exp\Gift\VoucherCPT;

use function absint;
use function array_map;
use function current_time;
use function get_posts;

/**
 * Cron job for expiring vouchers.
 *
 * Checks and expires vouchers that have passed their valid_until date.
 */
final class VoucherExpirationCron
{
    private VoucherRepository $repository;
    private VoucherEmailSender $email_sender;

    public function __construct(
        ?VoucherRepository $repository = null,
        ?VoucherEmailSender $email_sender = null
    ) {
        $this->repository = $repository ?? new VoucherRepository();
        $this->email_sender = $email_sender ?? new VoucherEmailSender();
    }

    /**
     * Process expiration (can be called by cron or manually).
     */
    public function process(): void
    {
        $now = current_time('timestamp', true);
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

                $this->expireVoucher($voucher_id, $now);
            }

            $page++;
        } while (count($voucher_ids) === $batch_size);
    }

    /**
     * Expire a voucher if valid_until has passed.
     */
    public function expireVoucher(int $voucher_id, ?int $now = null): void
    {
        if ($now === null) {
            $now = current_time('timestamp', true);
        }

        $valid_until = $this->repository->getValidUntil($voucher_id);
        $status = $this->repository->getStatus($voucher_id);

        // Only expire active vouchers
        if (! $status->isActive()) {
            return;
        }

        // Check if expired
        if ($valid_until > 0 && $valid_until <= $now) {
            $this->repository->updateStatus($voucher_id, VoucherStatus::expired());
            $this->repository->appendLog($voucher_id, 'expired');
            $this->email_sender->sendExpiredEmail($voucher_id);
        }
    }
}















