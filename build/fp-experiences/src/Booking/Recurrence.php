<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use DateTimeImmutable;
use Exception;

use function absint;
use function array_unique;
use function in_array;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function sort;
use function trim;

/**
 * Simplified Recurrence system - only weekly days without start/end dates
 */
final class Recurrence
{
    private const OPEN_ENDED_WINDOW_MONTHS = 12;

    /**
     * Default recurrence configuration (simplified).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'frequency' => 'weekly',
            'duration' => 60,
            'days' => [], // Giorni della settimana: monday, tuesday, etc.
            'time_slots' => [], // Slot orari con override opzionali
        ];
    }

    /**
     * Sanitize recurrence definition (simplified version).
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $raw): array
    {
        $definition = self::defaults();

        // Frequency is always 'weekly' in simplified version
        $definition['frequency'] = 'weekly';

        // Durata predefinita slot
        $definition['duration'] = isset($raw['duration']) ? absint((string) $raw['duration']) : 0;
        if ($definition['duration'] <= 0) {
            $definition['duration'] = 60;
        }

        // Giorni della settimana
        $definition['days'] = [];
        if (isset($raw['days']) && is_array($raw['days'])) {
            foreach ($raw['days'] as $day) {
                $day_key = sanitize_key((string) $day);
                $mapped = self::map_weekday_key($day_key);
                if ($mapped && ! in_array($mapped, $definition['days'], true)) {
                    $definition['days'][] = $mapped;
                }
            }
        }

        // Time slots con override opzionali
        $definition['time_slots'] = self::sanitize_time_slots($raw['time_slots'] ?? []);
        
        // Fallback per retrocompatibilità: se non ci sono time_slots ma ci sono time_sets, converti
        if (empty($definition['time_slots']) && isset($raw['time_sets']) && is_array($raw['time_sets'])) {
            $converted = [];
            foreach ($raw['time_sets'] as $set) {
                if (!is_array($set) || empty($set['times']) || !is_array($set['times'])) {
                    continue;
                }
                
                // Converti ogni time del set in un time_slot
                foreach ($set['times'] as $time) {
                    $time_str = trim((string) $time);
                    if ($time_str === '') {
                        continue;
                    }
                    
                    $converted[] = [
                        'time' => $time_str,
                        'capacity' => isset($set['capacity']) ? absint((string) $set['capacity']) : 0,
                        'buffer_before' => isset($set['buffer_before']) ? absint((string) $set['buffer_before']) : 0,
                        'buffer_after' => isset($set['buffer_after']) ? absint((string) $set['buffer_after']) : 0,
                        'days' => isset($set['days']) && is_array($set['days']) ? $set['days'] : [],
                    ];
                }
            }
            
            if (!empty($converted)) {
                $definition['time_slots'] = self::sanitize_time_slots($converted);
            }
        }

        return $definition;
    }

    /**
     * Determine if a recurrence definition is actionable.
     *
     * @param array<string, mixed> $definition
     */
    public static function is_actionable(array $definition): bool
    {
        // Verifica che ci siano giorni e slot orari
        if (empty($definition['days'])) {
            return false;
        }

        if (empty($definition['time_slots'])) {
            return false;
        }

        return true;
    }

