<?php
/**
 * Experience detail template.
 *
 * @var array<string, mixed> $experience
 * @var array<int, array<string, string>> $gallery
 * @var array<int, array<string, string>> $badges
 * @var array<int, string> $highlights
 * @var array<int, string> $inclusions
 * @var array<int, string> $exclusions
 * @var array<int, string> $what_to_bring
 * @var array<int, string>|string $notes
 * @var string $policy
 * @var array<int, array<string, string>> $faq
 * @var array<int, array<string, mixed>> $reviews
 * @var array<string, bool> $sections
 * @var array<int, array<string, string>> $navigation
 * @var array{primary: ?array<string, mixed>, alternatives: array<int, array<string, mixed>>} $meeting_points
 * @var bool $sticky_widget
 * @var string $widget_html
 * @var string $schema_json
 * @var string $data_layer
 * @var string $scope_class
 */

if (! defined('ABSPATH')) {
    exit;
}

$scope_class = $scope_class ?? '';
$container_classes = trim('fp-exp fp-exp-page ' . $scope_class);

$navigation = isset($navigation) && is_array($navigation) ? $navigation : [];
$has_navigation = ! empty($navigation);
$has_highlights = ! empty($highlights);
$has_inclusions = ! empty($inclusions) || ! empty($exclusions);
$has_meeting = isset($meeting_points['primary']) && is_array($meeting_points['primary']);
$has_extras = ! empty($what_to_bring) || ! empty($notes) || ! empty($policy);
$has_faq = ! empty($faq);
$has_reviews = ! empty($reviews);

$cta_label = esc_html__('Controlla disponibilità', 'fp-experiences');

