<?php
/**
 * Experience detail template.
 *
 * @var array<string, mixed> $experience
 * @var array<int, array<string, string>> $gallery
 * @var string $gallery_video_url
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
 * @var array{primary: ?array<string, mixed>, alternatives: array<int, array<string, mixed>>} $meeting_points
 * @var bool $sticky_widget
 * @var string $widget_html
 * @var string $schema_json
 * @var string $data_layer
 * @var string $scope_class
 * @var array<string, mixed> $overview
 * @var string $children_rules
 */

if (! defined('ABSPATH')) {
    exit;
}

$scope_class = $scope_class ?? '';
$language_sprite = \FP_Exp\Utils\LanguageHelper::get_sprite_url();
$layout = $layout ?? [
    'container' => 'boxed',
    'max_width' => null,
    'gutter' => null,
    'sidebar' => 'right',
];

$layout_container = $layout['container'] ?? 'boxed';
$layout_max_width = isset($layout['max_width']) ? (int) $layout['max_width'] : 0;
$layout_gutter = isset($layout['gutter']) ? (int) $layout['gutter'] : 0;
$sidebar_position = $layout['sidebar'] ?? 'right';

$wrapper_classes = ['fp-exp', 'fp-layout', 'fp-exp-page'];

if ('' !== $scope_class) {
    $wrapper_classes[] = $scope_class;
}

if ('full' === $layout_container) {
    $wrapper_classes[] = 'is-full';
}

if ('none' === $sidebar_position) {
    $wrapper_classes[] = 'is-single';
}

if ('left' === $sidebar_position) {
    $wrapper_classes[] = 'is-sidebar-left';
}

$layout_style = [];

if ($layout_max_width > 0) {
    $layout_style[] = '--fp-exp-max:' . $layout_max_width . 'px';
}

if ($layout_gutter > 0) {
    $layout_style[] = '--fp-exp-gutter:' . $layout_gutter . 'px';
}

$layout_style_attr = empty($layout_style) ? '' : implode(';', $layout_style);

$sections = isset($sections) && is_array($sections) ? $sections : [];
$has_highlights = ! empty($highlights);
$has_inclusions = ! empty($inclusions) || ! empty($exclusions);
$has_meeting = isset($meeting_points['primary']) && is_array($meeting_points['primary']);
$children_rules = isset($children_rules) ? trim((string) $children_rules) : '';
$has_extras = ! empty($what_to_bring) || ! empty($notes) || ! empty($policy) || '' !== $children_rules;
$has_faq = ! empty($faq);
$has_reviews = ! empty($reviews);

$sidebar_data = in_array($sidebar_position, ['left', 'none'], true) ? $sidebar_position : 'right';

$gift = isset($gift) && is_array($gift) ? $gift : [];
$gift_enabled = ! empty($gift['enabled']);
$gift_config = [
    'experienceId' => isset($gift['experience_id']) ? (int) $gift['experience_id'] : 0,
    'experienceTitle' => isset($gift['experience_title']) ? (string) $gift['experience_title'] : '',
    'validityDays' => isset($gift['validity_days']) ? (int) $gift['validity_days'] : 0,
    'redeemPage' => isset($gift['redeem_page']) ? (string) $gift['redeem_page'] : '',
    'currency' => isset($gift['currency']) ? (string) $gift['currency'] : get_option('woocommerce_currency', 'EUR'),
];
$gift_addons = isset($gift['addons']) && is_array($gift['addons']) ? $gift['addons'] : [];

$primary_image = ! empty($gallery) ? $gallery[0] : null;
$gallery_items = array_values(array_filter(
    $gallery,
    static fn ($image) => is_array($image) && ! empty($image['url'])
));

if ($primary_image) {
    // Remove the primary image from gallery_items by comparing 'url'
    $primary_image_url = isset($primary_image['url']) ? (string) $primary_image['url'] : '';
    $gallery_items = array_values(array_filter(
        $gallery_items,
        static fn ($image) => isset($image['url']) && $image['url'] !== $primary_image_url
    ));
}
$gallery_video_url = isset($gallery_video_url) ? trim((string) $gallery_video_url) : '';
$show_gallery = ! empty($sections['gallery']) && (! empty($gallery_items) || '' !== $gallery_video_url);
$hero_fact_badges = array_values(array_filter(
    $badges,
    static fn ($badge) => ! isset($badge['icon']) || 'language' !== $badge['icon']
));
$hero_language_badges = isset($experience['language_badges']) && is_array($experience['language_badges'])
    ? array_values(array_filter(array_map(
        static function ($language) {
            if (! is_array($language)) {
                return null;
            }

            $label = isset($language['label']) ? trim((string) $language['label']) : '';
            $sprite = isset($language['sprite']) ? trim((string) $language['sprite']) : '';
            $aria_label = isset($language['aria_label']) ? (string) $language['aria_label'] : $label;

            if ('' === $label || '' === $sprite) {
                return null;
            }

            return [
                'label' => $label,
                'sprite' => $sprite,
                'aria_label' => $aria_label,
            ];
        },
        $experience['language_badges']
    )))
    : [];
