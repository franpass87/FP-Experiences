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

final class Recurrence
{
    private const OPEN_ENDED_WINDOW_MONTHS = 12;

    /**
     * Default recurrence configuration.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'frequency' => 'weekly',
            'start_date' => '',
            'end_date' => '',
            'days' => [],
            'duration' => 60,
            'time_sets' => [],
        ];
    }

    /**
     * Sanitize recurrence definition coming from the admin UI.
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $raw): array
    {
        $definition = self::defaults();

        $frequency = isset($raw['frequency']) ? sanitize_key((string) $raw['frequency']) : 'weekly';
        if (! in_array($frequency, ['daily', 'weekly', 'specific'], true)) {
            $frequency = 'weekly';
        }
        $definition['frequency'] = $frequency;

        $definition['start_date'] = isset($raw['start_date']) ? sanitize_text_field((string) $raw['start_date']) : '';
        $definition['end_date'] = isset($raw['end_date']) ? sanitize_text_field((string) $raw['end_date']) : '';

        $definition['duration'] = isset($raw['duration']) ? absint((string) $raw['duration']) : 0;
        if ($definition['duration'] <= 0) {
            $definition['duration'] = 60;
        }

        $definition['days'] = [];
        if ('weekly' === $definition['frequency'] && isset($raw['days']) && is_array($raw['days'])) {
            foreach ($raw['days'] as $day) {
                $day_key = sanitize_key((string) $day);
                $mapped = self::map_weekday_key($day_key);
                if ($mapped && ! in_array($mapped, $definition['days'], true)) {
                    $definition['days'][] = $mapped;
                }
            }
        }

        $definition['time_sets'] = self::sanitize_time_sets($raw['time_sets'] ?? []);

        if (! self::validate_dates($definition['start_date'], $definition['end_date'])) {
            $definition['start_date'] = '';
            $definition['end_date'] = '';
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
        $times = self::flatten_time_sets($definition['time_sets'] ?? []);

        if (empty($times)) {
            return false;
        }

        // Removed check for empty 'days' for weekly recurrences to align with admin UI changes.
        return true;
    }

    /**
     * Convert a recurrence definition into slot generation rules.
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

        $times = self::flatten_time_sets($definition['time_sets']);

        if (empty($times)) {
            return [];
        }

        $start_date = $definition['start_date'] ?: 'now';
        $open_ended = '' === $definition['end_date'] && 'specific' !== $definition['frequency'];
        $end_date = $open_ended ? '' : ($definition['end_date'] ?: $start_date);

        $base_rule = [
            'type' => $definition['frequency'],
            'start_date' => $start_date,
            'end_date' => $end_date,
            'duration' => isset($definition['duration']) ? absint((string) $definition['duration']) : 60,
            'capacity_total' => isset($availability['slot_capacity']) ? absint((string) $availability['slot_capacity']) : 0,
            'capacity_per_type' => $availability['capacity_per_type'] ?? [],
            'resource_lock' => $availability['resource_lock'] ?? [],
            'price_rules' => $availability['price_rules'] ?? [],
            'buffer_before' => isset($availability['buffer_before_minutes']) ? absint((string) $availability['buffer_before_minutes']) : 0,
            'buffer_after' => isset($availability['buffer_after_minutes']) ? absint((string) $availability['buffer_after_minutes']) : 0,
        ];

        if ($open_ended) {
            $base_rule['open_ended'] = true;
            $base_rule['open_ended_months'] = self::OPEN_ENDED_WINDOW_MONTHS;
        }

        if ($base_rule['duration'] <= 0) {
            $base_rule['duration'] = 60;
        }

        $rules = [];

        foreach ($definition['time_sets'] as $set) {
            if (! is_array($set) || empty($set['times']) || ! is_array($set['times'])) {
                continue;
            }

            $times = [];
            foreach ($set['times'] as $time) {
                $time_string = trim((string) $time);
                if ('' === $time_string) {
                    continue;
                }
                $times[] = $time_string;
            }

            if (empty($times)) {
                continue;
            }

            $times = array_values(array_unique($times));
            sort($times);

            $rule = $base_rule;
            $rule['times'] = $times;
            
            // Usa la capienza del time set se specificata e > 0, altrimenti usa quella predefinita
            $set_capacity = isset($set['capacity']) ? absint((string) $set['capacity']) : 0;
            if ($set_capacity > 0) {
                $rule['capacity_total'] = $set_capacity;
            }
            // Se la capienza del time set è 0 o vuota, mantieni quella predefinita dal base_rule
            if (isset($set['buffer_before'])) {
                $rule['buffer_before'] = absint((string) $set['buffer_before']);
            }
            if (isset($set['buffer_after'])) {
                $rule['buffer_after'] = absint((string) $set['buffer_after']);
            }
            if (isset($set['duration']) && absint((string) $set['duration']) > 0) {
                $rule['duration'] = absint((string) $set['duration']);
            }

            if ('weekly' === $definition['frequency']) {
                $set_days = [];
                if (isset($set['days']) && is_array($set['days'])) {
                    foreach ($set['days'] as $day) {
                        $mapped = self::map_weekday_key((string) $day);
                        if ($mapped && ! in_array($mapped, $set_days, true)) {
                            $set_days[] = $mapped;
                        }
                    }
                }

                if (empty($set_days)) {
                    $set_days = $definition['days'];
                }

                if (empty($set_days)) {
                    continue;
                }

                sort($set_days);
                $rule['days'] = $set_days;
            } else {
                $rule['days'] = [];
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Flatten a collection of time sets into a simple times array.
     *
     * @param array<int, array<string, mixed>> $time_sets
     *
     * @return array<int, string>
     */
    public static function flatten_time_sets(array $time_sets): array
    {
        $times = [];
        foreach ($time_sets as $set) {
            if (! is_array($set) || empty($set['times']) || ! is_array($set['times'])) {
                continue;
            }

            foreach ($set['times'] as $time) {
                $time_string = trim((string) $time);
                if ('' === $time_string) {
                    continue;
                }
                $times[] = $time_string;
            }
        }

        $times = array_values(array_unique($times));
        sort($times);

        return $times;
    }

