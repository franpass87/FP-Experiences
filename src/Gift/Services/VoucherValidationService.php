<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Services;

use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use FP_Exp\Utils\Helpers;
use WP_Error;
use WP_Post;

use function esc_html__;
use function get_post;
use function is_email;

/**
 * Service for voucher validation.
 *
 * Validates voucher codes, status, expiration, and purchase data.
 */
final class VoucherValidationService
{
    private VoucherRepository $repository;

    public function __construct(?VoucherRepository $repository = null)
    {
        $this->repository = $repository ?? new VoucherRepository();
    }

    /**
     * Validate voucher code exists and is valid.
     *
     * @return WP_Post|WP_Error
     */
    public function validateCode(VoucherCode $code)
    {
        $voucher = $this->repository->findByCode($code);

        if (! $voucher) {
            return new WP_Error(
                'fp_exp_gift_not_found',
                esc_html__('Voucher not found.', 'fp-experiences')
            );
        }

        return $voucher;
    }

    /**
     * Validate voucher can be redeemed.
     *
     * @return true|WP_Error
     */
    public function validateRedemption(int $voucher_id)
    {
        $status = $this->repository->getStatus($voucher_id);

        if (! $status->canBeRedeemed()) {
            return new WP_Error(
                'fp_exp_gift_not_active',
                esc_html__('This voucher cannot be redeemed.', 'fp-experiences')
            );
        }

        if ($this->repository->isExpired($voucher_id)) {
            // Update status to expired
            $this->repository->updateStatus($voucher_id, VoucherStatus::expired());
            $this->repository->appendLog($voucher_id, 'expired');

            return new WP_Error(
                'fp_exp_gift_expired',
                esc_html__('This voucher has expired.', 'fp-experiences')
            );
        }

        return true;
    }

    /**
     * Validate purchase payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return true|WP_Error
     */
    public function validatePurchasePayload(array $payload)
    {
        // Validate gift enabled
        if (! Helpers::gift_enabled()) {
            return new WP_Error(
                'fp_exp_gift_disabled',
                esc_html__('Gift vouchers are currently disabled.', 'fp-experiences')
            );
        }

        // Validate experience
        $experience_id = absint((string) ($payload['experience_id'] ?? 0));
        $experience = get_post($experience_id);

        if (! $experience instanceof WP_Post || 'fp_experience' !== $experience->post_type) {
            return new WP_Error(
                'fp_exp_gift_experience',
                esc_html__('Experience not found.', 'fp-experiences')
            );
        }

        // Validate purchaser email
        $purchaser = $payload['purchaser'] ?? [];
        $purchaser_email = is_array($purchaser) ? ($purchaser['email'] ?? '') : '';

        if (! is_email($purchaser_email)) {
            return new WP_Error(
                'fp_exp_gift_purchaser_email',
                esc_html__('Provide the purchaser email address.', 'fp-experiences')
            );
        }

        // Validate recipient email
        $recipient = $payload['recipient'] ?? [];
        $recipient_email = is_array($recipient) ? ($recipient['email'] ?? '') : '';

        if (! is_email($recipient_email)) {
            return new WP_Error(
                'fp_exp_gift_recipient_email',
                esc_html__('Provide the recipient email address.', 'fp-experiences')
            );
        }

        return true;
    }

    /**
     * Validate status transition.
     */
    public function canTransitionTo(VoucherStatus $current, VoucherStatus $new): bool
    {
        // Cannot change final statuses
        if ($current->isFinal()) {
            return false;
        }

        // Pending can go to active or cancelled
        if ($current->isPending()) {
            return $new->isActive() || $new->isCancelled();
        }

        // Active can go to redeemed, cancelled, or expired
        if ($current->isActive()) {
            return $new->isRedeemed() || $new->isCancelled() || $new->isExpired();
        }

        return false;
    }
}