$hero_highlights = array_slice($highlights, 0, 3);
$experience_short_description = isset($experience['short_description']) ? (string) $experience['short_description'] : '';
$experience_summary = isset($experience['summary']) ? (string) $experience['summary'] : '';
$hero_summary = '' !== $experience_summary ? $experience_summary : $experience_short_description;

$overview = isset($overview) && is_array($overview) ? $overview : [];
$overview_biases = isset($overview['cognitive_biases']) && is_array($overview['cognitive_biases'])
    ? array_values(array_filter(array_map(
        static function ($bias) {
            if (! is_array($bias)) {
                $bias = [
                    'label' => (string) $bias,
                ];
            }

            $label = isset($bias['label']) ? (string) $bias['label'] : '';
            if ('' === $label) {
                return null;
            }

            $tagline = isset($bias['tagline']) ? (string) $bias['tagline'] : '';
            $description = isset($bias['description']) ? (string) $bias['description'] : '';
            $icon = isset($bias['icon']) ? (string) $bias['icon'] : '';

            return [
                'label' => $label,
                'tagline' => $tagline,
                'description' => $description,
                'icon' => $icon,
            ];
        },
        $overview['cognitive_biases']
    )))
    : [];
$fontawesome_icon = static function (string $icon, string $style = 'fa-solid'): string {
    return sprintf('<span class="%s %s" aria-hidden="true"></span>', $style, $icon);
};

$get_youtube_video_id = static function (string $url): string {
    if ('' === $url) {
        return '';
    }
    
    // Parse different YouTube URL formats
    // https://www.youtube.com/watch?v=VIDEO_ID
    // https://youtu.be/VIDEO_ID
    // https://www.youtube.com/embed/VIDEO_ID
    // https://www.youtube.com/shorts/VIDEO_ID (YouTube Shorts)
    
    $video_id = '';
    
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        $video_id = $matches[1];
    }
    
    return $video_id;
};

$overview_term_icon = static function (string $term) use ($fontawesome_icon): string {
    switch ($term) {
        case 'themes':
            return $fontawesome_icon('fa-shapes');
        case 'languages':
            return $fontawesome_icon('fa-language');
        case 'duration':
            return $fontawesome_icon('fa-clock');
        case 'experience':
            return $fontawesome_icon('fa-location-dot');
        default:
            return $fontawesome_icon('fa-circle');
    }
};

$get_section_icon = static function (string $section) use ($overview_term_icon, $fontawesome_icon): string {
    switch ($section) {
        case 'overview':
            return $fontawesome_icon('fa-circle-info');
        case 'gallery':
            return $fontawesome_icon('fa-images');
        case 'gift':
            return $fontawesome_icon('fa-gift');
        case 'highlights':
            return $fontawesome_icon('fa-star');
        case 'inclusions':
            return $fontawesome_icon('fa-clipboard-list');
        case 'meeting':
            return $fontawesome_icon('fa-map-pin');
        case 'extras':
            return $fontawesome_icon('fa-heart');
        case 'faq':
            return $fontawesome_icon('fa-circle-question');
        case 'reviews':
            return $fontawesome_icon('fa-comments');
        default:
            return $fontawesome_icon('fa-circle-question');
    }
};

$overview_meeting = isset($overview['meeting']) && is_array($overview['meeting']) ? $overview['meeting'] : [];
$overview_meeting_title = isset($overview_meeting['title']) ? (string) $overview_meeting['title'] : '';
$overview_meeting_address = isset($overview_meeting['address']) ? (string) $overview_meeting['address'] : '';
$overview_meeting_summary = isset($overview_meeting['summary']) ? (string) $overview_meeting['summary'] : '';
$overview_short_description = isset($overview['short_description']) ? (string) $overview['short_description'] : '';
$overview_experience_badges = [];
if (isset($overview['experience_badges']) && is_array($overview['experience_badges'])) {
    foreach ($overview['experience_badges'] as $badge) {
        if (! is_array($badge)) {
            continue;
        }

        $label = isset($badge['label']) ? (string) $badge['label'] : '';
        if ('' === $label) {
            continue;
        }

        $icon = isset($badge['icon']) ? (string) $badge['icon'] : '';
        $description = isset($badge['description']) ? (string) $badge['description'] : '';
        $id = isset($badge['id']) ? (string) $badge['id'] : '';

        $overview_experience_badges[] = [
            'label' => $label,
            'icon' => $icon,
            'description' => $description,
            'id' => $id,
        ];
    }
}
$has_overview_detail_lists = ! empty($overview_experience_badges);
$has_overview_details = '' !== $overview_short_description || $has_overview_detail_lists;
$overview_has_content = isset($overview_has_content) ? (bool) $overview_has_content : null;

if (null === $overview_has_content) {
    $overview_has_content = $has_overview_details
        || ! empty($overview_biases)
        || '' !== trim($overview_meeting_title)
        || '' !== trim($overview_meeting_address)
        || '' !== trim($overview_meeting_summary);
}

$has_overview = ! empty($sections['overview']) && $overview_has_content;
$cta_label = esc_html__('Controlla disponibilità', 'fp-experiences');
$price_from_display = isset($experience['price_from_display']) ? (string) $experience['price_from_display'] : '';
// Non mostrare il prezzo se è 0
if ('' !== $price_from_display && (float) $price_from_display <= 0) {
    $price_from_display = '';
}
$currency_code = get_option('woocommerce_currency', 'EUR');
$currency_symbol = function_exists('get_woocommerce_currency_symbol')
    ? get_woocommerce_currency_symbol($currency_code)
    : $currency_code;
