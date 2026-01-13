<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Services;

use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\DeliverySchedule;
use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Utils\Helpers;
use WP_Error;
use WP_Post;

use function absint;
use function current_time;
use function esc_html__;
use function get_locale;
use function get_option;
use function get_post;
use function get_permalink;
use function get_the_excerpt;
use function get_the_post_thumbnail_url;
use function home_url;
use function is_array;
use function is_email;
use function sanitize_email;
use function sanitize_textarea_field;
use function sanitize_text_field;
use function wc_get_checkout_url;

use const DAY_IN_SECONDS;

/**
 * Service for creating gift vouchers.
 *
 * Handles the purchase flow and voucher creation logic.
 */
final class VoucherCreationService
{
    private VoucherPricingService $pricing_service;
    private VoucherValidationService $validation_service;
    private VoucherRepository $repository;

    public function __construct(
        ?VoucherPricingService $pricing_service = null,
        ?VoucherValidationService $validation_service = null,
        ?VoucherRepository $repository = null
    ) {
        $this->pricing_service = $pricing_service ?? new VoucherPricingService();
        $this->validation_service = $validation_service ?? new VoucherValidationService();
        $this->repository = $repository ?? new VoucherRepository();
    }

    /**
     * Create a purchase (prepare voucher data for checkout).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    public function createPurchase(array $payload)
    {
        // Validate payload
        $validation = $this->validation_service->validatePurchasePayload($payload);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $experience_id = absint((string) ($payload['experience_id'] ?? 0));
        $quantity = absint((string) ($payload['quantity'] ?? 1));

        if ($quantity <= 0) {
            $quantity = 1;
        }

        $addons_requested = is_array($payload['addons'] ?? null) ? $payload['addons'] : [];

        // Calculate pricing
        $pricing_result = $this->pricing_service->calculateTotal($experience_id, $quantity, $addons_requested);

        if (is_wp_error($pricing_result)) {
            return $pricing_result;
        }

        $total = $pricing_result['total'];
        $addons_selected = $pricing_result['addons_selected'];

        // Sanitize contacts
        $purchaser = $this->sanitizeContact($payload['purchaser'] ?? []);
        $recipient = $this->sanitizeContact($payload['recipient'] ?? []);

        if (! is_email($purchaser['email'])) {
            return new WP_Error(
                'fp_exp_gift_purchaser_email',
                esc_html__('Provide the purchaser email address.', 'fp-experiences')
            );
        }

        if (! is_email($recipient['email'])) {
            return new WP_Error(
                'fp_exp_gift_recipient_email',
                esc_html__('Provide the recipient email address.', 'fp-experiences')
            );
        }

        $recipient['message'] = isset($payload['message'])
            ? sanitize_textarea_field((string) $payload['message'])
            : '';

        // Normalize delivery
        $delivery_data = $this->normalizeDelivery($payload['delivery'] ?? []);

        // Generate code and calculate validity
        $code = VoucherCode::generate();
        $valid_until = $this->calculateValidUntil();

        $currency = get_option('woocommerce_currency', 'EUR');
        $experience = get_post($experience_id);

        if (! $experience instanceof WP_Post) {
            return new WP_Error(
                'fp_exp_gift_experience',
                esc_html__('Experience not found.', 'fp-experiences')
            );
        }

        // Build gift data
        $gift_data = [
            'experience_id' => $experience_id,
            'experience_title' => $experience->post_title,
            'quantity' => $quantity,
            'addons' => $addons_selected,
            'purchaser' => $purchaser,
            'recipient' => $recipient,
            'delivery' => $delivery_data,
            'total' => $total,
            'currency' => $currency,
            'code' => $code->toString(),
            'valid_until' => $valid_until,
        ];

        // Build prefill data for checkout
        $prefill_data = [
            'billing_first_name' => $purchaser['name'],
            'billing_email' => $purchaser['email'],
            'billing_phone' => $purchaser['phone'],
        ];

        return [
            'gift_data' => $gift_data,
            'prefill_data' => $prefill_data,
            'checkout_url' => $this->getCheckoutUrl(),
            'value' => $total,
            'currency' => $currency,
            'experience_title' => $experience->post_title,
            'code' => $code->toString(),
        ];
    }

    /**
     * Build voucher payload for API response.
     *
     * @return array<string, mixed>
     */
    public function buildVoucherPayload(WP_Post $voucher): array
    {
        $voucher_id = $voucher->ID;
        $experience_id = $this->repository->getExperienceId($voucher_id);
        $experience = get_post($experience_id);
        $valid_until = $this->repository->getValidUntil($voucher_id);
        $code = $this->repository->getCode($voucher_id);
        $status = $this->repository->getStatus($voucher_id);

        $slots = $this->loadUpcomingSlots($experience_id);
        $addons_quantities = $this->repository->getAddons($voucher_id);

        return [
            'voucher_id' => $voucher_id,
            'code' => $code ? $code->toString() : '',
            'status' => $status->toString(),
            'valid_until' => $valid_until,
            'valid_until_label' => $valid_until > 0
                ? date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)
                : '',
            'quantity' => $this->repository->getQuantity($voucher_id),
            'addons' => $this->normalizeVoucherAddons($experience_id, $addons_quantities),
            'experience' => $this->buildExperiencePayload($experience),
            'slots' => $slots,
            'value' => $this->repository->getValue($voucher_id),
            'currency' => $this->repository->getCurrency($voucher_id),
        ];
    }

    /**
     * Calculate valid until timestamp.
     */
    private function calculateValidUntil(): int
    {
        $days = Helpers::gift_validity_days();
        $now = current_time('timestamp', true);

        return $now + ($days * DAY_IN_SECONDS);
    }

    /**
     * Normalize delivery data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeDelivery(array $data): array
    {
        try {
            $send_on = sanitize_text_field((string) ($data['send_on'] ?? ($data['date'] ?? '')));
            $time = sanitize_text_field((string) ($data['time'] ?? '09:00'));
            $timezone = sanitize_text_field((string) ($data['timezone'] ?? 'Europe/Rome'));

            if ('' !== $send_on) {
                $schedule = new DeliverySchedule($send_on, $time, $timezone);

                return $schedule->toArray();
            }
        } catch (\InvalidArgumentException $exception) {
            // Invalid delivery data, return empty
        }

        return [
            'send_on' => '',
            'send_at' => 0,
            'timezone' => 'Europe/Rome',
        ];
    }

    /**
     * Sanitize contact data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function sanitizeContact(array $data): array
    {
        $name = sanitize_text_field((string) ($data['name'] ?? ($data['full_name'] ?? '')));
        $email = sanitize_email((string) ($data['email'] ?? ''));

        if (! is_email($email)) {
            $email = '';
        }

        $phone = sanitize_text_field((string) ($data['phone'] ?? ''));

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * Get checkout URL.
     */
    private function getCheckoutUrl(): string
    {
        if (function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }

        return home_url('/checkout/');
    }

    /**
     * Build experience payload.
     *
     * @return array<string, mixed>
     */
    private function buildExperiencePayload(?WP_Post $experience): array
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
     * Load upcoming slots for experience.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadUpcomingSlots(int $experience_id): array
    {
        if ($experience_id <= 0) {
            return [];
        }

        // This will be delegated to a Slots service in future refactoring
        // For now, return empty array - actual implementation in VoucherManager
        return [];
    }

    /**
     * Normalize voucher addons.
     *
     * @param array<string, int> $addons
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeVoucherAddons(int $experience_id, array $addons): array
    {
        if (! $addons) {
            return [];
        }

        // This will use Pricing service in future
        // For now, return empty array - actual implementation in VoucherManager
        return [];
    }
}















