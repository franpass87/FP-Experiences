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
?>
<div class="<?php echo $container_class; ?>" data-fp-shortcode="calendar" data-experience="<?php echo esc_attr((string) $experience['id']); ?>">
    <header class="fp-exp-calendar-only__header">
        <h2 class="fp-exp-calendar-only__title"><?php echo esc_html(sprintf(esc_html__('Disponibilità di %s', 'fp-experiences'), $experience['title'])); ?></h2>
    </header>
    <div class="fp-exp-calendar-only__months">
        <?php foreach ($months as $month_key => $month_data) : ?>
            <section class="fp-exp-calendar-only__month" data-month="<?php echo esc_attr((string) $month_key); ?>">
                <header class="fp-exp-calendar-only__month-header"><?php echo esc_html($month_data['month_label'] ?? ''); ?></header>
                <ol class="fp-exp-calendar-only__days">
                    <?php foreach (($month_data['days'] ?? []) as $day => $slots) : ?>
                        <li class="fp-exp-calendar-only__day" data-date="<?php echo esc_attr($day); ?>">
                            <span class="fp-exp-calendar-only__day-label"><?php echo esc_html($day); ?></span>
                            <span class="fp-exp-calendar-only__day-count">
                                <?php echo esc_html(sprintf(esc_html__('%d fasce', 'fp-experiences'), count($slots))); ?>
                            </span>
                            <?php if (! empty($slots)) : ?>
                                <ul class="fp-exp-calendar-only__slots">
                                    <?php foreach ($slots as $slot) : ?>
                                        <li class="fp-exp-calendar-only__slot" data-slot-id="<?php echo esc_attr((string) $slot['id']); ?>">
                                            <time datetime="<?php echo esc_attr($slot['start_iso']); ?>"><?php echo esc_html($slot['time']); ?></time>
                                            <span class="fp-exp-calendar-only__slot-capacity" data-remaining="<?php echo esc_attr((string) $slot['remaining']); ?>">
                                                <?php echo esc_html(sprintf(esc_html__('%d posti rimasti', 'fp-experiences'), (int) $slot['remaining'])); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    </div>
    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema">
            <?php echo wp_kses_post($schema_json); ?>
        </script>
    <?php endif; ?>
</div>