$currency_position = get_option('woocommerce_currency_pos', 'left');
$format_currency = static function (string $amount) use ($currency_symbol, $currency_position): string {
    switch ($currency_position) {
        case 'left_space':
            return $currency_symbol . ' ' . $amount;
        case 'right':
            return $amount . $currency_symbol;
        case 'right_space':
            return $amount . ' ' . $currency_symbol;
        case 'left':
        default:
            return $currency_symbol . $amount;
    }
};
$sticky_price_display = '' !== $price_from_display ? $format_currency($price_from_display) : '';

?>
<div
    class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
    data-fp-shortcode="experience"
    data-layout="<?php echo esc_attr($layout_container); ?>"
    data-sidebar="<?php echo esc_attr($sidebar_data); ?>"
    <?php if ('' !== $layout_style_attr) : ?>style="<?php echo esc_attr($layout_style_attr); ?>"<?php endif; ?>
>
    <?php if (! empty($data_layer)) : ?>
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(<?php echo wp_kses_post($data_layer); ?>);
        </script>
    <?php endif; ?>

    <?php if (! empty($sections['hero'])) : ?>
        <section class="fp-exp-section fp-exp-hero" id="fp-exp-section-hero" data-fp-section="hero">
            <div class="fp-exp-hero__container">
                <div class="fp-exp-hero__layout">
                    <div class="fp-exp-hero__primary">
                        <?php if ($primary_image) : ?>
                            <figure class="fp-exp-hero__media">
                                <img
                                    class="fp-exp-hero__image"
                                    src="<?php echo esc_url($primary_image['url']); ?>"
                                    <?php if (! empty($primary_image['srcset'])) : ?>srcset="<?php echo esc_attr($primary_image['srcset']); ?>"<?php endif; ?>
                                    sizes="(min-width: 1280px) 640px, (min-width: 768px) 60vw, 100vw"
                                    <?php if (! empty($primary_image['width'])) : ?>width="<?php echo esc_attr((string) $primary_image['width']); ?>"<?php endif; ?>
                                    <?php if (! empty($primary_image['height'])) : ?>height="<?php echo esc_attr((string) $primary_image['height']); ?>"<?php endif; ?>
                                    alt="<?php echo esc_attr($experience['title']); ?>"
                                    loading="eager"
                                    decoding="async"
                                    fetchpriority="high"
                                />
                            </figure>
                        <?php else : ?>
                            <div class="fp-exp-hero__media fp-exp-hero__media--placeholder" aria-hidden="true">
                                <span></span>
                            </div>
                        <?php endif; ?>

                        <div class="fp-exp-hero__content">
                            <header class="fp-exp-hero__header">
                                <h1 class="fp-exp-hero__title"><?php echo esc_html($experience['title']); ?></h1>
                                <?php if ('' !== $hero_summary) : ?>
                                    <p class="fp-exp-hero__summary"><?php echo esc_html($hero_summary); ?></p>
                                <?php endif; ?>
                            </header>
                        </div>
                    </div>

                    <?php if ('none' === $sidebar_position || empty($widget_html)) : ?>
                        <aside class="fp-exp-hero__sidebar">
                            <div class="fp-exp-hero__card">
                                <?php if ('' !== $sticky_price_display) : ?>
                                    <div class="fp-exp-hero__price" data-fp-scroll-target="calendar">
                                        <span class="fp-exp-hero__price-label"><?php esc_html_e('Da', 'fp-experiences'); ?></span>
                                        <span class="fp-exp-hero__price-value"><?php echo esc_html($sticky_price_display); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="fp-exp-hero__actions">
                                    <button
                                        type="button"
                                        class="fp-exp-button fp-exp-button--primary"
                                        data-fp-scroll="calendar"
                                        data-fp-cta="hero"
                                    >
                                        <?php echo $cta_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </button>
                                    <?php if ($show_gallery) : ?>
                                        <button
                                            type="button"
                                            class="fp-exp-button fp-exp-button--secondary"
                                            data-fp-scroll="gallery"
                                        >
                                            <?php esc_html_e('Vedi galleria', 'fp-experiences'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if (! empty($hero_fact_badges) || ! empty($hero_language_badges)) : ?>
                                    <ul class="fp-exp-hero__facts" role="list">
                                        <?php if (! empty($hero_language_badges)) : ?>
                                            <li class="fp-exp-hero__fact fp-exp-hero__fact--languages">
                                                <span class="fp-exp-hero__fact-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" role="img" aria-hidden="true" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5.33 9h-1.83a19.46 19.46 0 0 0-.87-4 8 8 0 0 1 2.7 4ZM12 4a17.43 17.43 0 0 1 2.44 7H9.56A17.43 17.43 0 0 1 12 4ZM8.37 6.91a19.46 19.46 0 0 0-.87 4H5.67a8 8 0 0 1 2.7-4ZM4 12h3.5a19.43 19.43 0 0 0 .88 4H6.33A8 8 0 0 1 4 12Zm2.37 6h2.64a21.13 21.13 0 0 0 1.87 3.38A8 8 0 0 1 6.37 18Zm5.63 3a19.1 19.1 0 0 1-2.55-5h5.1A19.1 19.1 0 0 1 12 21Zm2.69.38A21.13 21.13 0 0 0 15 18h2.64a8 8 0 0 1-3 3.38ZM17.67 16H15.62a19.43 19.43 0 0 0 .88-4H20a8 8 0 0 1-2.33 4Z"/></svg>
                                                </span>
                                                <div class="fp-exp-hero__fact-content">
                                                    <span class="fp-exp-hero__fact-label"><?php esc_html_e('Lingue disponibili', 'fp-experiences'); ?></span>
                                                    <ul class="fp-exp-hero__language-list" role="list">
                                                        <?php foreach ($hero_language_badges as $language) : ?>
                                                            <li class="fp-exp-hero__language">
                                                                <span class="fp-exp-hero__language-flag" aria-hidden="true">
                                                                    <svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false" width="30" height="20">
                                                                        <use xlink:href="<?php echo esc_attr($language_sprite . '#' . $language['sprite']); ?>" href="<?php echo esc_attr($language_sprite . '#' . $language['sprite']); ?>"></use>
                                                                    </svg>
                                                                </span>
                                                                <span class="fp-exp-hero__language-label"><?php echo esc_html($language['label']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </li>
                                        <?php endif; ?>
                                        <?php foreach ($hero_fact_badges as $badge) : ?>
                                            <li class="fp-exp-hero__fact">
                                                <span class="fp-exp-hero__fact-icon" aria-hidden="true">
                                                    <?php if ('clock' === ($badge['icon'] ?? '')) : ?>
                                                        <svg viewBox="0 0 24 24" role="img" aria-hidden="true" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.59 2.12 2.12-1.41 1.41-2.83-2.83V7h2.12Z"/></svg>
                                                    <?php else : ?>
                                                        <?php echo \FP_Exp\Utils\Helpers::experience_badge_icon_svg((string) ($badge['icon'] ?? '')); ?>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="fp-exp-hero__fact-text"><?php echo esc_html((string) ($badge['label'] ?? '')); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </aside>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="fp-grid fp-exp-page__layout" data-fp-page>
        <main class="fp-main fp-exp-page__main">
            <?php if ($has_overview) : ?>
                <section class="fp-exp-section fp-exp-overview" id="fp-exp-section-overview" data-fp-section="overview">
                    <header class="fp-exp-section__header fp-exp-overview__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('overview'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Perché prenotare con noi', 'fp-experiences'); ?></h2>
                        </div>
                    </header>

                    <?php if ($has_overview_details) : ?>
                        <div class="fp-exp-overview__details">
                            <?php if ('' !== $overview_short_description) : ?>
                                <p class="fp-exp-overview__lead"><?php echo esc_html($overview_short_description); ?></p>
                            <?php endif; ?>

                            <?php if (! empty($overview_experience_badges)) : ?>
                                <dl class="fp-exp-overview__grid">
                                    <div class="fp-exp-overview__item">
                                        <dt class="fp-exp-overview__term">
                                            <span class="fp-exp-overview__term-label"><?php esc_html_e('Caratteristiche esperienza', 'fp-experiences'); ?></span>
                                        </dt>
                                        <dd class="fp-exp-overview__definition">
                                            <ul class="fp-exp-overview__list" role="list">
                                                <?php foreach ($overview_experience_badges as $badge) :
                                                    $badge_label = isset($badge['label']) ? (string) $badge['label'] : '';
                                                    if ('' === $badge_label) {
                                                        continue;
                                                    }

                                                    $badge_description = isset($badge['description']) ? (string) $badge['description'] : '';
                                                    ?>
                                                    <li class="fp-exp-overview__list-item">
                                                        <span class="fp-exp-overview__list-body">
                                                            <span class="fp-exp-overview__list-text"><?php echo esc_html($badge_label); ?></span>
                                                            <?php if ('' !== $badge_description) : ?>
                                                                <span class="fp-exp-overview__list-hint"><?php echo esc_html($badge_description); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </dd>
                                    </div>
                                </dl>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </section>
            <?php endif; ?>

            <?php if ($show_gallery) : ?>
                <section class="fp-exp-section fp-exp-gallery" id="fp-exp-section-gallery" data-fp-section="gallery">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('gallery'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Uno sguardo all\'esperienza', 'fp-experiences'); ?></h2>
                        </div>
                    </header>
                    <div class="fp-exp-gallery__track" role="list">
                        <?php
                        $youtube_video_id = '' !== $gallery_video_url ? $get_youtube_video_id($gallery_video_url) : '';
                        if ('' !== $youtube_video_id) :
                            ?>
                            <div class="fp-exp-gallery__item fp-exp-gallery__item--video" role="listitem">
                                <div class="fp-exp-gallery__video-wrapper">
                                    <iframe
                                        class="fp-exp-gallery__video"
                                        src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_video_id); ?>?autoplay=1&mute=1&loop=1&playlist=<?php echo esc_attr($youtube_video_id); ?>&controls=1&modestbranding=1&rel=0"
                                        title="<?php echo esc_attr($experience['title']); ?>"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                        loading="lazy"
                                    ></iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($gallery_items as $index => $image) :
                            $url = isset($image['url']) ? (string) $image['url'] : '';
                            if ('' === $url) {
                                continue;
                            }

                            $srcset = isset($image['srcset']) ? (string) $image['srcset'] : '';
                            $width = isset($image['width']) ? (string) $image['width'] : '';
                            $height = isset($image['height']) ? (string) $image['height'] : '';
                            $alt = isset($image['alt']) ? (string) $image['alt'] : '';
                            $caption = isset($image['caption']) ? (string) $image['caption'] : '';

                            if ('' === $alt) {
                                $alt = $experience['title'];
                            }
                            ?>
                            <figure class="fp-exp-gallery__item" role="listitem">
                                <img
                                    class="fp-exp-gallery__image"
                                    src="<?php echo esc_url($url); ?>"
                                    <?php if ('' !== $srcset) : ?>srcset="<?php echo esc_attr($srcset); ?>"<?php endif; ?>
                                    sizes="(min-width: 768px) 50vw, 100vw"
                                    <?php if ('' !== $width) : ?>width="<?php echo esc_attr($width); ?>"<?php endif; ?>
                                    <?php if ('' !== $height) : ?>height="<?php echo esc_attr($height); ?>"<?php endif; ?>
                                    alt="<?php echo esc_attr($alt); ?>"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <?php if ('' !== $caption) : ?>
                                    <figcaption class="fp-exp-gallery__caption"><?php echo esc_html($caption); ?></figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($gift_enabled) : ?>
                <section class="fp-exp-section fp-exp-gift" id="fp-exp-hero-gift" data-fp-section="hero-gift">
                    <div class="fp-exp-gift__body">
                        <div class="fp-exp-gift__content">
                            <div class="fp-exp-section__heading">
                                <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('gift'); ?></span>
                                <h2 class="fp-exp-gift__title fp-exp-section__title"><?php esc_html_e('Regala questa esperienza', 'fp-experiences'); ?></h2>
                            </div>
                            <p class="fp-exp-gift__description"><?php esc_html_e('Acquista un voucher e invialo con un messaggio personalizzato in pochi clic.', 'fp-experiences'); ?></p>
                        </div>
                        <button
                            type="button"
                            class="fp-exp-button fp-exp-button--secondary"
                            data-fp-gift-toggle
                            aria-controls="fp-exp-gift"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                        >
                            <?php esc_html_e('Regala questa esperienza', 'fp-experiences'); ?>
                        </button>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($gift_enabled) : ?>
                <div
                    class="fp-gift-modal"
                    id="fp-exp-gift"
                    data-fp-gift
                    data-fp-gift-config="<?php echo esc_attr(wp_json_encode($gift_config)); ?>"
                    aria-hidden="true"
                    hidden
                >
                    <div class="fp-gift-modal__backdrop" data-fp-gift-backdrop aria-hidden="true"></div>
                    <div
                        class="fp-gift-modal__dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="fp-exp-gift-title"
                        aria-describedby="fp-exp-gift-intro"
                        data-fp-gift-dialog
                        tabindex="-1"
                    >
                        <button type="button" class="fp-gift-modal__close" data-fp-gift-close>
                            <span class="screen-reader-text"><?php esc_html_e('Chiudi modulo regalo', 'fp-experiences'); ?></span>
                            <svg viewBox="0 0 24 24" aria-hidden="true" width="24" height="24"><path fill="currentColor" d="m12 10.59 4.95-4.95 1.41 1.41L13.41 12l4.95 4.95-1.41 1.41L12 13.41l-4.95 4.95-1.41-1.41L10.59 12 5.64 7.05l1.41-1.41Z"/></svg>
                        </button>
                        <div class="fp-gift">
                            <div class="fp-gift__inner">
                                <h2 class="fp-gift__title" id="fp-exp-gift-title"><?php esc_html_e('Regala questa esperienza', 'fp-experiences'); ?></h2>
                                <p class="fp-gift__intro" id="fp-exp-gift-intro"><?php esc_html_e('Acquista un voucher, personalizza un messaggio e invialo via email in pochi clic.', 'fp-experiences'); ?></p>
                                <div class="fp-gift__feedback" data-fp-gift-feedback aria-live="polite" hidden></div>
                                <form class="fp-gift__form" data-fp-gift-form novalidate>
                                    <div class="fp-gift__grid">
                                        <div class="fp-gift__field">
                                            <label for="fp-gift-purchaser-name"><?php esc_html_e('Il tuo nome', 'fp-experiences'); ?></label>
                                            <input type="text" id="fp-gift-purchaser-name" name="purchaser[name]" required />
                                        </div>
                                        <div class="fp-gift__field">
                                            <label for="fp-gift-purchaser-email"><?php esc_html_e('La tua email', 'fp-experiences'); ?></label>
                                            <input type="email" id="fp-gift-purchaser-email" name="purchaser[email]" required />
                                        </div>
                                        <div class="fp-gift__field">
                                            <label for="fp-gift-recipient-name"><?php esc_html_e('Nome destinatario', 'fp-experiences'); ?></label>
                                            <input type="text" id="fp-gift-recipient-name" name="recipient[name]" required />
                                        </div>
                                        <div class="fp-gift__field">
                                            <label for="fp-gift-recipient-email"><?php esc_html_e('Email destinatario', 'fp-experiences'); ?></label>
                                            <input type="email" id="fp-gift-recipient-email" name="recipient[email]" required />
                                        </div>
                                        <div class="fp-gift__field">
                                            <label for="fp-gift-send-on"><?php esc_html_e('Data di consegna (opzionale)', 'fp-experiences'); ?></label>
                                            <input type="date" id="fp-gift-send-on" name="delivery[send_on]" min="<?php echo esc_attr(gmdate('Y-m-d')); ?>" />
                                            <p class="fp-gift__field-note"><?php esc_html_e('Invieremo il regalo via email alle 9:00 (Europe/Rome). Lascia vuoto per inviarlo subito dopo il pagamento.', 'fp-experiences'); ?></p>
                                        </div>
                                        <div class="fp-gift__field fp-gift__field--quantity">
                                            <label for="fp-gift-quantity"><?php esc_html_e('Numero di ospiti', 'fp-experiences'); ?></label>
                                            <input type="number" id="fp-gift-quantity" name="quantity" value="1" min="1" step="1" required />
                                        </div>
                                        <div class="fp-gift__field fp-gift__field--message">
                                            <label for="fp-gift-message"><?php esc_html_e('Messaggio personale (opzionale)', 'fp-experiences'); ?></label>
                                            <textarea id="fp-gift-message" name="message" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <?php if ($gift_addons) : ?>
                                        <?php
                                        // Raggruppa gli addon per gruppo
                                        $gift_grouped_addons = [];
                                        foreach ($gift_addons as $addon) {
                                            $group = isset($addon['selection_group']) && '' !== (string) $addon['selection_group']
                                                ? (string) $addon['selection_group']
                                                : '__default__';
                                            if (! isset($gift_grouped_addons[$group])) {
                                                $gift_grouped_addons[$group] = [];
                                            }
                                            $gift_grouped_addons[$group][] = $addon;
                                        }
                                        ?>
                                        <?php foreach ($gift_grouped_addons as $group_name => $group_addons) : ?>
                                            <?php
                                            $is_default_group = '__default__' === $group_name;
                                            $first_addon = $group_addons[0];
                                            $is_radio_group = isset($first_addon['selection_type']) && 'radio' === $first_addon['selection_type'];
                                            $group_id = $is_default_group ? 'gift-addons-default' : 'gift-addons-' . sanitize_title($group_name);
                                            $fieldset_title = $is_default_group ? esc_html__('Extra prepagati', 'fp-experiences') : esc_html($group_name);
                                            ?>
                                            <fieldset class="fp-gift__addons">
                                                <legend><?php echo $fieldset_title; ?></legend>
                                                <div class="fp-gift__addons-grid">
                                                    <?php foreach ($group_addons as $addon) :
                                                        $addon_price = isset($addon['price']) ? (float) $addon['price'] : 0.0;
                                                        if (function_exists('wc_price')) {
                                                            $formatted_price = wc_price($addon_price);
                                                        } else {
                                                            $currency_code = get_option('woocommerce_currency', 'EUR');
                                                            $symbol = function_exists('get_woocommerce_currency_symbol')
                                                                ? get_woocommerce_currency_symbol($currency_code)
                                                                : $currency_code;
                                                            $formatted_price = esc_html(number_format_i18n($addon_price, 2) . ' ' . $symbol);
                                                        }
                                                        $selection_type = isset($addon['selection_type']) ? (string) $addon['selection_type'] : 'checkbox';
                                                        $input_type = 'radio' === $selection_type ? 'radio' : 'checkbox';
                                                        $input_name = 'radio' === $selection_type ? 'addon_' . $group_id : 'addons[]';
                                                        ?>
                                                        <label class="fp-gift__addon">
                                                            <input 
                                                                type="<?php echo esc_attr($input_type); ?>" 
                                                                name="<?php echo esc_attr($input_name); ?>" 
                                                                value="<?php echo esc_attr((string) ($addon['slug'] ?? '')); ?>" 
                                                            />
                                                            <span class="fp-gift__addon-label"><?php echo esc_html((string) ($addon['label'] ?? '')); ?></span>
                                                            <?php if (! empty($addon['description'])) : ?>
                                                                <span class="fp-gift__addon-desc"><?php echo esc_html((string) $addon['description']); ?></span>
                                                            <?php endif; ?>
                                                            <span class="fp-gift__addon-price"><?php echo wp_kses_post($formatted_price); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </fieldset>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <p class="fp-gift__note"><?php esc_html_e('Potrai verificare il totale e completare il pagamento al checkout. Il destinatario riceverà il voucher la mattina programmata o immediatamente dopo il pagamento se non è stata selezionata una data.', 'fp-experiences'); ?></p>
                                    <button type="submit" class="fp-exp-button" data-fp-gift-submit>
                                        <?php esc_html_e('Procedi al pagamento', 'fp-experiences'); ?>
                                    </button>
                                </form>
                                <div class="fp-gift__success" data-fp-gift-success hidden></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (! empty($sections['highlights']) && $has_highlights) : ?>
                <section class="fp-exp-section fp-exp-highlights" id="fp-exp-section-highlights" data-fp-section="highlights">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('highlights'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Perché questa esperienza è speciale', 'fp-experiences'); ?></h2>
                        </div>
                    </header>
                    <div class="fp-exp-section__body">
                        <ul class="fp-exp-highlights__list" role="list">
                            <?php foreach ($highlights as $highlight) : ?>
                                <?php if ('' !== trim($highlight) && strtolower(trim($highlight)) !== 'array') : ?>
                                    <li class="fp-exp-highlights__item">
                                        <span class="fp-exp-highlights__icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M9.75 18.25 3.5 12l1.41-1.41 4.84 4.84 9.34-9.34L20.5 7.5Z"/></svg>
                                        </span>
                                        <span class="fp-exp-highlights__text"><?php echo esc_html($highlight); ?></span>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['inclusions']) && $has_inclusions) : ?>
                <section class="fp-exp-section fp-exp-inclusions" id="fp-exp-section-inclusions" data-fp-section="inclusions">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('inclusions'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Cosa è incluso', 'fp-experiences'); ?></h2>
                        </div>
                        <?php if (! empty($exclusions)) : ?>
                            <p class="fp-exp-section__summary"><?php esc_html_e('Cosa aspettarsi il giorno dell’esperienza e cosa è a pagamento.', 'fp-experiences'); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="fp-exp-section__body">
                        <div class="fp-exp-inclusions__grid">
                            <?php if (! empty($inclusions)) : ?>
                                <div class="fp-exp-inclusions__column">
                                    <h3 class="fp-exp-inclusions__title"><?php esc_html_e('Incluso', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-inclusions__list" role="list">
                                        <?php foreach ($inclusions as $item) : ?>
                                            <?php if ('' !== trim($item) && strtolower(trim($item)) !== 'array') : ?>
                                                <li class="fp-exp-inclusions__item">
                                                    <span class="fp-exp-inclusions__icon fp-exp-inclusions__icon--check" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M9.75 18.25 3.5 12l1.41-1.41 4.84 4.84 9.34-9.34L20.5 7.5Z"/></svg>
                                                    </span>
                                                    <span class="fp-exp-inclusions__text"><?php echo esc_html($item); ?></span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (! empty($exclusions)) : ?>
                                <div class="fp-exp-inclusions__column">
                                    <h3 class="fp-exp-inclusions__title"><?php esc_html_e('Non incluso', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-inclusions__list" role="list">
                                        <?php foreach ($exclusions as $item) : ?>
                                            <?php if ('' !== trim($item) && strtolower(trim($item)) !== 'array') : ?>
                                                <li class="fp-exp-inclusions__item">
                                                    <span class="fp-exp-inclusions__icon fp-exp-inclusions__icon--cross" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="m18.3 5.71 1.42 1.42-5.3 5.29 5.3 5.29-1.42 1.42-5.29-5.3-5.29 5.3-1.42-1.42 5.3-5.29-5.3-5.29 1.42-1.42 5.29 5.3Z"/></svg>
                                                    </span>
                                                    <span class="fp-exp-inclusions__text"><?php echo esc_html($item); ?></span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['meeting']) && $has_meeting) : ?>
                <section class="fp-exp-section fp-exp-meeting" id="fp-exp-section-meeting" data-fp-section="meeting">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('meeting'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Punto di ritrovo', 'fp-experiences'); ?></h2>
                        </div>
                    </header>
                    <div class="fp-exp-section__body">
                        <?php
                        $primary = $meeting_points['primary'];
                        $alternatives = $meeting_points['alternatives'];
                        $embedded = true;
                        include __DIR__ . '/meeting-points.php';
                        ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['extras']) && $has_extras) : ?>
                <section class="fp-exp-section fp-exp-essentials" id="fp-exp-section-extras" data-fp-section="extras">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('extras'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Da sapere', 'fp-experiences'); ?></h2>
                        </div>
                        <p class="fp-exp-section__summary"><?php esc_html_e('Suggerimenti utili per pianificare, oltre a note e politiche importanti.', 'fp-experiences'); ?></p>
                    </header>
                    <div class="fp-exp-section__body">
                        <div class="fp-exp-essentials__grid">
                            <?php
                            // Filtra gli elementi di "Cosa portare" prima di verificare se renderizzare la card
                            $what_to_bring_filtered = ! empty($what_to_bring) ? array_filter($what_to_bring, static function($item) {
                                $trimmed = trim((string) $item);
                                return '' !== $trimmed && strtolower($trimmed) !== 'array';
                            }) : [];
                            ?>
                            <?php if (! empty($what_to_bring_filtered)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Cosa portare', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-essentials__list">
                                        <?php foreach ($what_to_bring_filtered as $item) : ?>
                                            <li><?php echo esc_html($item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </article>
                            <?php endif; ?>

                            <?php
                            // Prepara e filtra le note prima di renderizzare la card
                            $notes_items = [];
                            if (! empty($notes)) {
                                $notes_items = is_array($notes) ? $notes : array_filter(array_map('trim', explode("\n", $notes)));
                                // Filtra elementi vuoti e la stringa "Array"
                                $notes_items = array_filter($notes_items, static function($item) {
                                    return '' !== trim($item) && strtolower(trim($item)) !== 'array';
                                });
                            }
                            ?>
                            <?php if (! empty($notes_items)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Note', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-essentials__list">
                                        <?php foreach ($notes_items as $note) : ?>
                                            <li><?php echo esc_html($note); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </article>
                            <?php endif; ?>

                            <?php
                            // Prepara e filtra le regole bambini prima di renderizzare la card
                            $children_rules_trimmed = trim((string) $children_rules);
                            $has_children_rules = '' !== $children_rules_trimmed && strtolower($children_rules_trimmed) !== 'array';
                            $children_rules_items = [];
                            
                            if ($has_children_rules) {
                                $children_rules_items = array_filter(array_map('trim', explode("\n", $children_rules)));
                                // Filtra elementi vuoti e la stringa "Array"
                                $children_rules_items = array_filter($children_rules_items, static function($item) {
                                    return '' !== trim($item) && strtolower(trim($item)) !== 'array';
                                });
                            }
                            ?>
                            <?php if (! empty($children_rules_items) || ($has_children_rules && count(array_map('trim', explode("\n", $children_rules))) === 1)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Regole bambini', 'fp-experiences'); ?></h3>
                                    <?php if (count($children_rules_items) > 1) : ?>
                                        <ul class="fp-exp-essentials__list">
                                            <?php foreach ($children_rules_items as $rule) : ?>
                                                <li><?php echo esc_html($rule); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php elseif ($has_children_rules) : ?>
                                        <p class="fp-exp-essentials__copy"><?php echo esc_html($children_rules_trimmed); ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endif; ?>

                            <?php if (! empty($policy)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Politica di cancellazione', 'fp-experiences'); ?></h3>
                                    <div class="fp-exp-essentials__copy fp-exp-essentials__copy--rich">
                                        <div class="fp-exp-richtext"><?php echo wp_kses_post($policy); ?></div>
                                    </div>
                                </article>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['faq']) && $has_faq) : ?>
                <section class="fp-exp-section" id="fp-exp-section-faq" data-fp-section="faq">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('faq'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Domande frequenti', 'fp-experiences'); ?></h2>
                        </div>
                    </header>
                    <div class="fp-exp-accordion" data-fp-accordion>
                        <?php foreach ($faq as $index => $item) :
                            $button_id = $scope_class . '-faq-' . $index;
                            $panel_id = $scope_class . '-faq-panel-' . $index;
                            $question = isset($item['question']) ? (string) $item['question'] : '';
                            $answer = isset($item['answer']) ? (string) $item['answer'] : '';
                            // Salta se contiene "Array"
                            if (strtolower(trim($question)) === 'array' || strtolower(trim($answer)) === 'array') {
                                continue;
                            }
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
                                        <span class="fp-exp-accordion__label"><?php echo esc_html($question); ?></span>
                                        <span class="fp-exp-accordion__icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 5v14m-7-7h14"/></svg>
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
                                    <div class="fp-exp-accordion__content"><?php echo wp_kses_post($answer); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['reviews']) && $has_reviews) : ?>
                <section class="fp-exp-section" id="fp-exp-section-reviews" data-fp-section="reviews">
                    <header class="fp-exp-section__header">
                        <div class="fp-exp-section__heading">
                            <span class="fp-exp-section__icon" aria-hidden="true"><?php echo $get_section_icon('reviews'); ?></span>
                            <h2 class="fp-exp-section__title"><?php esc_html_e('Recensioni dei viaggiatori', 'fp-experiences'); ?></h2>
                        </div>
                    </header>
                    <ul class="fp-exp-reviews" role="list">
                        <?php foreach ($reviews as $review) : ?>
                            <li class="fp-exp-review" data-fp-review>
                                <header class="fp-exp-review__header">
                                    <strong class="fp-exp-review__author"><?php echo esc_html($review['author'] ?? ''); ?></strong>
                                    <?php if (! empty($review['rating'])) : ?>
                                        <span class="fp-exp-review__rating" aria-label="<?php echo esc_attr(sprintf(esc_html__('%s su 5', 'fp-experiences'), number_format_i18n((float) $review['rating'], 1))); ?>">
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

        <?php if ('none' !== $sidebar_position && ! empty($widget_html)) : ?>
            <aside
                class="fp-aside fp-exp-page__aside"
                id="fp-exp-widget"
                data-fp-sticky-container
                aria-label="<?php esc_attr_e('Riepilogo prenotazione', 'fp-experiences'); ?>"
            >
                <div class="fp-exp-page__widget">
                    <?php echo $widget_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </aside>
        <?php endif; ?>
    </div>

    <?php if ($sticky_widget && 'none' !== $sidebar_position && ! empty($widget_html)) : ?>
        <div class="fp-exp-page__sticky-bar" data-fp-sticky-bar>
            <?php if ('' !== $sticky_price_display) : ?>
                <span class="fp-exp-page__sticky-price">
                    <span class="fp-exp-page__sticky-price-label"><?php esc_html_e('Da', 'fp-experiences'); ?></span>
                    <span class="fp-exp-page__sticky-price-value"><?php echo esc_html($sticky_price_display); ?></span>
                </span>
            <?php endif; ?>
            <button type="button" class="fp-exp-page__sticky-button" data-fp-scroll="calendar" data-fp-cta="sticky">
                <?php echo $cta_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    <?php endif; ?>
</div>
