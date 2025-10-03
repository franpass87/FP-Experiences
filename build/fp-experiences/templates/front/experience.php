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
$show_gallery = ! empty($sections['gallery']) && ! empty($gallery_items);
$hero_fact_badges = array_values(array_filter(
    $badges,
    static fn ($badge) => ! isset($badge['icon']) || 'language' !== $badge['icon']
));
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
$normalize_overview_list = static function ($values): array {
    if (! is_array($values)) {
        return [];
    }

    $normalized = [];
    foreach ($values as $value) {
        if (is_array($value)) {
            $value = isset($value['label']) ? (string) $value['label'] : '';
        }

        $value = (string) $value;
        $value = trim($value);

        if ('' !== $value) {
            $normalized[] = $value;
        }
    }

    return array_values(array_unique($normalized));
};
$overview_term_icon = static function (string $term): string {
    switch ($term) {
        case 'themes':
            return '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5.5A2.5 2.5 0 0 1 5.5 3h6.59a2.5 2.5 0 0 1 1.77.73l6.41 6.41a2.5 2.5 0 0 1 0 3.54l-6.59 6.59a2.12 2.12 0 0 1-3 0L3.73 13.87A2.5 2.5 0 0 1 3 12.1Zm6.75 1.75a1.75 1.75 0 1 0 1.75-1.75 1.75 1.75 0 0 0-1.75 1.75Z"/></svg>';
        case 'languages':
            return '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5.33 9h-1.83a19.46 19.46 0 0 0-.87-4 8 8 0 0 1 2.7 4ZM12 4a17.43 17.43 0 0 1 2.44 7H9.56A17.43 17.43 0 0 1 12 4ZM8.37 6.91a19.46 19.46 0 0 0-.87 4H5.67a8 8 0 0 1 2.7-4ZM4 12h3.5a19.43 19.43 0 0 0 .88 4H6.33A8 8 0 0 1 4 12Zm2.37 6h2.64a21.13 21.13 0 0 0 1.87 3.38A8 8 0 0 1 6.37 18Zm5.63 3a19.1 19.1 0 0 1-2.55-5h5.1A19.1 19.1 0 0 1 12 21Zm2.69.38A21.13 21.13 0 0 0 15 18h2.64a8 8 0 0 1-3 3.38ZM17.67 16H15.62a19.43 19.43 0 0 0 .88-4H20a8 8 0 0 1-2.33 4Z"/></svg>';
        case 'duration':
            return '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 11.59L16.12 16l-1.41 1.41L11.88 14V7h2.12Z"/></svg>';
        case 'family':
            return '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 21.35 10.55 20c-4.2-3.8-7-6.3-7-9.5A4.5 4.5 0 0 1 8 6a4.49 4.49 0 0 1 4 2.35A4.49 4.49 0 0 1 16 6a4.5 4.5 0 0 1 4.5 4.5c0 3.2-2.8 5.7-7 9.5Z"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 3a7 7 0 1 1-7 7 7 7 0 0 1 7-7Z"/></svg>';
    }
};

$normalize_language_key = static function (string $label): string {
    $label = remove_accents($label);
    $label = trim((string) $label);
    $label = strtolower($label);
    $label = preg_replace('/[^a-z0-9]+/u', '-', $label);

    return trim((string) $label, '-');
};