    /**
     * Convert a recurrence definition into slot generation rules (simplified).
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $availability
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build_rules(array $definition, array $availability): array
    {
        if (! self::is_actionable($definition)) {
            return [];
        }

        // Sistema semplificato: generiamo regole per i prossimi N mesi senza date fisse
        $base_rule = [
            'type' => 'weekly',
            'start_date' => 'now',
            'end_date' => '', // Open ended
            'open_ended' => true,
            'open_ended_months' => self::OPEN_ENDED_WINDOW_MONTHS,
            'duration' => isset($definition['duration']) ? absint((string) $definition['duration']) : 60,
            'capacity_total' => isset($availability['slot_capacity']) ? absint((string) $availability['slot_capacity']) : 0,
            'capacity_per_type' => $availability['capacity_per_type'] ?? [],
            'resource_lock' => $availability['resource_lock'] ?? [],
            'price_rules' => $availability['price_rules'] ?? [],
            'buffer_before' => isset($availability['buffer_before_minutes']) ? absint((string) $availability['buffer_before_minutes']) : 0,
            'buffer_after' => isset($availability['buffer_after_minutes']) ? absint((string) $availability['buffer_after_minutes']) : 0,
            'days' => $definition['days'],
        ];

        $rules = [];

        // Crea una regola per ogni time slot
        foreach ($definition['time_slots'] as $slot) {
            if (! is_array($slot) || empty($slot['time'])) {
                continue;
            }

            $time_string = trim((string) $slot['time']);
            if ('' === $time_string) {
                continue;
            }

            $rule = $base_rule;
            $rule['times'] = [$time_string];

            // Override capacità se specificata per questo slot
            if (isset($slot['capacity']) && absint((string) $slot['capacity']) > 0) {
                $rule['capacity_total'] = absint((string) $slot['capacity']);
            }

            // Override buffer se specificati per questo slot
            if (isset($slot['buffer_before']) && absint((string) $slot['buffer_before']) > 0) {
                $rule['buffer_before'] = absint((string) $slot['buffer_before']);
            }

            if (isset($slot['buffer_after']) && absint((string) $slot['buffer_after']) > 0) {
                $rule['buffer_after'] = absint((string) $slot['buffer_after']);
            }

            // Override giorni se specificati per questo slot
            if (isset($slot['days']) && is_array($slot['days']) && ! empty($slot['days'])) {
                $slot_days = [];
                foreach ($slot['days'] as $day) {
                    $mapped = self::map_weekday_key((string) $day);
                    if ($mapped && ! in_array($mapped, $slot_days, true)) {
                        $slot_days[] = $mapped;
                    }
                }
                if (! empty($slot_days)) {
                    sort($slot_days);
                    $rule['days'] = $slot_days;
                }
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Sanitize time slots array.
     *
     * @param array<int, mixed> $time_slots
     *
     * @return array<int, array{time:string,capacity:int,buffer_before:int,buffer_after:int,days:array<int,string>}>
     */
    private static function sanitize_time_slots($time_slots): array
    {
        if (! is_array($time_slots)) {
            return [];
        }

        $sanitized = [];
        foreach ($time_slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $time = isset($slot['time']) ? trim(sanitize_text_field((string) $slot['time'])) : '';
            if ('' === $time) {
                continue;
            }

            $capacity = isset($slot['capacity']) ? absint((string) $slot['capacity']) : 0;
            $buffer_before = isset($slot['buffer_before']) ? absint((string) $slot['buffer_before']) : 0;
            $buffer_after = isset($slot['buffer_after']) ? absint((string) $slot['buffer_after']) : 0;

            $days = [];
            if (isset($slot['days']) && is_array($slot['days'])) {
                foreach ($slot['days'] as $day) {
                    $day_key = sanitize_key((string) $day);
                    $mapped = self::map_weekday_key($day_key);
                    if ($mapped && ! in_array($mapped, $days, true)) {
                        $days[] = $mapped;
                    }
                }
            }

            $sanitized[] = [
                'time' => $time,
                'capacity' => $capacity,
                'buffer_before' => $buffer_before,
                'buffer_after' => $buffer_after,
                'days' => $days,
            ];
        }

        return $sanitized;
    }

    /**
     * Map weekday key to full name.
     */
    private static function map_weekday_key(string $day): ?string
    {
        $map = [
            'mon' => 'monday',
            'tue' => 'tuesday',
            'wed' => 'wednesday',
            'thu' => 'thursday',
            'fri' => 'friday',
            'sat' => 'saturday',
            'sun' => 'sunday',
        ];

        if (isset($map[$day])) {
            return $map[$day];
        }

        $day = strtolower($day);
        if (in_array($day, $map, true)) {
            return $day;
        }

        return null;
    }
}
