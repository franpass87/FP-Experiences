<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use function absint;
use function sanitize_key;
use function str_replace;
use function ucfirst;

/**
 * Provides helper methods for ticket and attendee data.
 */
final class Tickets
{
    /**
     * Retrieves ticket type definitions for an experience.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_ticket_types(int $experience_id): array
    {
        return Pricing::get_ticket_types($experience_id);
    }

    /**
     * Normalizes ticket selections submitted by users.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, int>
     */
    public function normalize_selection(array $input, int $experience_id = 0): array
    {
        $normalized = [];
        $definitions = [];

        if ($experience_id > 0) {
            $definitions = $this->get_ticket_types($experience_id);
        }

        foreach ($input as $slug => $quantity) {
            $slug_key = sanitize_key((string) $slug);
            $quantity = absint($quantity);

            if ('' === $slug_key || $quantity <= 0) {
                continue;
            }

            if (isset($definitions[$slug_key])) {
                $max = (int) ($definitions[$slug_key]['max'] ?? 0);

                if ($max > 0) {
                    $quantity = min($quantity, $max);
                }
            }

            $normalized[$slug_key] = $quantity;
        }

        return $normalized;
    }

    /**
     * Calculates attendee totals for reporting and emails.
     *
     * @param array<string, int|string> $selection
     *
     * @return array{total:int, by_type:array<string, int>, items:array<int, array{slug:string, label:string, quantity:int}>}
     */
    public function summarize_attendees(array $selection, int $experience_id = 0): array
    {
        $normalized = $this->normalize_selection($selection, $experience_id);
        $labels = [];

        if ($experience_id > 0) {
            foreach ($this->get_ticket_types($experience_id) as $ticket) {
                $labels[$ticket['slug']] = $ticket['label'];
            }
        }

        $total = 0;
        $by_type = [];
        $items = [];

        foreach ($normalized as $slug => $quantity) {
            $total += $quantity;
            $by_type[$slug] = ($by_type[$slug] ?? 0) + $quantity;

            $items[] = [
                'slug' => $slug,
                'label' => $labels[$slug] ?? ucfirst(str_replace('_', ' ', $slug)),
                'quantity' => $quantity,
            ];
        }

        return [
            'total' => $total,
            'by_type' => $by_type,
            'items' => $items,
        ];
    }
}
