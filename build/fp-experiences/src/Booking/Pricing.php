<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

use function __;
use function absint;
use function floatval;
use function get_option;
use function get_post_meta;
use function is_array;
use function max;
use function round;
use function sanitize_key;
use function sanitize_text_field;
use function wp_timezone;

final class Pricing
{
    /**
     * Retrieve normalized ticket types for an experience.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_ticket_types(int $experience_id): array
    {
        $raw = get_post_meta($experience_id, '_fp_ticket_types', true);

        if (! is_array($raw)) {
            return [];
        }

        $types = [];

        foreach ($raw as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            $normalized = self::normalize_ticket_type($ticket);

            if (! $normalized) {
                continue;
            }

            $types[$normalized['slug']] = $normalized;
        }

        return $types;
    }

    /**
     * Retrieve normalized add-ons for an experience.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_addons(int $experience_id): array
    {
        $raw = get_post_meta($experience_id, '_fp_addons', true);

        if (! is_array($raw)) {
            return [];
        }

        $addons = [];

        foreach ($raw as $addon) {
            if (! is_array($addon)) {
                continue;
            }

            $normalized = self::normalize_addon($addon);

            if (! $normalized) {
                continue;
            }

            $addons[$normalized['slug']] = $normalized;
        }

        return $addons;
    }

    /**
     * Build a price breakdown for the provided booking context.
     *
     * @param array<string, int>        $ticket_quantities Quantities indexed by ticket slug.
     * @param array<string, int|float>  $addon_quantities  Quantities indexed by addon slug.
     *
     * @return array<string, mixed>
     */
    public static function calculate_breakdown(
        int $experience_id,
        string $slot_start_utc,
        array $ticket_quantities,
        array $addon_quantities = []
    ): array {
        $tickets = self::get_ticket_types($experience_id);
        $addons = self::get_addons($experience_id);

        $ticket_lines = [];
        $ticket_subtotal = 0.0;
        $total_guests = 0;

        foreach ($ticket_quantities as $slug => $quantity) {
            $slug_key = sanitize_key((string) $slug);
            $quantity = absint($quantity);

            if ($quantity <= 0 || ! isset($tickets[$slug_key])) {
                continue;
            }

            $ticket = $tickets[$slug_key];

            if ($ticket['max'] > 0) {
                $quantity = min($quantity, $ticket['max']);
            }

            $line_total = $ticket['price'] * $quantity;

            $ticket_lines[] = [
                'slug' => $slug_key,
                'label' => $ticket['label'],
                'quantity' => $quantity,
                'unit_price' => round($ticket['price'], 2),
                'line_total' => round($line_total, 2),
            ];

            $ticket_subtotal += $line_total;
            $total_guests += $quantity;
        }

        $addon_lines = [];
        $addon_subtotal = 0.0;

        foreach ($addon_quantities as $slug => $quantity) {
            $slug_key = sanitize_key((string) $slug);

            if (! isset($addons[$slug_key])) {
                continue;
            }

            $addon = $addons[$slug_key];
            $quantity = (float) $quantity;

            if (! $addon['allow_multiple']) {
                $quantity = min(1.0, max(0.0, $quantity));
            } else {
                $quantity = max(0.0, $quantity);

                if ($addon['max'] > 0) {
                    $quantity = min($quantity, (float) $addon['max']);
                }
            }

            if ($quantity <= 0) {
                continue;
            }

            $line_total = $addon['price'] * $quantity;

            $addon_lines[] = [
                'slug' => $slug_key,
                'label' => $addon['label'],
                'quantity' => $quantity,
                'unit_price' => round($addon['price'], 2),
                'line_total' => round($line_total, 2),
            ];

            $addon_subtotal += $line_total;
        }

        $base_price = self::get_base_price($experience_id);
        $subtotal = $base_price + $ticket_subtotal + $addon_subtotal;

        [$slot_start_local, $timezone] = self::resolve_slot_datetime($slot_start_utc);
        $rules = self::get_pricing_rules($experience_id, $timezone);

        [$total_with_rules, $adjustments] = self::apply_pricing_rules($rules, $slot_start_local, $subtotal);

        $currency = (string) get_option('woocommerce_currency', 'EUR');

        return [
            'base_price' => round($base_price, 2),
            'tickets' => $ticket_lines,
            'addons' => $addon_lines,
            'adjustments' => $adjustments,
            'subtotal' => round($subtotal, 2),
            'total' => round(max(0.0, $total_with_rules), 2),
            'currency' => $currency,
            'total_guests' => $total_guests,
        ];
    }