$language_flag_icons = [
    'italiano' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="20" height="40" fill="#009246"/><rect x="20" width="20" height="40" fill="#ffffff"/><rect x="40" width="20" height="40" fill="#ce2b37"/></svg>',
    'italian' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="20" height="40" fill="#009246"/><rect x="20" width="20" height="40" fill="#ffffff"/><rect x="40" width="20" height="40" fill="#ce2b37"/></svg>',
    'inglese' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" fill="#012169"/><path fill="#ffffff" d="M0 15h25V0h10v15h25v10H35v15H25V25H0Z"/><path fill="#c8102e" d="M0 17h27V0h6v17h27v6H33v17h-6V23H0Z"/><path fill="#ffffff" d="m0 4.5 17 11.5h-6L0 7.2Zm60 0v2.7L43 16h-6Zm0 31.5-17-11.5h6l11 7.2ZM0 36l17-11.5h6L0 37.5Z"/><path fill="#c8102e" d="m0 1.8 20.4 13.2h-4.5L0 4.5Zm60 0v2.7L39.6 15h4.5ZM60 38.2 39.6 25h4.5L60 35.5ZM0 38.2l20.4-13.2h-4.5L0 35.5Z"/></svg>',
    'english' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" fill="#012169"/><path fill="#ffffff" d="M0 15h25V0h10v15h25v10H35v15H25V25H0Z"/><path fill="#c8102e" d="M0 17h27V0h6v17h27v6H33v17h-6V23H0Z"/><path fill="#ffffff" d="m0 4.5 17 11.5h-6L0 7.2Zm60 0v2.7L43 16h-6Zm0 31.5-17-11.5h6l11 7.2ZM0 36l17-11.5h6L0 37.5Z"/><path fill="#c8102e" d="m0 1.8 20.4 13.2h-4.5L0 4.5Zm60 0v2.7L39.6 15h4.5ZM60 38.2 39.6 25h4.5L60 35.5ZM0 38.2l20.4-13.2h-4.5L0 35.5Z"/></svg>',
    'francese' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="20" height="40" fill="#0055a4"/><rect x="20" width="20" height="40" fill="#ffffff"/><rect x="40" width="20" height="40" fill="#ef4135"/></svg>',
    'french' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="20" height="40" fill="#0055a4"/><rect x="20" width="20" height="40" fill="#ffffff"/><rect x="40" width="20" height="40" fill="#ef4135"/></svg>',
    'spagnolo' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" fill="#c60b1e"/><rect y="12" width="60" height="16" fill="#ffc400"/></svg>',
    'spanish' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" fill="#c60b1e"/><rect y="12" width="60" height="16" fill="#ffc400"/></svg>',
    'tedesco' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" fill="#ffce00"/><rect y="13.3" width="60" height="13.4" fill="#dd0000"/><rect width="60" height="13.3" fill="#000000"/></svg>',
    'german' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" fill="#ffce00"/><rect y="13.3" width="60" height="13.4" fill="#dd0000"/><rect width="60" height="13.3" fill="#000000"/></svg>',
    'portoghese' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="24" height="40" fill="#006600"/><rect x="24" width="36" height="40" fill="#ff0000"/><circle cx="24" cy="20" r="8" fill="#ffcc29"/></svg>',
    'portuguese' => '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="24" height="40" fill="#006600"/><rect x="24" width="36" height="40" fill="#ff0000"/><circle cx="24" cy="20" r="8" fill="#ffcc29"/></svg>',
];

$get_language_flag_icon = static function (string $label) use ($language_flag_icons, $normalize_language_key): string {
    $key = $normalize_language_key($label);

    if (isset($language_flag_icons[$key])) {
        return $language_flag_icons[$key];
    }

    return '<svg viewBox="0 0 60 40" role="img" aria-hidden="true" focusable="false"><rect width="60" height="40" rx="4" fill="#0f172a"/><path fill="#38bdf8" d="M12 20a18 18 0 0 1 36 0 18 18 0 0 1-36 0Z" opacity="0.3"/><path fill="#38bdf8" d="M24 20a6 6 0 1 1 6 6 6 6 0 0 1-6-6Z"/></svg>';
};

