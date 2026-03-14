<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Services;

use FP_Exp\Booking\Pricing;
use WP_Error;

use function absint;
use function array_filter;
use function array_map;
use function array_values;
use function esc_html__;
use function get_post_meta;
use function is_array;
use function is_string;
use function max;
use function sanitize_key;

/**
 * Service for calculating voucher pricing.
 *
 * Handles all pricing logic for gift vouchers.
 */
final class VoucherPricingService
{
    /**
     * Calculate total price for a voucher.
     *
     * @param array<string, mixed> $addons_requested
     *
     * @return array{total: float, base_price: float, ticket_price: float|null, ticket_slug: string, ticket_label: string, addons_total: float, addons_selected: array<string, int>}|WP_Error
     */
    public function calculateTotal(int $experience_id, int $quantity, array $addons_requested, string $ticket_slug = '')
    {
        if ($quantity <= 0) {
            $quantity = 1;
        }

        // Sanitize addons
        $addons_requested = $this->sanitizeAddonsInput($addons_requested);

        // Get pricing data
        $pricing_addons = Pricing::get_addons($experience_id);
        $addons_selected = [];
        $addons_total = 0.0;

        // Calculate addons total
        foreach ($addons_requested as $slug) {
            if (! isset($pricing_addons[$slug])) {
                continue;
            }

            $addon = $pricing_addons[$slug];
            $line_total = (float) ($addon['price'] ?? 0.0);
            $allow_multiple = ! empty($addon['allow_multiple']);

            if ($allow_multiple) {
                $line_total *= $quantity;
            }

            $addons_total += max(0.0, $line_total);
            $addons_selected[$slug] = $allow_multiple ? max(1, $quantity) : 1;
        }

        // Get selected ticket price (or fallback to lowest if only one/default behavior).
        $ticket_slug = sanitize_key($ticket_slug);
        [$ticket_price, $resolved_ticket_slug, $resolved_ticket_label] = $this->resolveTicketPrice($experience_id, $ticket_slug);

        // Get base price
        $base_price = $this->getBasePrice($experience_id);

        // Calculate total:
        // when ticket pricing is available, avoid adding base price to prevent inflated gift totals.
        $has_ticket_pricing = null !== $ticket_price && $ticket_price > 0;
        $total = $has_ticket_pricing ? 0.0 : $base_price;

        if ($has_ticket_pricing) {
            $total += $ticket_price * $quantity;
        }

        $total += $addons_total;

        if ($total <= 0) {
            return new WP_Error(
                'fp_exp_gift_total',
                esc_html__('Unable to calculate a price for the voucher.', 'fp-experiences')
            );
        }

        return [
            'total' => $total,
            'base_price' => $base_price,
            'ticket_price' => $ticket_price,
            'ticket_slug' => $resolved_ticket_slug,
            'ticket_label' => $resolved_ticket_label,
            'addons_total' => $addons_total,
            'addons_selected' => $addons_selected,
        ];
    }

    /**
     * Get base price for experience.
     */
    public function getBasePrice(int $experience_id): float
    {
        $base_price = (float) get_post_meta($experience_id, '_fp_base_price', true);

        return max(0.0, $base_price);
    }

    /**
     * Get lowest ticket price for experience.
     */
    public function getLowestTicketPrice(int $experience_id): ?float
    {
        $tickets = Pricing::get_ticket_types($experience_id);
        $ticket_price = null;

        foreach ($tickets as $ticket) {
            $price = (float) ($ticket['price'] ?? 0.0);

            if (null === $ticket_price || $price < $ticket_price) {
                $ticket_price = $price;
            }
        }

        return $ticket_price;
    }

    /**
     * Resolve ticket price by selected slug, with fallback to lowest available price.
     *
     * @return array{0: float|null, 1: string, 2: string}
     */
    private function resolveTicketPrice(int $experience_id, string $ticket_slug): array
    {
        $tickets = Pricing::get_ticket_types($experience_id);

        if ('' !== $ticket_slug && isset($tickets[$ticket_slug])) {
            return [
                (float) ($tickets[$ticket_slug]['price'] ?? 0.0),
                $ticket_slug,
                (string) ($tickets[$ticket_slug]['label'] ?? ''),
            ];
        }

        if (empty($tickets)) {
            return [null, '', ''];
        }

        $fallback_slug = '';
        $fallback_price = null;
        $fallback_label = '';
        foreach ($tickets as $slug => $ticket) {
            $price = (float) ($ticket['price'] ?? 0.0);
            if (null === $fallback_price || $price < $fallback_price) {
                $fallback_slug = (string) $slug;
                $fallback_price = $price;
                $fallback_label = (string) ($ticket['label'] ?? '');
            }
        }

        return [$fallback_price, $fallback_slug, $fallback_label];
    }

    /**
     * Calculate addons total.
     *
     * @param array<string, mixed> $addons_requested
     *
     * @return array{total: float, selected: array<string, int>}
     */
    public function calculateAddonsTotal(int $experience_id, array $addons_requested, int $quantity): array
    {
        $addons_requested = $this->sanitizeAddonsInput($addons_requested);
        $pricing_addons = Pricing::get_addons($experience_id);
        $addons_selected = [];
        $addons_total = 0.0;

        foreach ($addons_requested as $slug) {
            if (! isset($pricing_addons[$slug])) {
                continue;
            }

            $addon = $pricing_addons[$slug];
            $line_total = (float) ($addon['price'] ?? 0.0);
            $allow_multiple = ! empty($addon['allow_multiple']);

            if ($allow_multiple) {
                $line_total *= $quantity;
            }

            $addons_total += max(0.0, $line_total);
            $addons_selected[$slug] = $allow_multiple ? max(1, $quantity) : 1;
        }

        return [
            'total' => $addons_total,
            'selected' => $addons_selected,
        ];
    }

    /**
     * Sanitize addons input.
     *
     * @param array<string, mixed> $addons
     *
     * @return array<int, string>
     */
    private function sanitizeAddonsInput(array $addons): array
    {
        return array_values(array_filter(array_map(static function ($value) {
            if (is_string($value)) {
                return sanitize_key($value);
            }

            if (is_array($value) && isset($value['slug'])) {
                return sanitize_key((string) $value['slug']);
            }

            return '';
        }, $addons)));
    }
}