    /**
     * @param array<int, mixed> $time_sets
     *
     * @return array<int, array{label:string,times:array<int,string>,days:array<int,string>,capacity:int,buffer_before:int,buffer_after:int}>
     */
    private static function sanitize_time_sets($time_sets): array
    {
        if (! is_array($time_sets)) {
            return [];
        }

        $sanitized = [];
        foreach ($time_sets as $set) {
            if (! is_array($set)) {
                continue;
            }

            $label = isset($set['label']) ? sanitize_text_field((string) $set['label']) : '';
            $times = [];
            $days = [];
            $capacity = isset($set['capacity']) ? absint((string) $set['capacity']) : 0;
            $buffer_before = isset($set['buffer_before']) ? absint((string) $set['buffer_before']) : 0;
            $buffer_after = isset($set['buffer_after']) ? absint((string) $set['buffer_after']) : 0;

            if (isset($set['times']) && is_array($set['times'])) {
                foreach ($set['times'] as $time) {
                    $time_string = trim(sanitize_text_field((string) $time));
                    if ('' === $time_string) {
                        continue;
                    }
                    $times[] = $time_string;
                }
            }

            if (isset($set['days']) && is_array($set['days'])) {
                foreach ($set['days'] as $day) {
                    $day_key = sanitize_key((string) $day);
                    $mapped = self::map_weekday_key($day_key);
                    if ($mapped && ! in_array($mapped, $days, true)) {
                        $days[] = $mapped;
                    }
                }
            }

            if (empty($times)) {
                continue;
            }

            $times = array_values(array_unique($times));
            sort($times);

            $sanitized[] = [
                'label' => $label,
                'times' => $times,
                'days' => $days,
                'capacity' => $capacity,
                'buffer_before' => $buffer_before,
                'buffer_after' => $buffer_after,
            ];
        }

        return $sanitized;
    }

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

    private static function validate_dates(string $start, string $end): bool
    {
        if ('' === $start && '' === $end) {
            return true;
        }

        try {
            $start_dt = new DateTimeImmutable($start ?: 'now');
            $end_dt = new DateTimeImmutable($end ?: $start ?: 'now');
        } catch (Exception $exception) {
            return false;
        }

        return $start_dt <= $end_dt;
    }
}