$overview_meeting = isset($overview['meeting']) && is_array($overview['meeting']) ? $overview['meeting'] : [];
$overview_meeting_title = isset($overview_meeting['title']) ? (string) $overview_meeting['title'] : '';
$overview_meeting_address = isset($overview_meeting['address']) ? (string) $overview_meeting['address'] : '';
$overview_meeting_summary = isset($overview_meeting['summary']) ? (string) $overview_meeting['summary'] : '';
$overview_short_description = isset($overview['short_description']) ? (string) $overview['short_description'] : '';
$overview_themes = $normalize_overview_list($overview['themes'] ?? []);
$overview_language_terms = $normalize_overview_list($overview['language_terms'] ?? []);
$overview_duration_terms = $normalize_overview_list($overview['duration_terms'] ?? []);
$overview_family_terms = $normalize_overview_list($overview['family_terms'] ?? []);
$overview_family_friendly = ! empty($overview['family_friendly']);
$has_overview_detail_lists = ! empty($overview_themes)
    || ! empty($overview_language_terms)
    || ! empty($overview_duration_terms)
    || ! empty($overview_family_terms)
    || $overview_family_friendly;
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

                            <?php if (! empty($hero_highlights)) : ?>
                                <ul class="fp-exp-hero__highlights" role="list">
                                    <?php foreach ($hero_highlights as $highlight) : ?>
                                        <li class="fp-exp-hero__highlight">
                                            <span class="fp-exp-hero__highlight-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path fill="currentColor" d="M9.75 18.25 3.5 12l1.41-1.41 4.84 4.84 9.34-9.34L20.5 7.5Z" />
                                                </svg>
                                            </span>
                                            <span class="fp-exp-hero__highlight-text"><?php echo esc_html($highlight); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ('none' === $sidebar_position || empty($widget_html)) : ?>
                        <aside class="fp-exp-hero__sidebar">
                            <div class="fp-exp-hero__card">
                                <?php if ('' !== $sticky_price_display) : ?>
                                    <div class="fp-exp-hero__price" data-fp-scroll-target="calendar">
                                        <span class="fp-exp-hero__price-label"><?php esc_html_e('From', 'fp-experiences'); ?></span>
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
                                            <?php esc_html_e('View gallery', 'fp-experiences'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if (! empty($hero_fact_badges)) : ?>
                                    <ul class="fp-exp-hero__facts" role="list">
                                        <?php foreach ($hero_fact_badges as $badge) : ?>
                                            <li class="fp-exp-hero__fact">
                                                <span class="fp-exp-hero__fact-icon" aria-hidden="true">
                                                    <?php if ('clock' === ($badge['icon'] ?? '')) : ?>
                                                        <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.59 2.12 2.12-1.41 1.41-2.83-2.83V7h2.12Z"/></svg>
                                                    <?php else : ?>
                                                        <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 12.88 9.17 10H5a3 3 0 0 0-3 3v7h6v-4h2v4h6v-7a3 3 0 0 0-3-3h-1.17Zm9-2.88a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/></svg>
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
            <?php if ($gift_enabled) : ?>
                <section class="fp-exp-section fp-exp-gift" id="fp-exp-hero-gift" data-fp-section="hero-gift">
                    <div class="fp-exp-gift__body">
                        <div class="fp-exp-gift__content">
                            <span class="fp-exp-gift__eyebrow"><?php esc_html_e('Regali', 'fp-experiences'); ?></span>
                            <h2 class="fp-exp-gift__title fp-exp-section__title"><?php esc_html_e('Gift this experience', 'fp-experiences'); ?></h2>
                            <p class="fp-exp-gift__description"><?php esc_html_e('Acquista un voucher e invialo con un messaggio personalizzato in pochi clic.', 'fp-experiences'); ?></p>
                        </div>
                        <button
                            type="button"
                            class="fp-exp-button fp-exp-button--secondary"
                            data-fp-gift-toggle
                            aria-controls="fp-exp-gift"
                            aria-expanded="false"
                        >
                            <?php esc_html_e('Gift this experience', 'fp-experiences'); ?>
                        </button>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($has_overview) : ?>
                <section class="fp-exp-section fp-exp-overview" id="fp-exp-section-overview" data-fp-section="overview">
                    <header class="fp-exp-section__header fp-exp-overview__header">
                        <h2 class="fp-exp-section__title"><?php esc_html_e('Perché prenotare con noi', 'fp-experiences'); ?></h2>
                    </header>

                    <?php if ($has_overview_details) : ?>
                        <div class="fp-exp-overview__details">
                            <?php if ('' !== $overview_short_description) : ?>
                                <p class="fp-exp-overview__lead"><?php echo esc_html($overview_short_description); ?></p>
                            <?php endif; ?>

                            <?php if ($has_overview_detail_lists) : ?>
                                <dl class="fp-exp-overview__grid">
                                    <?php if (! empty($overview_themes)) : ?>
                                        <div class="fp-exp-overview__item">
                                            <dt class="fp-exp-overview__term">
                                                <span class="fp-exp-overview__term-icon" aria-hidden="true"><?php echo $overview_term_icon('themes'); ?></span>
                                                <span class="fp-exp-overview__term-label"><?php esc_html_e('Temi esperienza', 'fp-experiences'); ?></span>
                                            </dt>
                                            <dd class="fp-exp-overview__definition">
                                                <ul class="fp-exp-overview__list" role="list">
                                                    <?php foreach ($overview_themes as $theme) : ?>
                                                        <li class="fp-exp-overview__list-item"><?php echo esc_html($theme); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </dd>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (! empty($overview_language_terms)) : ?>
                                        <div class="fp-exp-overview__item">
                                            <dt class="fp-exp-overview__term">
                                                <span class="fp-exp-overview__term-icon" aria-hidden="true"><?php echo $overview_term_icon('languages'); ?></span>
                                                <span class="fp-exp-overview__term-label"><?php esc_html_e('Lingue per filtri', 'fp-experiences'); ?></span>
                                            </dt>
                                            <dd class="fp-exp-overview__definition">
                                                <ul class="fp-exp-overview__list" role="list">
                                                    <?php foreach ($overview_language_terms as $language_term) : ?>
                                                        <li class="fp-exp-overview__list-item fp-exp-overview__list-item--with-icon">
                                                            <span class="fp-exp-overview__flag" aria-hidden="true"><?php echo $get_language_flag_icon($language_term); ?></span>
                                                            <span class="fp-exp-overview__list-text"><?php echo esc_html($language_term); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </dd>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (! empty($overview_duration_terms)) : ?>
                                        <div class="fp-exp-overview__item">
                                            <dt class="fp-exp-overview__term">
                                                <span class="fp-exp-overview__term-icon" aria-hidden="true"><?php echo $overview_term_icon('duration'); ?></span>
                                                <span class="fp-exp-overview__term-label"><?php esc_html_e('Durate aggiuntive', 'fp-experiences'); ?></span>
                                            </dt>
                                            <dd class="fp-exp-overview__definition">
                                                <ul class="fp-exp-overview__list" role="list">
                                                    <?php foreach ($overview_duration_terms as $duration_term) : ?>
                                                        <li class="fp-exp-overview__list-item"><?php echo esc_html($duration_term); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </dd>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (! empty($overview_family_terms) || $overview_family_friendly) : ?>
                                        <div class="fp-exp-overview__item">
                                            <dt class="fp-exp-overview__term">
                                                <span class="fp-exp-overview__term-icon" aria-hidden="true"><?php echo $overview_term_icon('family'); ?></span>
                                                <span class="fp-exp-overview__term-label"><?php esc_html_e('Family friendly', 'fp-experiences'); ?></span>
                                            </dt>
                                            <dd class="fp-exp-overview__definition">
                                                <?php if (! empty($overview_family_terms)) : ?>
                                                    <ul class="fp-exp-overview__list" role="list">
                                                        <?php foreach ($overview_family_terms as $family_term) : ?>
                                                            <li class="fp-exp-overview__list-item"><?php echo esc_html($family_term); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else : ?>
                                                    <span class="fp-exp-overview__value"><?php echo esc_html_x('Yes', 'family friendly indicator', 'fp-experiences'); ?></span>
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (! empty($overview_biases)) : ?>
                        <ul class="fp-exp-overview__trust-list" role="list">
                            <?php foreach ($overview_biases as $bias) :
                                $label = isset($bias['label']) ? (string) $bias['label'] : '';
                                if ('' === $label) {
                                    continue;
                                }

                                $tagline = isset($bias['tagline']) ? (string) $bias['tagline'] : '';
                                $description = isset($bias['description']) ? (string) $bias['description'] : '';
                                $icon_name = isset($bias['icon']) ? (string) $bias['icon'] : '';
                                $icon_svg = \FP_Exp\Utils\Helpers::cognitive_bias_icon_svg($icon_name);
                                $chip_label_parts = array_values(array_filter([$label, $tagline, $description]));
                                $chip_label_text = ! empty($chip_label_parts)
                                    ? implode(' – ', array_unique($chip_label_parts))
                                    : '';
                                ?>
                                <li
                                    class="fp-exp-overview__chip"
                                    <?php if ('' !== $chip_label_text) : ?>title="<?php echo esc_attr($chip_label_text); ?>" aria-label="<?php echo esc_attr($chip_label_text); ?>"<?php endif; ?>
                                >
                                    <span class="fp-exp-overview__chip-icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
                                    <span class="fp-exp-overview__chip-body">
                                        <span class="fp-exp-overview__chip-label"><?php echo esc_html($label); ?></span>
                                        <?php if ('' !== $tagline) : ?>
                                            <span class="fp-exp-overview__chip-tagline"><?php echo esc_html($tagline); ?></span>
                                        <?php endif; ?>
                                        <?php if ('' !== $description) : ?>
                                            <span class="fp-exp-overview__chip-description"><?php echo esc_html($description); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($show_gallery) : ?>
                <section class="fp-exp-section fp-exp-gallery" id="fp-exp-section-gallery" data-fp-section="gallery">
                    <header class="fp-exp-section__header">
                        <span class="fp-exp-section__eyebrow"><?php esc_html_e('Gallery', 'fp-experiences'); ?></span>
                        <h2 class="fp-exp-section__title"><?php esc_html_e('A glimpse of the experience', 'fp-experiences'); ?></h2>
                    </header>
                    <div class="fp-exp-gallery__track" role="list">
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
                <section
                    class="fp-exp-section fp-gift"
                    id="fp-exp-gift"
                    data-fp-gift
                    data-fp-gift-config="<?php echo esc_attr(wp_json_encode($gift_config)); ?>"
                    aria-hidden="true"
                    hidden
                >
                    <div class="fp-gift__inner">
                        <h2 class="fp-gift__title"><?php esc_html_e('Gift this experience', 'fp-experiences'); ?></h2>
                        <p class="fp-gift__intro"><?php esc_html_e('Purchase a voucher, personalise a message, and send it via email in a few clicks.', 'fp-experiences'); ?></p>
                        <div class="fp-gift__feedback" data-fp-gift-feedback aria-live="polite" hidden></div>
                        <form class="fp-gift__form" data-fp-gift-form novalidate>
                            <div class="fp-gift__grid">
                                <div class="fp-gift__field">
                                    <label for="fp-gift-purchaser-name"><?php esc_html_e('Your name', 'fp-experiences'); ?></label>
                                    <input type="text" id="fp-gift-purchaser-name" name="purchaser[name]" required />
                                </div>
                                <div class="fp-gift__field">
                                    <label for="fp-gift-purchaser-email"><?php esc_html_e('Your email', 'fp-experiences'); ?></label>
                                    <input type="email" id="fp-gift-purchaser-email" name="purchaser[email]" required />
                                </div>
                                <div class="fp-gift__field">
                                    <label for="fp-gift-recipient-name"><?php esc_html_e('Recipient name', 'fp-experiences'); ?></label>
                                    <input type="text" id="fp-gift-recipient-name" name="recipient[name]" required />
                                </div>
                                <div class="fp-gift__field">
                                    <label for="fp-gift-recipient-email"><?php esc_html_e('Recipient email', 'fp-experiences'); ?></label>
                                    <input type="email" id="fp-gift-recipient-email" name="recipient[email]" required />
                                </div>
                                <div class="fp-gift__field fp-gift__field--quantity">
                                    <label for="fp-gift-quantity"><?php esc_html_e('Number of guests', 'fp-experiences'); ?></label>
                                    <input type="number" id="fp-gift-quantity" name="quantity" value="1" min="1" step="1" required />
                                </div>
                                <div class="fp-gift__field fp-gift__field--message">
                                    <label for="fp-gift-message"><?php esc_html_e('Personal message (optional)', 'fp-experiences'); ?></label>
                                    <textarea id="fp-gift-message" name="message" rows="3"></textarea>
                                </div>
                            </div>
                            <?php if ($gift_addons) : ?>
                                <fieldset class="fp-gift__addons">
                                    <legend><?php esc_html_e('Prepaid add-ons', 'fp-experiences'); ?></legend>
                                    <div class="fp-gift__addons-grid">
                                        <?php foreach ($gift_addons as $addon) :
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
                                            ?>
                                            <label class="fp-gift__addon">
                                                <input type="checkbox" name="addons[]" value="<?php echo esc_attr((string) ($addon['slug'] ?? '')); ?>" />
                                                <span class="fp-gift__addon-label"><?php echo esc_html((string) ($addon['label'] ?? '')); ?></span>
                                                <?php if (! empty($addon['description'])) : ?>
                                                    <span class="fp-gift__addon-desc"><?php echo esc_html((string) $addon['description']); ?></span>
                                                <?php endif; ?>
                                                <span class="fp-gift__addon-price"><?php echo wp_kses_post($formatted_price); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>
                            <p class="fp-gift__note"><?php esc_html_e('You will review the total and complete payment at checkout. The recipient will receive an email with the voucher code immediately after payment.', 'fp-experiences'); ?></p>
                            <button type="submit" class="fp-exp-button" data-fp-gift-submit>
                                <?php esc_html_e('Proceed to checkout', 'fp-experiences'); ?>
                            </button>
                        </form>
                        <div class="fp-gift__success" data-fp-gift-success hidden></div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['highlights']) && $has_highlights) : ?>
                <section class="fp-exp-section fp-exp-highlights" id="fp-exp-section-highlights" data-fp-section="highlights">
                    <header class="fp-exp-section__header">
                        <span class="fp-exp-section__eyebrow"><?php esc_html_e('Highlights', 'fp-experiences'); ?></span>
                        <h2 class="fp-exp-section__title"><?php esc_html_e('What makes this experience special', 'fp-experiences'); ?></h2>
                    </header>
                    <div class="fp-exp-section__body">
                        <ul class="fp-exp-highlights__list" role="list">
                            <?php foreach ($highlights as $highlight) : ?>
                                <li class="fp-exp-highlights__item">
                                    <span class="fp-exp-highlights__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M9.75 18.25 3.5 12l1.41-1.41 4.84 4.84 9.34-9.34L20.5 7.5Z"/></svg>
                                    </span>
                                    <span class="fp-exp-highlights__text"><?php echo esc_html($highlight); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['inclusions']) && $has_inclusions) : ?>
                <section class="fp-exp-section fp-exp-inclusions" id="fp-exp-section-inclusions" data-fp-section="inclusions">
                    <header class="fp-exp-section__header">
                        <h2 class="fp-exp-section__title"><?php esc_html_e('What\'s included', 'fp-experiences'); ?></h2>
                        <?php if (! empty($exclusions)) : ?>
                            <p class="fp-exp-section__summary"><?php esc_html_e('What to expect on the day and what comes at an extra cost.', 'fp-experiences'); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="fp-exp-section__body">
                        <div class="fp-exp-inclusions__grid">
                            <?php if (! empty($inclusions)) : ?>
                                <div class="fp-exp-inclusions__column">
                                    <h3 class="fp-exp-inclusions__title"><?php esc_html_e('Included', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-inclusions__list" role="list">
                                        <?php foreach ($inclusions as $item) : ?>
                                            <li class="fp-exp-inclusions__item">
                                                <span class="fp-exp-inclusions__icon fp-exp-inclusions__icon--check" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24"><path fill="currentColor" d="M9.75 18.25 3.5 12l1.41-1.41 4.84 4.84 9.34-9.34L20.5 7.5Z"/></svg>
                                                </span>
                                                <span class="fp-exp-inclusions__text"><?php echo esc_html($item); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (! empty($exclusions)) : ?>
                                <div class="fp-exp-inclusions__column">
                                    <h3 class="fp-exp-inclusions__title"><?php esc_html_e('Not included', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-inclusions__list" role="list">
                                        <?php foreach ($exclusions as $item) : ?>
                                            <li class="fp-exp-inclusions__item">
                                                <span class="fp-exp-inclusions__icon fp-exp-inclusions__icon--cross" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24"><path fill="currentColor" d="m18.3 5.71 1.42 1.42-5.3 5.29 5.3 5.29-1.42 1.42-5.29-5.3-5.29 5.3-1.42-1.42 5.3-5.29-5.3-5.29 1.42-1.42 5.29 5.3Z"/></svg>
                                                </span>
                                                <span class="fp-exp-inclusions__text"><?php echo esc_html($item); ?></span>
                                            </li>
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
                        <h2 class="fp-exp-section__title"><?php esc_html_e('Meeting point', 'fp-experiences'); ?></h2>
                        <?php if ('' !== $overview_meeting_summary) : ?>
                            <p class="fp-exp-section__summary"><?php echo esc_html($overview_meeting_summary); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="fp-exp-section__body fp-exp-section__body--flush">
                        <?php
                        $primary = $meeting_points['primary'];
                        $alternatives = $meeting_points['alternatives'];
                        include __DIR__ . '/meeting-points.php';
                        ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (! empty($sections['extras']) && $has_extras) : ?>
                <section class="fp-exp-section fp-exp-essentials" id="fp-exp-section-extras" data-fp-section="extras">
                    <header class="fp-exp-section__header">
                        <h2 class="fp-exp-section__title"><?php esc_html_e('Good to know', 'fp-experiences'); ?></h2>
                        <p class="fp-exp-section__summary"><?php esc_html_e('Handy tips to plan ahead, plus important notes and policies.', 'fp-experiences'); ?></p>
                    </header>
                    <div class="fp-exp-section__body">
                        <div class="fp-exp-essentials__grid">
                            <?php if (! empty($what_to_bring)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('What to bring', 'fp-experiences'); ?></h3>
                                    <ul class="fp-exp-essentials__list" role="list">
                                        <?php foreach ($what_to_bring as $item) : ?>
                                            <li class="fp-exp-essentials__item"><?php echo esc_html($item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </article>
                            <?php endif; ?>

                            <?php if (! empty($notes)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Notes', 'fp-experiences'); ?></h3>
                                    <?php if (is_array($notes)) : ?>
                                        <ul class="fp-exp-essentials__list" role="list">
                                            <?php foreach ($notes as $note) : ?>
                                                <li class="fp-exp-essentials__item"><?php echo esc_html($note); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <p class="fp-exp-essentials__copy"><?php echo esc_html($notes); ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endif; ?>

                            <?php if ('' !== $children_rules) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Regole bambini', 'fp-experiences'); ?></h3>
                                    <p class="fp-exp-essentials__copy"><?php echo esc_html($children_rules); ?></p>
                                </article>
                            <?php endif; ?>

                            <?php if (! empty($policy)) : ?>
                                <article class="fp-exp-essentials__card">
                                    <h3 class="fp-exp-essentials__title"><?php esc_html_e('Cancellation policy', 'fp-experiences'); ?></h3>
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
                        <span class="fp-exp-section__eyebrow"><?php esc_html_e('FAQ', 'fp-experiences'); ?></span>
                        <h2 class="fp-exp-section__title"><?php esc_html_e('Frequently asked questions', 'fp-experiences'); ?></h2>
                    </header>
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
                    <header class="fp-exp-section__header">
                        <span class="fp-exp-section__eyebrow"><?php esc_html_e('Reviews', 'fp-experiences'); ?></span>
                        <h2 class="fp-exp-section__title"><?php esc_html_e('Traveler reviews', 'fp-experiences'); ?></h2>
                    </header>
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
                    <span class="fp-exp-page__sticky-price-label"><?php esc_html_e('From', 'fp-experiences'); ?></span>
                    <span class="fp-exp-page__sticky-price-value"><?php echo esc_html($sticky_price_display); ?></span>
                </span>
            <?php endif; ?>
            <button type="button" class="fp-exp-page__sticky-button" data-fp-scroll="calendar" data-fp-cta="sticky">
                <?php echo $cta_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    <?php endif; ?>
</div>
