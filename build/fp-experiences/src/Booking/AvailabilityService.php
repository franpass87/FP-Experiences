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

        // NUOVA LOGICA: Leggi direttamente da _fp_exp_recurrence (formato unico)
        $recurrence = get_post_meta($experience_id, '_fp_exp_recurrence', true);
        
        // Fallback al vecchio formato per retrocompatibilità
        if (! is_array($recurrence) || empty($recurrence)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'FP_EXP AvailabilityService: Experience %d - No recurrence data, trying legacy availability format',
                    $experience_id
                ));
            }
            return self::get_virtual_slots_legacy($experience_id, $start_utc, $end_utc);
        }

        // Estrai dati dalla ricorrenza
        $frequency = isset($recurrence['frequency']) ? sanitize_key((string) $recurrence['frequency']) : 'weekly';
        $recurrence_start_date = isset($recurrence['start_date']) ? sanitize_text_field((string) $recurrence['start_date']) : '';
        $recurrence_end_date = isset($recurrence['end_date']) ? sanitize_text_field((string) $recurrence['end_date']) : '';
        
        // Estrai times e days dai time_slots (nuovo formato semplificato)
        $all_times = [];
        $all_days = [];
        
        // Supporta sia il nuovo formato time_slots che il vecchio time_sets per retrocompatibilità
        $slots_data = isset($recurrence['time_slots']) && is_array($recurrence['time_slots']) 
            ? $recurrence['time_slots'] 
            : (isset($recurrence['time_sets']) && is_array($recurrence['time_sets']) ? $recurrence['time_sets'] : []);
        
        if (!empty($slots_data)) {
            foreach ($slots_data as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                
                // Nuovo formato time_slots: singolo campo 'time'
                if (isset($slot['time'])) {
                    $time_str = trim((string) $slot['time']);
                    if ($time_str !== '' && ! in_array($time_str, $all_times, true)) {
                        $all_times[] = $time_str;
                    }
                }
                // Vecchio formato time_sets: array 'times'
                elseif (isset($slot['times']) && is_array($slot['times'])) {
                    foreach ($slot['times'] as $time) {
                        $time_str = trim((string) $time);
                        if ($time_str !== '' && ! in_array($time_str, $all_times, true)) {
                            $all_times[] = $time_str;
                        }
                    }
                }
                
                // Raccogli giorni (per override specifici del singolo slot)
                if (isset($slot['days']) && is_array($slot['days'])) {
                    foreach ($slot['days'] as $day) {
                        $day_str = trim((string) $day);
                        if ($day_str !== '' && ! in_array($day_str, $all_days, true)) {
                            $all_days[] = $day_str;
                        }
                    }
                }
            }
        }
        
        // Se non ci sono giorni negli slot, usa i giorni globali
        if (empty($all_days) && isset($recurrence['days']) && is_array($recurrence['days'])) {
            $all_days = $recurrence['days'];
        }
        
        // Leggi capacità e buffer dai meta generali (NON dalla ricorrenza)
        // Questo assicura che gli slot virtuali usino i valori corretti
        $availability_meta = get_post_meta($experience_id, '_fp_exp_availability', true);
        $capacity = is_array($availability_meta) && isset($availability_meta['slot_capacity']) 
            ? absint((string) $availability_meta['slot_capacity']) 
            : 0;
        $buffer_before = is_array($availability_meta) && isset($availability_meta['buffer_before_minutes']) 
            ? absint((string) $availability_meta['buffer_before_minutes']) 
            : 0;
        $buffer_after = is_array($availability_meta) && isset($availability_meta['buffer_after_minutes']) 
            ? absint((string) $availability_meta['buffer_after_minutes']) 
            : 0;
        
        // Leggi lead_time dai meta separati (se presenti)
        $lead_time = absint(get_post_meta($experience_id, '_fp_lead_time_hours', true));
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP_EXP AvailabilityService: Experience %d - Reading from _fp_exp_recurrence. Frequency: %s, Times: %d (%s), Days: %d (%s), Capacity: %d',
                $experience_id,
                $frequency,
                count($all_times),
                implode(', ', $all_times),
                count($all_days),
                implode(', ', $all_days),
                $capacity
            ));
        }
        
        // Controllo early return se non ci sono times
        if ($frequency !== 'custom' && empty($all_times)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'FP_EXP AvailabilityService: Experience %d - No times configured in time_slots, cannot generate slots',
                    $experience_id
                ));
            }
            return [];
        }
        
        // Usa le variabili estratte
        $times = $all_times;
        $days = $all_days;
        $custom = []; // Custom slots non più usati nel nuovo formato

        // Durata: se non specificata nelle meta, default 60m.
        $duration_minutes = 60;

        try {
            $range_start = new DateTimeImmutable($start_utc, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $range_start = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        try {
            // Estendi range_end di 1 giorno per catturare tutti gli slot del giorno finale
            // quando convertiti dal timezone locale a UTC (evita problemi con timezone dietro UTC)
            $range_end = new DateTimeImmutable($end_utc . ' 23:59:59', new DateTimeZone('UTC'));
            $range_end = $range_end->add(new DateInterval('P1D'));
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
                // IMPORTANTE: setTime PRIMA della conversione a UTC per evitare shift di giorno
                $rec_start = new DateTimeImmutable($recurrence_start_date . ' 00:00:00', $tz);
                $rec_start_utc = $rec_start->setTimezone(new DateTimeZone('UTC'));
                if ($rec_start_utc > $range_start) {
                    $range_start = $rec_start_utc;
                }
            } catch (Exception $e) {
                // Ignora se la data non è valida
            }
        }
        
        if ('' !== $recurrence_end_date) {
            try {
                // IMPORTANTE: setTime PRIMA della conversione a UTC per evitare shift di giorno
                $rec_end = new DateTimeImmutable($recurrence_end_date . ' 23:59:59', $tz);
                $rec_end_utc = $rec_end->setTimezone(new DateTimeZone('UTC'));
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
                $range_end->setTime(0, 0)->add(new DateInterval('P1D'))
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
        // Filtra gli slot per assicurarsi che appartengano al range originale nel timezone locale
        $slots = [];
        foreach ($occurrences as [$start, $end]) {
            // Verifica che lo slot appartenga al range originale nel timezone locale
            $start_local = $start->setTimezone($tz);
            $start_date_local = $start_local->format('Y-m-d');
            
            // Salta gli slot che cadono dopo la data finale richiesta nel timezone locale
            if ($start_date_local > $end_utc) {
                continue;
            }
            
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
    
    /**
     * Metodo legacy per retrocompatibilità con il vecchio formato _fp_exp_availability.
     * 
     * @deprecated Usa get_virtual_slots() che legge da _fp_exp_recurrence
     * @return array<int, array<string, string|int>>
     */
    private static function get_virtual_slots_legacy(int $experience_id, string $start_utc, string $end_utc): array
    {
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
        $recurrence_start_date = isset($availability['start_date']) ? sanitize_text_field((string) $availability['start_date']) : '';
        $recurrence_end_date = isset($availability['end_date']) ? sanitize_text_field((string) $availability['end_date']) : '';

        if ($frequency !== 'custom' && empty($times)) {
            return [];
        }

        $duration_minutes = 60;

        try {
            $range_start = new DateTimeImmutable($start_utc, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $range_start = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        try {
            // Estendi range_end di 1 giorno per catturare tutti gli slot del giorno finale
            // quando convertiti dal timezone locale a UTC (evita problemi con timezone dietro UTC)
            $range_end = new DateTimeImmutable($end_utc . ' 23:59:59', new DateTimeZone('UTC'));
            $range_end = $range_end->add(new DateInterval('P1D'));
        } catch (Exception $e) {
            $range_end = $range_start;
        }

        if ($range_end < $range_start) {
            $range_end = $range_start;
        }
        
        $tz = new DateTimeZone(wp_timezone_string() ?: 'UTC');
        
        if ('' !== $recurrence_start_date) {
            try {
                // IMPORTANTE: setTime PRIMA della conversione a UTC per evitare shift di giorno
                $rec_start = new DateTimeImmutable($recurrence_start_date . ' 00:00:00', $tz);
                $rec_start_utc = $rec_start->setTimezone(new DateTimeZone('UTC'));
                if ($rec_start_utc > $range_start) {
                    $range_start = $rec_start_utc;
                }
            } catch (Exception $e) {
                // Ignora
            }
        }
        
        if ('' !== $recurrence_end_date) {
            try {
                // IMPORTANTE: setTime PRIMA della conversione a UTC per evitare shift di giorno
                $rec_end = new DateTimeImmutable($recurrence_end_date . ' 23:59:59', $tz);
                $rec_end_utc = $rec_end->setTimezone(new DateTimeZone('UTC'));
                if ($rec_end_utc < $range_end) {
                    $range_end = $rec_end_utc;
                }
            } catch (Exception $e) {
                // Ignora
            }
        }
        
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
            $period = new DatePeriod(
                $range_start->setTime(0, 0),
                new DateInterval('P1D'),
                $range_end->setTime(0, 0)->add(new DateInterval('P1D'))
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

        usort(
            $occurrences,
            static function (array $a, array $b): int {
                return $a[0] <=> $b[0];
            }
        );

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

        // Filtra gli slot per assicurarsi che appartengano al range originale nel timezone locale
        $slots = [];
        foreach ($occurrences as [$start, $end]) {
            // Verifica che lo slot appartenga al range originale nel timezone locale
            $start_local = $start->setTimezone($tz);
            $start_date_local = $start_local->format('Y-m-d');
            
            // Salta gli slot che cadono dopo la data finale richiesta nel timezone locale
            if ($start_date_local > $end_utc) {
                continue;
            }
            
            $start_sql = $start->format('Y-m-d H:i:s');
            $end_sql = $end->format('Y-m-d H:i:s');
            
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