?>
<section class="<?php echo esc_attr($container_classes); ?>" data-fp-shortcode="experience">
    <?php if (! empty($data_layer)) : ?>
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(<?php echo wp_kses_post($data_layer); ?>);
        </script>
    <?php endif; ?>

    <div class="fp-exp-page__layout" data-fp-page>
        <main class="fp-exp-page__main">
            <?php if (! empty($sections['hero'])) : ?>
                <header class="fp-exp-page__hero" id="fp-exp-section-hero" data-fp-section="hero">
                    <div class="fp-exp-page__hero-media" aria-hidden="<?php echo empty($gallery) ? 'true' : 'false'; ?>">
                        <?php if (! empty($gallery)) : ?>
                            <div class="fp-exp-gallery" data-fp-gallery>
                                <?php foreach ($gallery as $index => $image) : ?>
                                    <figure class="fp-exp-gallery__item" data-index="<?php echo esc_attr((string) $index); ?>">
                                        <img
                                            src="<?php echo esc_url($image['url']); ?>"
                                            <?php if (! empty($image['srcset'])) : ?>srcset="<?php echo esc_attr($image['srcset']); ?>"<?php endif; ?>
                                            <?php if (! empty($image['width'])) : ?>width="<?php echo esc_attr((string) $image['width']); ?>"<?php endif; ?>
                                            <?php if (! empty($image['height'])) : ?>height="<?php echo esc_attr((string) $image['height']); ?>"<?php endif; ?>
                                            alt="<?php echo esc_attr($experience['title']); ?>"
                                            loading="lazy"
                                        />
                                    </figure>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="fp-exp-gallery fp-exp-gallery--placeholder">
                                <span aria-hidden="true"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="fp-exp-page__hero-body">
                        <div class="fp-exp-page__eyebrow">
                            <span class="fp-exp-page__badge">FP Experiences</span>
                        </div>
                        <h1 class="fp-exp-page__title"><?php echo esc_html($experience['title']); ?></h1>
                        <?php if (! empty($badges)) : ?>
                            <ul class="fp-exp-page__badges" role="list">
                                <?php foreach ($badges as $badge) : ?>
                                    <li class="fp-exp-page__badge-item">
                                        <span class="fp-exp-icon fp-exp-icon--<?php echo esc_attr($badge['icon']); ?>" aria-hidden="true">
                                            <?php if ('clock' === $badge['icon']) : ?>
                                                <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.59 2.12 2.12-1.41 1.41-2.83-2.83V7h2.12Z"/></svg>
                                            <?php elseif ('language' === $badge['icon']) : ?>
                                                <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M3 5h18v2H3Zm10 4h8v2h-5.18a7.87 7.87 0 0 1-1.35 3.24l3.14 3.14-1.41 1.41-3.48-3.48A9.85 9.85 0 0 1 10 19.93V22H8v-2.07A9.94 9.94 0 0 1 2 12h2a8 8 0 0 0 4 6.92A7.87 7.87 0 0 0 9.18 11H4V9h6V7h2v2h1Z"/></svg>
                                            <?php else : ?>
                                                <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 12.88 9.17 10H5a3 3 0 0 0-3 3v7h6v-4h2v4h6v-7a3 3 0 0 0-3-3h-1.17Zm9-2.88a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/></svg>
                                            <?php endif; ?>
                                        </span>
                                        <span class="fp-exp-page__badge-label"><?php echo esc_html($badge['label']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (! empty($experience['summary'])) : ?>
                            <p class="fp-exp-page__summary"><?php echo esc_html($experience['summary']); ?></p>
                        <?php endif; ?>
                        <div class="fp-exp-page__hero-cta">
                            <a
                                href="#fp-exp-widget"
                                class="fp-exp-button"
                                data-fp-scroll="widget"
                                data-fp-cta="hero"
                            >
                                <?php echo $cta_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                        </div>
                    </div>
                </header>
            <?php endif; ?>

            <?php if ($has_navigation) : ?>
                <nav class="fp-exp-page__nav" aria-label="<?php esc_attr_e('Experience sections', 'fp-experiences'); ?>">
                    <ul class="fp-exp-page__nav-list">
                        <?php foreach ($navigation as $item) : ?>
                            <li class="fp-exp-page__nav-item">
                                <button
                                    type="button"
                                    class="fp-exp-page__nav-button"
                                    data-fp-scroll="<?php echo esc_attr($item['id']); ?>"
                                >
                                    <?php echo esc_html($item['label']); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <?php if (! empty($sections['highlights']) && $has_highlights) : ?>
                <section class="fp-exp-section" id="fp-exp-section-highlights" data-fp-section="highlights">
                    <h2 class="fp-exp-section__title"><?php esc_html_e('Highlights', 'fp-experiences'); ?></h2>
                    <ul class="fp-exp-list" role="list">
                        <?php foreach ($highlights as $highlight) : ?>
                            <li class="fp-exp-list__item"><?php echo esc_html($highlight); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['inclusions']) && $has_inclusions) : ?>
                <section class="fp-exp-section" id="fp-exp-section-inclusions" data-fp-section="inclusions">
                    <div class="fp-exp-section__header">
                        <h2 class="fp-exp-section__title"><?php esc_html_e('What\'s included', 'fp-experiences'); ?></h2>
                        <?php if (! empty($exclusions)) : ?>
                            <p class="fp-exp-section__subtitle"><?php esc_html_e('and what\'s not', 'fp-experiences'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="fp-exp-columns">
                        <?php if (! empty($inclusions)) : ?>
                            <div class="fp-exp-column">
                                <h3 class="fp-exp-column__title"><?php esc_html_e('Included', 'fp-experiences'); ?></h3>
                                <ul class="fp-exp-list" role="list">
                                    <?php foreach ($inclusions as $item) : ?>
                                        <li class="fp-exp-list__item">
                                            <span class="fp-exp-icon fp-exp-icon--check" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path fill="currentColor" d="M9.75 18.25 3.5 12l1.41-1.41 4.84 4.84 9.34-9.34L20.5 7.5Z"/></svg>
                                            </span>
                                            <span><?php echo esc_html($item); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($exclusions)) : ?>
                            <div class="fp-exp-column">
                                <h3 class="fp-exp-column__title"><?php esc_html_e('Not included', 'fp-experiences'); ?></h3>
                                <ul class="fp-exp-list" role="list">
                                    <?php foreach ($exclusions as $item) : ?>
                                        <li class="fp-exp-list__item">
                                            <span class="fp-exp-icon fp-exp-icon--cross" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path fill="currentColor" d="m18.3 5.71 1.42 1.42-5.3 5.29 5.3 5.29-1.42 1.42-5.29-5.3-5.29 5.3-1.42-1.42 5.3-5.29-5.3-5.29 1.42-1.42 5.29 5.3Z"/></svg>
                                            </span>
                                            <span><?php echo esc_html($item); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['meeting']) && $has_meeting) : ?>
                <section class="fp-exp-section" id="fp-exp-section-meeting" data-fp-section="meeting">
                    <h2 class="fp-exp-section__title"><?php esc_html_e('Meeting point', 'fp-experiences'); ?></h2>
                    <?php
                    $primary = $meeting_points['primary'];
                    $alternatives = $meeting_points['alternatives'];
                    include __DIR__ . '/meeting-points.php';
                    ?>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['extras']) && $has_extras) : ?>
                <section class="fp-exp-section" id="fp-exp-section-extras" data-fp-section="extras">
                    <h2 class="fp-exp-section__title"><?php esc_html_e('Good to know', 'fp-experiences'); ?></h2>
                    <div class="fp-exp-columns fp-exp-columns--stack">
                        <?php if (! empty($what_to_bring)) : ?>
                            <div class="fp-exp-column">
                                <h3 class="fp-exp-column__title"><?php esc_html_e('What to bring', 'fp-experiences'); ?></h3>
                                <ul class="fp-exp-list" role="list">
                                    <?php foreach ($what_to_bring as $item) : ?>
                                        <li class="fp-exp-list__item"><?php echo esc_html($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($notes)) : ?>
                            <div class="fp-exp-column">
                                <h3 class="fp-exp-column__title"><?php esc_html_e('Notes', 'fp-experiences'); ?></h3>
                                <?php if (is_array($notes)) : ?>
                                    <ul class="fp-exp-list" role="list">
                                        <?php foreach ($notes as $note) : ?>
                                            <li class="fp-exp-list__item"><?php echo esc_html($note); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <p class="fp-exp-paragraph"><?php echo esc_html($notes); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($policy)) : ?>
                            <div class="fp-exp-column">
                                <h3 class="fp-exp-column__title"><?php esc_html_e('Cancellation policy', 'fp-experiences'); ?></h3>
                                <div class="fp-exp-richtext"><?php echo wp_kses_post($policy); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['faq']) && $has_faq) : ?>
                <section class="fp-exp-section" id="fp-exp-section-faq" data-fp-section="faq">
                    <h2 class="fp-exp-section__title"><?php esc_html_e('Frequently asked questions', 'fp-experiences'); ?></h2>
                    <div class="fp-exp-accordion" data-fp-accordion>
                        <?php foreach ($faq as $index => $item) :
                            $button_id = $scope_class . '-faq-' . $index;
                            $panel_id = $scope_class . '-faq-panel-' . $index;
                            ?>
                            <div class="fp-exp-accordion__item">
                                <h3 class="fp-exp-accordion__heading">
                                    <button
                                        type="button"
                                        class="fp-exp-accordion__trigger"
                                        id="<?php echo esc_attr($button_id); ?>"
                                        aria-expanded="false"
                                        aria-controls="<?php echo esc_attr($panel_id); ?>"
                                        data-fp-accordion-trigger
                                    >
                                        <span class="fp-exp-accordion__label"><?php echo esc_html($item['question']); ?></span>
                                        <span class="fp-exp-accordion__icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 5v14m-7-7h14"/></svg>
                                        </span>
                                    </button>
                                </h3>
                                <div
                                    class="fp-exp-accordion__panel"
                                    id="<?php echo esc_attr($panel_id); ?>"
                                    role="region"
                                    aria-labelledby="<?php echo esc_attr($button_id); ?>"
                                    hidden
                                >
                                    <div class="fp-exp-accordion__content"><?php echo wp_kses_post($item['answer']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['reviews']) && $has_reviews) : ?>
                <section class="fp-exp-section" id="fp-exp-section-reviews" data-fp-section="reviews">
                    <h2 class="fp-exp-section__title"><?php esc_html_e('Traveler reviews', 'fp-experiences'); ?></h2>
                    <ul class="fp-exp-reviews" role="list">
                        <?php foreach ($reviews as $review) : ?>
                            <li class="fp-exp-review" data-fp-review>
                                <header class="fp-exp-review__header">
                                    <strong class="fp-exp-review__author"><?php echo esc_html($review['author'] ?? ''); ?></strong>
                                    <?php if (! empty($review['rating'])) : ?>
                                        <span class="fp-exp-review__rating" aria-label="<?php echo esc_attr(sprintf(esc_html__('%s out of 5', 'fp-experiences'), number_format_i18n((float) $review['rating'], 1))); ?>">
                                            <?php echo esc_html(number_format_i18n((float) $review['rating'], 1)); ?> ★
                                        </span>
                                    <?php endif; ?>
                                    <?php if (! empty($review['date'])) : ?>
                                        <?php
                                        $timestamp = strtotime((string) $review['date']);
                                        if ($timestamp) :
                                            ?>
                                            <time class="fp-exp-review__date" datetime="<?php echo esc_attr(gmdate('Y-m-d', $timestamp)); ?>">
                                                <?php echo esc_html(date_i18n(get_option('date_format', 'F j, Y'), $timestamp)); ?>
                                            </time>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </header>
                                <div class="fp-exp-review__content"><?php echo wp_kses_post($review['content'] ?? ''); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (! empty($schema_json)) : ?>
                <script type="application/ld+json" class="fp-exp-schema">
                    <?php echo wp_kses_post($schema_json); ?>
                </script>
            <?php endif; ?>
        </main>

        <aside class="fp-exp-page__aside" data-fp-sticky-container>
            <div class="fp-exp-page__widget" id="fp-exp-widget">
                <?php echo $widget_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </aside>
    </div>

    <?php if ($sticky_widget) : ?>
        <div class="fp-exp-page__sticky-bar" data-fp-sticky-bar>
            <button type="button" class="fp-exp-page__sticky-button" data-fp-scroll="widget" data-fp-cta="sticky">
                <?php echo $cta_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    <?php endif; ?>
</section>
