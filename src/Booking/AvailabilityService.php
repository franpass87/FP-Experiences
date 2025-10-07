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

/**
 * Servizio per calcolare slot virtuali basati su configurazione di disponibilità.
 * 
 * LOGICA SEMPLIFICATA:
 * 1. Legge da _fp_exp_recurrence (nuovo formato unificato)
 * 2. Fallback a _fp_exp_availability (vecchio formato legacy)
 * 3. Genera slot virtuali per il range richiesto
 * 4. Applica lead_time, date limits e calcola capacità rimanente
 */
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
            self::log_debug($experience_id, 'Invalid experience_id');
            return [];
        }

        // Leggi configurazione ricorrenza
        $config = self::read_recurrence_config($experience_id);
        
        if (empty($config)) {
            self::log_debug($experience_id, 'No recurrence configuration found');
            return [];
        }

        self::log_debug($experience_id, 'Configuration loaded', $config);

        // Parse e valida date range
        try {
            $range_start = new DateTimeImmutable($start_utc, new DateTimeZone('UTC'));
            $range_end = new DateTimeImmutable($end_utc, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            self::log_debug($experience_id, 'Invalid date range', ['error' => $e->getMessage()]);
            return [];
        }

        if ($range_end < $range_start) {
            $range_end = $range_start;
        }

        // Applica limiti di data dalla configurazione
        $tz = new DateTimeZone(wp_timezone_string() ?: 'UTC');
        
        if (!empty($config['start_date'])) {
            try {
                $rec_start = new DateTimeImmutable($config['start_date'], $tz);
                $rec_start_utc = $rec_start->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0, 0);
                if ($rec_start_utc > $range_start) {
                    $range_start = $rec_start_utc;
                }
            } catch (Exception $e) {
                // Ignora date invalide
            }
        }
        
        if (!empty($config['end_date'])) {
            try {
                $rec_end = new DateTimeImmutable($config['end_date'], $tz);
                $rec_end_utc = $rec_end->setTimezone(new DateTimeZone('UTC'))->setTime(23, 59, 59);
                if ($rec_end_utc < $range_end) {
                    $range_end = $rec_end_utc;
                }
            } catch (Exception $e) {
                // Ignora date invalide
            }
        }
        
        // Se la data di fine è prima della data di inizio dopo i limiti, non ci sono slot
        if ($range_end < $range_start) {
            self::log_debug($experience_id, 'Date range invalid after applying limits');
            return [];
        }

        // Genera occorrenze basate sulla frequenza
        $occurrences = self::generate_occurrences(
            $config['frequency'],
            $config['times'],
            $config['days'],
            $config['custom_slots'],
            $config['duration_minutes'],
            $range_start,
            $range_end,
            $tz
        );

        self::log_debug($experience_id, 'Generated occurrences', ['count' => count($occurrences)]);

        // Applica lead time
        if ($config['lead_time_hours'] > 0) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $cutoff = $now->add(new DateInterval('PT' . $config['lead_time_hours'] . 'H'));
            $occurrences = array_values(array_filter(
                $occurrences,
                static function (array $occ) use ($cutoff): bool {
                    return $occ[0] >= $cutoff;
                }
            ));
            
            self::log_debug($experience_id, 'After lead time filter', ['count' => count($occurrences)]);
        }

        // Converti in slot con capacità
        $slots = [];
        foreach ($occurrences as [$start, $end]) {
            $start_sql = $start->format('Y-m-d H:i:s');
            $end_sql = $end->format('Y-m-d H:i:s');
            
            // Calcola quanti posti sono già prenotati
            $booked = Reservations::count_bookings_for_virtual_slot(
                $experience_id,
                $start_sql,
                $end_sql
            );
            
            $capacity_remaining = max(0, $config['capacity'] - $booked);
            
            $slots[] = [
                'experience_id' => $experience_id,
                'start' => $start_sql,
                'end' => $end_sql,
                'capacity_total' => $config['capacity'],
                'capacity_remaining' => $capacity_remaining,
                'buffer_before' => $config['buffer_before'],
                'buffer_after' => $config['buffer_after'],
                'duration' => (int) (($end->getTimestamp() - $start->getTimestamp()) / 60),
            ];
        }

        self::log_debug($experience_id, 'Final slots', ['count' => count($slots)]);

        return $slots;
    }

    /**
     * Legge configurazione di ricorrenza da meta unificata o legacy.
     * 
     * @return array{
     *   frequency: string,
     *   times: array<string>,
     *   days: array<string>,
     *   custom_slots: array,
     *   capacity: int,
     *   lead_time_hours: int,
     *   buffer_before: int,
     *   buffer_after: int,
     *   duration_minutes: int,
     *   start_date: string,
     *   end_date: string
     * }
     */
    private static function read_recurrence_config(int $experience_id): array
    {
        // Tenta di leggere dal nuovo formato _fp_exp_recurrence
        $recurrence = get_post_meta($experience_id, '_fp_exp_recurrence', true);
        
        if (is_array($recurrence) && !empty($recurrence)) {
            return self::parse_unified_format($experience_id, $recurrence);
        }

        // Fallback al vecchio formato _fp_exp_availability
        $availability = get_post_meta($experience_id, '_fp_exp_availability', true);
        
        if (is_array($availability) && !empty($availability)) {
            return self::parse_legacy_format($experience_id, $availability);
        }

        return [];
    }

    /**
     * Parse nuovo formato unificato _fp_exp_recurrence.
     */
    private static function parse_unified_format(int $experience_id, array $recurrence): array
    {
        $frequency = isset($recurrence['frequency']) ? sanitize_key((string) $recurrence['frequency']) : 'weekly';
        
        // Estrai times e days dai time_sets
        $all_times = [];
        $all_days = [];
        $capacity = 0;
        $buffer_before = 0;
        $buffer_after = 0;
        
        if (isset($recurrence['time_sets']) && is_array($recurrence['time_sets'])) {
            foreach ($recurrence['time_sets'] as $set) {
                if (!is_array($set)) {
                    continue;
                }
                
                // Raccogli orari
                if (isset($set['times']) && is_array($set['times'])) {
                    foreach ($set['times'] as $time) {
                        $time_str = trim((string) $time);
                        if ($time_str !== '' && !in_array($time_str, $all_times, true)) {
                            $all_times[] = $time_str;
                        }
                    }
                }
                
                // Raccogli giorni
                if (isset($set['days']) && is_array($set['days'])) {
                    foreach ($set['days'] as $day) {
                        $day_str = strtolower(trim((string) $day));
                        if ($day_str !== '' && !in_array($day_str, $all_days, true)) {
                            $all_days[] = $day_str;
                        }
                    }
                }
                
                // Usa la capienza più alta tra i set
                if (isset($set['capacity'])) {
                    $set_capacity = absint((string) $set['capacity']);
                    if ($set_capacity > $capacity) {
                        $capacity = $set_capacity;
                    }
                }
                
                // Usa i buffer del primo set
                if ($buffer_before === 0 && isset($set['buffer_before'])) {
                    $buffer_before = absint((string) $set['buffer_before']);
                }
                if ($buffer_after === 0 && isset($set['buffer_after'])) {
                    $buffer_after = absint((string) $set['buffer_after']);
                }
            }
        }
        
        // Se non ci sono giorni nei time_sets, usa i giorni globali
        if (empty($all_days) && isset($recurrence['days']) && is_array($recurrence['days'])) {
            $all_days = array_map('strtolower', array_map('trim', $recurrence['days']));
        }
        
        // Leggi lead_time dai meta separati (se presenti)
        $lead_time = absint(get_post_meta($experience_id, '_fp_lead_time_hours', true));
        
        $start_date = isset($recurrence['start_date']) ? sanitize_text_field((string) $recurrence['start_date']) : '';
        $end_date = isset($recurrence['end_date']) ? sanitize_text_field((string) $recurrence['end_date']) : '';
        
        return [
            'frequency' => $frequency,
            'times' => $all_times,
            'days' => $all_days,
            'custom_slots' => [],
            'capacity' => $capacity,
            'lead_time_hours' => $lead_time,
            'buffer_before' => $buffer_before,
            'buffer_after' => $buffer_after,
            'duration_minutes' => 60, // Default
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
    }

    /**
     * Parse vecchio formato legacy _fp_exp_availability.
     */
    private static function parse_legacy_format(int $experience_id, array $availability): array
    {
        $frequency = isset($availability['frequency']) ? sanitize_key((string) $availability['frequency']) : 'weekly';
        $times = isset($availability['times']) && is_array($availability['times']) ? $availability['times'] : [];
        $days = isset($availability['days_of_week']) && is_array($availability['days_of_week']) ? $availability['days_of_week'] : [];
        $custom = isset($availability['custom_slots']) && is_array($availability['custom_slots']) ? $availability['custom_slots'] : [];
        $capacity = isset($availability['slot_capacity']) ? absint((string) $availability['slot_capacity']) : 0;
        $lead_time = isset($availability['lead_time_hours']) ? absint((string) $availability['lead_time_hours']) : 0;
        $buffer_before = isset($availability['buffer_before_minutes']) ? absint((string) $availability['buffer_before_minutes']) : 0;
        $buffer_after = isset($availability['buffer_after_minutes']) ? absint((string) $availability['buffer_after_minutes']) : 0;
        $start_date = isset($availability['start_date']) ? sanitize_text_field((string) $availability['start_date']) : '';
        $end_date = isset($availability['end_date']) ? sanitize_text_field((string) $availability['end_date']) : '';

        // Normalizza giorni
        $days = array_map('strtolower', array_map('trim', $days));

        return [
            'frequency' => $frequency,
            'times' => $times,
            'days' => $days,
            'custom_slots' => $custom,
            'capacity' => $capacity,
            'lead_time_hours' => $lead_time,
            'buffer_before' => $buffer_before,
            'buffer_after' => $buffer_after,
            'duration_minutes' => 60, // Default
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
    }

    /**
     * Genera occorrenze basate sulla configurazione.
     * 
     * @param array<string> $times
     * @param array<string> $days
     * @param array $custom_slots
     * @return array<int, array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    private static function generate_occurrences(
        string $frequency,
        array $times,
        array $days,
        array $custom_slots,
        int $duration_minutes,
        DateTimeImmutable $range_start,
        DateTimeImmutable $range_end,
        DateTimeZone $tz
    ): array {
        $occurrences = [];
        $duration_minutes = max(1, $duration_minutes);

        if ('custom' === $frequency) {
            // Slot custom: date + time specifici
            foreach ($custom_slots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $date = isset($slot['date']) ? sanitize_text_field((string) $slot['date']) : '';
                $time = isset($slot['time']) ? sanitize_text_field((string) $slot['time']) : '';
                if ('' === $date || '' === $time) {
                    continue;
                }
                try {
                    $start = new DateTimeImmutable($date . ' ' . $time, $tz);
                    $start = $start->setTimezone(new DateTimeZone('UTC'));
                } catch (Exception $e) {
                    continue;
                }
                $end = $start->add(new DateInterval('PT' . $duration_minutes . 'M'));
                if ($end < $range_start || $start > $range_end) {
                    continue;
                }
                $occurrences[] = [$start, $end];
            }
        } else {
            // daily/weekly: genera slot per ogni giorno nel range
            if (empty($times)) {
                // IMPORTANTE: Se non ci sono times configurati, non possiamo generare slot
                return [];
            }

            $period = new DatePeriod(
                $range_start->setTime(0, 0),
                new DateInterval('P1D'),
                $range_end->setTime(23, 59, 59)->add(new DateInterval('PT1S'))
            );

            foreach ($period as $day) {
                if (!$day instanceof DateTimeImmutable) {
                    $day = DateTimeImmutable::createFromMutable($day);
                }

                // Per weekly, controlla se il giorno corrente è nella lista
                if ('weekly' === $frequency) {
                    $weekday = strtolower($day->format('l'));
                    if (!in_array($weekday, $days, true)) {
                        continue;
                    }
                }

                // Genera uno slot per ogni time configurato
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
                    $end = $start->add(new DateInterval('PT' . $duration_minutes . 'M'));
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

        return $occurrences;
    }

    /**
     * Log debug se WP_DEBUG è abilitato.
     */
    private static function log_debug(int $experience_id, string $message, array $context = []): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $context_str = empty($context) ? '' : ' | Context: ' . wp_json_encode($context);
            error_log(sprintf(
                '[FP_EXP AvailabilityService] Experience %d: %s%s',
                $experience_id,
                $message,
                $context_str
            ));
        }
    }
}
