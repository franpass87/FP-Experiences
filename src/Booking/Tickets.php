<?php

namespace FP_Exp\Booking;

/**
 * Provides helper methods for ticket and attendee data.
 */
class Tickets
{
    /**
     * Retrieves ticket type definitions for an experience.
     */
    public function get_ticket_types(int $experience_id): array
    {
        // TODO: Load ticket type definitions from post meta for the given experience.
        return [];
    }

    /**
     * Normalizes ticket selections submitted by users.
     */
    public function normalize_selection(array $input): array
    {
        // TODO: Sanitize and normalize ticket quantities and pricing selections.
        return [];
    }

    /**
     * Calculates attendee totals for reporting and emails.
     */
    public function summarize_attendees(array $selection): array
    {
        // TODO: Produce aggregated attendee counts from the normalized ticket selection.
        return [];
    }
}
