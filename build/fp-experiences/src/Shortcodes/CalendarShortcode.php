<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Utils\Theme;
use WP_Error;
use WP_Post;

use function absint;
use function esc_html__;
use function get_post;
use function wp_json_encode;

final class CalendarShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_calendar';

    protected string $template = 'front/calendar.php';

    protected array $defaults = [
        'id' => '',
        'months' => '1',
        'preset' => '',
        'mode' => '',
        'primary' => '',
        'secondary' => '',
        'accent' => '',
        'background' => '',
        'surface' => '',
        'text' => '',
        'muted' => '',
        'success' => '',
        'warning' => '',
        'danger' => '',
        'radius' => '',
        'shadow' => '',
        'font' => '',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $experience_id = absint($attributes['id']);
        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_calendar_invalid', esc_html__('Missing experience ID.', 'fp-experiences'));
        }

        $post = get_post($experience_id);
        if (! $post instanceof WP_Post || 'fp_experience' !== $post->post_type) {
            return new WP_Error('fp_exp_calendar_not_found', esc_html__('Experience not found.', 'fp-experiences'));
        }

        $theme = Theme::resolve_palette([
            'preset' => (string) $attributes['preset'],
            'mode' => (string) $attributes['mode'],
            'primary' => (string) $attributes['primary'],
            'secondary' => (string) $attributes['secondary'],
            'accent' => (string) $attributes['accent'],
            'background' => (string) $attributes['background'],
            'surface' => (string) $attributes['surface'],
            'text' => (string) $attributes['text'],
            'muted' => (string) $attributes['muted'],
            'success' => (string) $attributes['success'],
            'warning' => (string) $attributes['warning'],
            'danger' => (string) $attributes['danger'],
            'radius' => (string) $attributes['radius'],
            'shadow' => (string) $attributes['shadow'],
            'font' => (string) $attributes['font'],
        ]);

        // Genera la struttura dei mesi per il calendario
        $months_count = absint($attributes['months']);
        if ($months_count <= 0 || $months_count > 3) {
            $months_count = 1; // Default a 1 mese per performance
        }
        $months = $this->generate_calendar_months($experience_id, $months_count);

        // Schema disabilitato per il calendario on-the-fly
        $schema = [];

        return [
            'theme' => $theme,
            'experience' => [
                'id' => $experience_id,
                'title' => $post->post_title,
            ],
            'months' => $months,
            'schema_json' => $schema ? wp_json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'TouristTrip',
                'name' => $post->post_title,
                'offers' => $schema,
            ]) : '',
        ];
    }

    /**
     * Generate calendar months structure with availability data.
     *
     * @param int $experience_id
     * @param int $count
     *
     * @return array<string, array<string, mixed>>
     */
    private function generate_calendar_months(int $experience_id, int $count = 1): array
    {
        // Verifica veloce se ci sono dati di disponibilità configurati
        $availability = get_post_meta($experience_id, '_fp_exp_availability', true);
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP_EXP Calendar: Experience %d - Availability check: %s, Times: %s',
                $experience_id,
                is_array($availability) ? 'array' : 'not array',
                isset($availability['times']) ? json_encode($availability['times']) : 'not set'
            ));
        }
        
        if (! is_array($availability) || empty($availability['times'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'FP_EXP Calendar: Experience %d - No availability times configured, returning empty calendar',
                    $experience_id
                ));
            }
            return []; // Non ci sono slot configurati, ritorna vuoto
        }
        
        $months = [];
        $timezone = wp_timezone();
        $now = new \DateTimeImmutable('now', $timezone);

        for ($i = 0; $i < $count; $i++) {
            $date = $now->modify("+{$i} months");
            $month_key = $date->format('Y-m');
            $month_label = $date->format('F Y');

            // Ottieni gli slot per questo mese
            $start_of_month = $date->modify('first day of this month')->setTime(0, 0, 0);
            $end_of_month = $date->modify('last day of this month')->setTime(23, 59, 59);

            $start_utc = $start_of_month->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
            $end_utc = $end_of_month->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

            // Usa AvailabilityService per ottenere gli slot virtuali
            $slots = \FP_Exp\Booking\AvailabilityService::get_virtual_slots($experience_id, $start_utc, $end_utc);

            // Debug log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'FP_EXP Calendar: Experience %d, Month %s - Generated %d virtual slots (range: %s to %s)',
                    $experience_id,
                    $month_key,
                    count($slots),
                    $start_utc,
                    $end_utc
                ));
            }

            // Raggruppa gli slot per giorno
            $days = [];
            foreach ($slots as $slot) {
                if (empty($slot['start'])) {
                    continue;
                }

                try {
                    $slot_start = new \DateTimeImmutable($slot['start'], new \DateTimeZone('UTC'));
                    $slot_start_local = $slot_start->setTimezone($timezone);
                    $day_key = $slot_start_local->format('Y-m-d');

                    if (! isset($days[$day_key])) {
                        $days[$day_key] = [];
                    }

                    $slot_end = new \DateTimeImmutable($slot['end'], new \DateTimeZone('UTC'));
                    $slot_end_local = $slot_end->setTimezone($timezone);

                    $days[$day_key][] = [
                        'id' => 0, // Virtual slot
                        'time' => $slot_start_local->format('H:i'),
                        'remaining' => (int) ($slot['capacity_remaining'] ?? 0),
                        'start_iso' => $slot_start->format('c'),
                        'end_iso' => $slot_end->format('c'),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            $months[$month_key] = [
                'month_label' => $month_label,
                'days' => $days,
            ];
        }

        return $months;
    }
}
