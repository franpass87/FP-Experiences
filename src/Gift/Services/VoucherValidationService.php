<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Services;

use FP_Exp\Booking\Pricing;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use FP_Exp\Utils\Helpers;
use WP_Error;
use WP_Post;

use function esc_html__;
use function absint;
use function get_post;
use function is_array;
use function is_email;
use function sanitize_key;

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

        if (! Helpers::gift_enabled_for_experience($experience_id)) {
            return new WP_Error(
                'fp_exp_gift_single_date_disabled',
                esc_html__('Il regalo esperienza non è disponibile per questo evento a data singola.', 'fp-experiences')
            );
        }

        $ticket_sales_block = Helpers::single_event_ticket_sales_blocked_error($experience_id);
        if ($ticket_sales_block instanceof WP_Error) {
            return $ticket_sales_block;
        }

        $ticket_types = Pricing::get_ticket_types($experience_id);
        $ticket_quantities = is_array($payload['ticket_quantities'] ?? null) ? $payload['ticket_quantities'] : [];
        $ticket_slug = sanitize_key((string) ($payload['ticket_slug'] ?? ''));

        // Validate per-ticket quantities when provided.
        $has_selected_ticket_quantity = false;
        if (! empty($ticket_quantities)) {
            foreach ($ticket_quantities as $slug => $qty) {
                $slug_key = sanitize_key((string) $slug);
                $qty_value = absint((string) $qty);
                if ('' === $slug_key || ! isset($ticket_types[$slug_key])) {
                    return new WP_Error(
                        'fp_exp_gift_ticket_invalid',
                        esc_html__('The selected ticket type is not valid for this experience.', 'fp-experiences')
                    );
                }
                if ($qty_value > 0) {
                    $has_selected_ticket_quantity = true;
                }
            }
            if (! $has_selected_ticket_quantity) {
                return new WP_Error(
                    'fp_exp_gift_ticket_required',
                    esc_html__('Select at least one ticket quantity for the gift voucher.', 'fp-experiences')
                );
            }
        } else {
            // Backward-compatible validation with single ticket selection.
            if (count($ticket_types) > 1 && '' === $ticket_slug) {
                return new WP_Error(
                    'fp_exp_gift_ticket_required',
                    esc_html__('Select a ticket type for the gift voucher.', 'fp-experiences')
                );
            }

            if ('' !== $ticket_slug && ! isset($ticket_types[$ticket_slug])) {
                return new WP_Error(
                    'fp_exp_gift_ticket_invalid',
                    esc_html__('The selected ticket type is not valid for this experience.', 'fp-experiences')
                );
            }
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