    /**
     * Normalize a single ticket definition.
     *
     * @param array<string, mixed> $ticket
     *
     * @return array<string, mixed>|null
     */
    private static function normalize_ticket_type(array $ticket): ?array
    {
        $slug = '';

        if (! empty($ticket['slug'])) {
            $slug = sanitize_key((string) $ticket['slug']);
        } elseif (! empty($ticket['name'])) {
            $slug = sanitize_key((string) $ticket['name']);
        }

        if ('' === $slug) {
            return null;
        }

        $label = ! empty($ticket['label']) ? sanitize_text_field((string) $ticket['label']) : '';

        if ('' === $label && ! empty($ticket['name'])) {
            $label = sanitize_text_field((string) $ticket['name']);
        }

        if ('' === $label) {
            $label = ucfirst(str_replace('_', ' ', $slug));
        }

        return [
            'slug' => $slug,
            'label' => $label,
            'price' => max(0.0, floatval($ticket['price'] ?? 0.0)),
            'min' => absint($ticket['min'] ?? 0),
            'max' => absint($ticket['max'] ?? 0),
            'capacity' => absint($ticket['capacity'] ?? 0),
            'description' => ! empty($ticket['description']) ? sanitize_text_field((string) $ticket['description']) : '',
        ];
    }

    /**
     * Normalize an addon definition.
     *
     * @param array<string, mixed> $addon
     *
     * @return array<string, mixed>|null
     */
    private static function normalize_addon(array $addon): ?array
    {
        $slug = '';

        if (! empty($addon['slug'])) {
            $slug = sanitize_key((string) $addon['slug']);
        } elseif (! empty($addon['name'])) {
            $slug = sanitize_key((string) $addon['name']);
        }

        if ('' === $slug) {
            return null;
        }

        $label = ! empty($addon['label']) ? sanitize_text_field((string) $addon['label']) : '';

        if ('' === $label && ! empty($addon['name'])) {
            $label = sanitize_text_field((string) $addon['name']);
        }

        if ('' === $label) {
            $label = ucfirst(str_replace('_', ' ', $slug));
        }

        $allow_multiple = isset($addon['allow_multiple']) ? (bool) $addon['allow_multiple'] : true;

        return [
            'slug' => $slug,
            'label' => $label,
            'price' => max(0.0, floatval($addon['price'] ?? 0.0)),
            'allow_multiple' => $allow_multiple,
            'max' => absint($addon['max'] ?? 0),
            'description' => ! empty($addon['description']) ? sanitize_text_field((string) $addon['description']) : '',
        ];
    }

