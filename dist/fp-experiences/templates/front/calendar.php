<?php
/**
 * Standalone availability calendar template.
 *
 * @var array<int, array<string, mixed>> $months
 * @var array<string, mixed>             $experience
 * @var string                           $scope_class
 * @var string                           $schema_json
 */

if (! defined('ABSPATH')) {
    exit;
}


$container_class = 'fp-exp fp-exp-calendar-only ' . esc_attr($scope_class);

// Prepara mappa data => slot minimi per JS
$slots_map = [];
foreach ($months as $m_key => $m_data) {
    if (empty($m_data['days']) || ! is_array($m_data['days'])) {
        continue;
    }
    foreach ($m_data['days'] as $day_key => $day_slots) {
        if (! is_array($day_slots)) {
            continue;
        }
        foreach ($day_slots as $slot) {
            $slots_map[$day_key][] = [
                'id' => (int) ($slot['id'] ?? 0),
                'time' => (string) ($slot['time'] ?? ''),
                'remaining' => (int) ($slot['remaining'] ?? 0),
                'start' => (string) ($slot['start_iso'] ?? ''),
                'end' => (string) ($slot['end_iso'] ?? ''),
                'start_iso' => (string) ($slot['start_iso'] ?? ''),
                'end_iso' => (string) ($slot['end_iso'] ?? ''),
            ];
        }
    }
}

?>
<div class="<?php echo $container_class; ?>" data-fp-shortcode="calendar" data-experience="<?php echo esc_attr((string) $experience['id']); ?>" data-slots="<?php echo esc_attr(wp_json_encode($slots_map)); ?>">
    <header class="fp-exp-calendar-only__header">
        <h2 class="fp-exp-calendar-only__title"><?php echo esc_html(sprintf(esc_html__('Disponibilità di %s', 'fp-experiences'), $experience['title'])); ?></h2>
    </header>
    <div class="fp-exp-calendar" data-show-calendar="1">
        <?php foreach ($months as $month_key => $month_data) :
            $month_label = isset($month_data['month_label']) ? (string) $month_data['month_label'] : '';
            $month_days = isset($month_data['days']) && is_array($month_data['days']) ? $month_data['days'] : [];
            ?>
            <section class="fp-exp-calendar__month" data-month="<?php echo esc_attr((string) $month_key); ?>">
                <header class="fp-exp-calendar__month-header"><?php echo esc_html($month_label); ?></header>
                <?php
                try {
                    $first_of_month = new \DateTimeImmutable($month_key . '-01');
                    $days_in_month = (int) $first_of_month->format('t');
                    $leading = max(0, (int) $first_of_month->format('N') - 1);
                } catch (\Exception $e) {
                    $first_of_month = null;
                    $days_in_month = 31;
                    $leading = 0;
                }

                $week_ref = $first_of_month ?: new \DateTimeImmutable('monday this week');
                $weekdays = [];
                for ($i = 0; $i < 7; $i++) {
                    $weekdays[] = $week_ref->modify('+' . $i . ' days')->format('D');
                }
                ?>
                <div class="fp-exp-calendar__weekdays" aria-hidden="true">
                    <?php foreach ($weekdays as $wd) : ?>
                        <div class="fp-exp-calendar__weekday"><?php echo esc_html($wd); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="fp-exp-calendar__grid">
                    <?php for ($i = 0; $i < $leading; $i++) : ?>
                        <div class="fp-exp-calendar__empty" aria-hidden="true"></div>
                    <?php endfor; ?>
                    <?php for ($day_num = 1; $day_num <= $days_in_month; $day_num++) :
                        $date_key = $month_key . '-' . str_pad((string) $day_num, 2, '0', STR_PAD_LEFT);
                        $day_slots = isset($month_days[$date_key]) && is_array($month_days[$date_key]) ? $month_days[$date_key] : [];
                        $slot_count = count($day_slots);
                        $is_available = $slot_count > 0;
                        ?>
                        <button
                            type="button"
                            class="fp-exp-calendar__day"
                            data-date="<?php echo esc_attr($date_key); ?>"
                            data-available="<?php echo esc_attr($is_available ? '1' : '0'); ?>"
                            <?php if (! $is_available) : ?>disabled aria-disabled="true"<?php else : ?>aria-pressed="false"<?php endif; ?>
                        >
                            <span class="fp-exp-calendar__day-label"><?php echo esc_html((string) $day_num); ?></span>
                            <?php if ($is_available) : ?>
                                <span class="fp-exp-calendar__day-count"><?php echo esc_html(sprintf(esc_html__('%d fasce', 'fp-experiences'), $slot_count)); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="fp-exp-slots" aria-live="polite" data-empty-label="<?php echo esc_attr__('Seleziona una data per vedere le fasce orarie', 'fp-experiences'); ?>">
        <p class="fp-exp-slots__placeholder"><?php echo esc_html__('Seleziona una data per vedere le fasce orarie', 'fp-experiences'); ?></p>
    </div>
    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema">
            <?php echo wp_kses_post($schema_json); ?>
        </script>
    <?php endif; ?>
</div>
