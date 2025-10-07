<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

use function absint;
use function get_post_meta;
use function in_array;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;

final class AvailabilityService
{
    /**
     * Calcola slot virtuali (non persistiti) basati sulle meta di disponibilità.
     *
     * @return array<int, array<string, string|int>>
     */
    public static function get_virtual_slots(int $experience_id, string $start_utc, string $end_utc): array
    {
        $experience_id = absint($experience_id);
        if ($experience_id <= 0) {
            return [];
        }

        $availability = get_post_meta($experience_id, '_fp_exp_availability', true);
        if (! is_array($availability)) {
            return [];
        }

        $frequency = isset($availability['frequency']) ? sanitize_key((string) $availability['frequency']) : 'weekly';
        $times = isset($availability['times']) && is_array($availability['times']) ? $availability['times'] : [];
        $days = isset($availability['days_of_week']) && is_array($availability['days_of_week']) ? $availability['days_of_week'] : [];
        $custom = isset($availability['custom_slots']) && is_array($availability['custom_slots']) ? $availability['custom_slots'] : [];
        $capacity = isset($availability['slot_capacity']) ? absint((string) $availability['slot_capacity']) : 0;
        $lead_time = isset($availability['lead_time_hours']) ? absint((string) $availability['lead_time_hours']) : 0;
        $buffer_before = isset($availability['buffer_before_minutes']) ? absint((string) $availability['buffer_before_minutes']) : 0;
        $buffer_after = isset($availability['buffer_after_minutes']) ? absint((string) $availability['buffer_after_minutes']) : 0;
        
        // Leggi le date di inizio e fine dalla ricorrenza
        $recurrence_start_date = isset($availability['start_date']) ? sanitize_text_field((string) $availability['start_date']) : '';
        $recurrence_end_date = isset($availability['end_date']) ? sanitize_text_field((string) $availability['end_date']) : '';

        // Durata: se non specificata nelle meta, default 60m.
        $duration_minutes = 60;

        try {
            $range_start = new DateTimeImmutable($start_utc, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $range_start = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        try {
            $range_end = new DateTimeImmutable($end_utc, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $range_end = $range_start;
        }

        if ($range_end < $range_start) {
            $range_end = $range_start;
        }
        
        // Applica i limiti di data dalla ricorrenza se presenti
        $tz = new DateTimeZone(wp_timezone_string() ?: 'UTC');
        
        if ('' !== $recurrence_start_date) {
            try {
                $rec_start = new DateTimeImmutable($recurrence_start_date, $tz);
                $rec_start_utc = $rec_start->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0, 0);
                if ($rec_start_utc > $range_start) {
                    $range_start = $rec_start_utc;
                }
            } catch (Exception $e) {
                // Ignora se la data non è valida
            }
        }
        
        if ('' !== $recurrence_end_date) {
            try {
                $rec_end = new DateTimeImmutable($recurrence_end_date, $tz);
                $rec_end_utc = $rec_end->setTimezone(new DateTimeZone('UTC'))->setTime(23, 59, 59);
                if ($rec_end_utc < $range_end) {
                    $range_end = $rec_end_utc;
                }
            } catch (Exception $e) {
                // Ignora se la data non è valida
            }
        }
        
        // Se la data di fine è prima della data di inizio dopo i limiti, non ci sono slot
        if ($range_end < $range_start) {
            return [];
        }

        $occurrences = [];

        if ('custom' === $frequency) {
            foreach ($custom as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $date = isset($slot['date']) ? sanitize_text_field((string) $slot['date']) : '';
                $time = isset($slot['time']) ? sanitize_text_field((string) $slot['time']) : '';
                if ('' === $date || '' === $time) {
                    continue;
                }
                try {
                    $start = new DateTimeImmutable($date . ' ' . $time, new DateTimeZone(wp_timezone_string() ?: 'UTC'));
                    $start = $start->setTimezone(new DateTimeZone('UTC'));
                } catch (Exception $e) {
                    continue;
                }
                $end = $start->add(new DateInterval('PT' . max(1, $duration_minutes) . 'M'));
                if ($end < $range_start || $start > $range_end) {
                    continue;
                }
                $occurrences[] = [$start, $end];
            }
        } else {
            // daily/weekly
            $period = new DatePeriod(
                $range_start->setTime(0, 0),
                new DateInterval('P1D'),
                $range_end->setTime(23, 59, 59)->add(new DateInterval('PT1S'))
            );

            foreach ($period as $day) {
                if (! $day instanceof DateTimeImmutable) {
                    $day = DateTimeImmutable::createFromMutable($day);
                }

                if ('weekly' === $frequency) {
                    $weekday = strtolower($day->format('l'));
                    if (! in_array($weekday, array_map('strtolower', $days), true)) {
                        continue;
                    }
                }

                foreach ($times as $time) {
                    $time = trim((string) $time);
                    if ('' === $time) {
                        continue;
                    }
                    try {
                        $local = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $time, $tz);
                        $start = $local->setTimezone(new DateTimeZone('UTC'));
                    } catch (Exception $e) {
                        continue;
                    }
                    $end = $start->add(new DateInterval('PT' . max(1, $duration_minutes) . 'M'));
                    if ($end < $range_start || $start > $range_end) {
                        continue;
                    }
                    $occurrences[] = [$start, $end];
                }
            }
        }

        // Ordina per data di inizio
        usort(
            $occurrences,
            static function (array $a, array $b): int {
                return $a[0] <=> $b[0];
            }
        );

        // Applica lead time
        if ($lead_time > 0) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $cutoff = $now->add(new DateInterval('PT' . $lead_time . 'H'));
            $occurrences = array_values(array_filter(
                $occurrences,
                static function (array $occ) use ($cutoff): bool {
                    return $occ[0] >= $cutoff;
                }
            ));
        }

        // Converte in payload e calcola capacità rimanente
        $slots = [];
        foreach ($occurrences as [$start, $end]) {
            $start_sql = $start->format('Y-m-d H:i:s');
            $end_sql = $end->format('Y-m-d H:i:s');
            
            // Calcola quanti posti sono già prenotati per questo slot virtuale
            $booked = Reservations::count_bookings_for_virtual_slot(
                $experience_id,
                $start_sql,
                $end_sql
            );
            
            $capacity_remaining = max(0, $capacity - $booked);
            
            $slots[] = [
                'experience_id' => $experience_id,
                'start' => $start_sql,
                'end' => $end_sql,
                'capacity_total' => $capacity,
                'capacity_remaining' => $capacity_remaining,
                'buffer_before' => $buffer_before,
                'buffer_after' => $buffer_after,
                'duration' => (int) (($end->getTimestamp() - $start->getTimestamp()) / 60),
            ];
        }

        return $slots;
    }
}