    private static function get_base_price(int $experience_id): float
    {
        return max(0.0, floatval(get_post_meta($experience_id, '_fp_base_price', true)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function get_pricing_rules(int $experience_id, DateTimeZone $timezone): array
    {
        $raw = get_post_meta($experience_id, '_fp_pricing_rules', true);

        if (! is_array($raw)) {
            return [];
        }

        $rules = [];

        foreach ($raw as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $normalized = self::normalize_pricing_rule($rule, $timezone);

            if (! $normalized) {
                continue;
            }

            $rules[] = $normalized;
        }

        return $rules;
    }

    /**
     * Normalize a pricing rule entry.
     *
     * @param array<string, mixed> $rule
     *
     * @return array<string, mixed>|null
     */
    private static function normalize_pricing_rule(array $rule, DateTimeZone $timezone): ?array
    {
        $type = isset($rule['type']) ? strtolower((string) $rule['type']) : '';

        if (! in_array($type, ['seasonal', 'weekday', 'weekend'], true)) {
            return null;
        }

        $modifier = self::normalize_modifier($rule['modifier'] ?? $rule['adjustment'] ?? null);

        if (! $modifier) {
            return null;
        }

        $label = ! empty($rule['label']) ? sanitize_text_field((string) $rule['label']) : '';

        if ('' === $label) {
            switch ($type) {
                case 'seasonal':
                    $label = __('Seasonal adjustment', 'fp-experiences');
                    break;
                case 'weekday':
                    $label = __('Weekday adjustment', 'fp-experiences');
                    break;
                case 'weekend':
                    $label = __('Weekend adjustment', 'fp-experiences');
                    break;
            }
        }

        $normalized = [
            'type' => $type,
            'label' => $label,
            'modifier' => $modifier,
            'priority' => absint($rule['priority'] ?? 10),
        ];

        if ('seasonal' === $type) {
            $start = isset($rule['start_date']) ? (string) $rule['start_date'] : '';
            $end = isset($rule['end_date']) ? (string) $rule['end_date'] : '';

            if ('' === $start || '' === $end) {
                return null;
            }

            try {
                $normalized['start'] = new DateTimeImmutable($start, $timezone);
                $normalized['end'] = (new DateTimeImmutable($end, $timezone))->setTime(23, 59, 59);
            } catch (Exception $exception) {
                return null;
            }
        }

        if ('weekday' === $type) {
            $days = [];

            if (! empty($rule['days']) && is_array($rule['days'])) {
                foreach ($rule['days'] as $day) {
                    $day = strtolower((string) $day);

                    if ('' === $day) {
                        continue;
                    }

                    $days[] = $day;
                }
            }

            if (empty($days)) {
                return null;
            }

            $normalized['days'] = $days;
        }

        return $normalized;
    }

    /**
     * Normalize modifier payloads.
     *
     * @param mixed $modifier
     *
     * @return array<string, mixed>|null
     */
    private static function normalize_modifier($modifier): ?array
    {
        if (is_array($modifier)) {
            $type = isset($modifier['type']) ? strtolower((string) $modifier['type']) : 'flat';
            $value = isset($modifier['value']) ? floatval($modifier['value']) : 0.0;
        } elseif (is_numeric($modifier)) {
            $type = 'flat';
            $value = floatval($modifier);
        } else {
            return null;
        }

        if ('percentage' === $type) {
            $type = 'percent';
        }

        if (! in_array($type, ['flat', 'percent'], true)) {
            return null;
        }

        return [
            'type' => $type,
            'value' => $value,
        ];
    }

    /**
     * Apply pricing rules to the subtotal.
     *
     * @param array<int, array<string, mixed>> $rules
     *
     * @return array{0:float,1:array<int,array<string,mixed>>}
     */
    private static function apply_pricing_rules(array $rules, DateTimeImmutable $slot_start, float $initial_total): array
    {
        if (empty($rules)) {
            return [$initial_total, []];
        }

        usort(
            $rules,
            static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']
        );

        $total = $initial_total;
        $adjustments = [];

        foreach ($rules as $rule) {
            if (! self::rule_applies($rule, $slot_start)) {
                continue;
            }

            $amount = self::calculate_modifier_amount($rule['modifier'], $total);

            if (0.0 === $amount) {
                continue;
            }

            $total += $amount;

            $adjustments[] = [
                'label' => $rule['label'],
                'amount' => round($amount, 2),
                'total_after' => round($total, 2),
            ];
        }

        return [$total, $adjustments];
    }

    /**
     * Determine if a rule applies to a slot start time.
     *
     * @param array<string, mixed> $rule
     */
    private static function rule_applies(array $rule, DateTimeImmutable $slot_start): bool
    {
        switch ($rule['type']) {
            case 'seasonal':
                return isset($rule['start'], $rule['end'])
                    && $slot_start >= $rule['start']
                    && $slot_start <= $rule['end'];
            case 'weekday':
                $weekday = strtolower($slot_start->format('l'));

                return in_array($weekday, $rule['days'], true);
            case 'weekend':
                $weekday = strtolower($slot_start->format('l'));

                return in_array($weekday, ['saturday', 'sunday'], true);
            default:
                return false;
        }
    }

    /**
     * Calculate modifier amount based on the provided base.
     *
     * @param array<string, mixed> $modifier
     */
    private static function calculate_modifier_amount(array $modifier, float $base): float
    {
        if ('percent' === $modifier['type']) {
            return $base * ($modifier['value'] / 100);
        }

        return $modifier['value'];
    }

    /**
     * Resolve the slot datetime into local timezone context.
     *
     * @return array{0:DateTimeImmutable,1:DateTimeZone}
     */
    private static function resolve_slot_datetime(string $slot_start_utc): array
    {
        $timezone = wp_timezone();

        try {
            $slot_start = new DateTimeImmutable($slot_start_utc, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            $slot_start = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        return [$slot_start->setTimezone($timezone), $timezone];
    }
}
