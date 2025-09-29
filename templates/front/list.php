<?php
/**
 * Experiences list template.
 *
 * @var array<int, array<string, mixed>> $experiences
 * @var array<string, mixed>             $filters
 * @var string                           $scope_class
 * @var string                           $schema_json
 */

if (! defined('ABSPATH')) {
    exit;
}


$container_classes = 'fp-exp fp-exp-list ' . esc_attr($scope_class);
?>
<section class="<?php echo $container_classes; ?>" data-fp-shortcode="list">
    <header class="fp-exp-list__header">
        <h2 class="fp-exp-list__title"><?php echo esc_html__('Experiences', 'fp-experiences'); ?></h2>
        <?php if (! empty($filters)) : ?>
            <div class="fp-exp-list__filters" role="region" aria-label="<?php echo esc_attr__('Filters', 'fp-experiences'); ?>">
                <?php if (! empty($filters['theme'])) : ?>
                    <div class="fp-exp-filter fp-exp-filter--theme">
                        <span class="fp-exp-filter__label"><?php echo esc_html__('Theme', 'fp-experiences'); ?></span>
                        <ul class="fp-exp-filter__choices">
                            <?php foreach ($filters['theme'] as $theme) : ?>
                                <li class="fp-exp-filter__choice" data-filter-type="theme" data-filter-value="<?php echo esc_attr($theme); ?>"><?php echo esc_html($theme); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (! empty($filters['duration'])) : ?>
                    <div class="fp-exp-filter fp-exp-filter--duration">
                        <span class="fp-exp-filter__label"><?php echo esc_html__('Duration', 'fp-experiences'); ?></span>
                        <ul class="fp-exp-filter__choices">
                            <?php foreach ($filters['duration'] as $duration_label) : ?>
                                <li class="fp-exp-filter__choice" data-filter-type="duration" data-filter-value="<?php echo esc_attr($duration_label); ?>"><?php echo esc_html($duration_label); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (! empty($filters['price']['min']) && ! empty($filters['price']['max'])) : ?>
                    <div class="fp-exp-filter fp-exp-filter--price" data-price-min="<?php echo esc_attr((string) $filters['price']['min']); ?>" data-price-max="<?php echo esc_attr((string) $filters['price']['max']); ?>">
                        <span class="fp-exp-filter__label"><?php echo esc_html__('Price range', 'fp-experiences'); ?></span>
                        <div class="fp-exp-filter__range">
                            <span class="fp-exp-filter__range-value" data-role="min">€<?php echo esc_html(number_format_i18n((float) $filters['price']['min'], 2)); ?></span>
                            <span class="fp-exp-filter__range-separator" aria-hidden="true">—</span>
                            <span class="fp-exp-filter__range-value" data-role="max">€<?php echo esc_html(number_format_i18n((float) $filters['price']['max'], 2)); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>
    <div class="fp-exp-list__grid" role="list">
        <?php foreach ($experiences as $experience) : ?>
            <article
                class="fp-exp-card"
                role="listitem"
                data-experience-id="<?php echo esc_attr((string) $experience['id']); ?>"
                data-price-from="<?php echo esc_attr((string) ($experience['price_from'] ?? 0)); ?>"
                data-themes="<?php echo esc_attr(implode('|', $experience['terms']['theme'] ?? [])); ?>"
                data-duration-label="<?php echo esc_attr($experience['duration_label'] ?? ''); ?>"
            >
                <a class="fp-exp-card__link" href="<?php echo esc_url($experience['permalink']); ?>">
                    <div class="fp-exp-card__media" aria-hidden="true">
                        <?php if (! empty($experience['thumbnail'])) : ?>
                            <span class="fp-exp-card__thumbnail" style="background-image:url('<?php echo esc_url($experience['thumbnail']); ?>');"></span>
                        <?php else : ?>
                            <span class="fp-exp-card__thumbnail fp-exp-card__thumbnail--placeholder"></span>
                        <?php endif; ?>
                        <?php if (! empty($experience['price_from'])) : ?>
                            <span class="fp-exp-card__price-tag"><?php echo esc_html__('From', 'fp-experiences'); ?> €<?php echo esc_html(number_format_i18n((float) $experience['price_from'], 2)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="fp-exp-card__body">
                        <h3 class="fp-exp-card__title"><?php echo esc_html($experience['title']); ?></h3>
                        <?php if (! empty($experience['short_description'])) : ?>
                            <p class="fp-exp-card__excerpt"><?php echo esc_html($experience['short_description']); ?></p>
                        <?php endif; ?>
                        <?php if (! empty($experience['highlights'])) : ?>
                            <ul class="fp-exp-card__highlights">
                                <?php foreach ($experience['highlights'] as $highlight) : ?>
                                    <li><?php echo esc_html($highlight); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <dl class="fp-exp-card__meta">
                            <?php if (! empty($experience['duration'])) : ?>
                                <div>
                                    <dt><?php echo esc_html__('Duration', 'fp-experiences'); ?></dt>
                                    <dd><?php echo esc_html(sprintf(__('About %d minutes', 'fp-experiences'), (int) $experience['duration'])); ?></dd>
                                </div>
                            <?php endif; ?>
                            <?php if (! empty($experience['languages'])) : ?>
                                <div>
                                    <dt><?php echo esc_html__('Languages', 'fp-experiences'); ?></dt>
                                    <dd>
                                        <?php echo esc_html(implode(', ', $experience['languages'])); ?>
                                    </dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </a>
                <footer class="fp-exp-card__footer">
                    <a class="fp-exp-card__cta" href="<?php echo esc_url($experience['permalink']); ?>">
                        <?php echo esc_html__('View availability', 'fp-experiences'); ?>
                    </a>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema">
            <?php echo wp_kses_post($schema_json); ?>
        </script>
    <?php endif; ?>
</section>
