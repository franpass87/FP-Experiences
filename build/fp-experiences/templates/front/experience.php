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
 * @var array<string, mixed> $overview
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

$navigation = isset($navigation) && is_array($navigation) ? $navigation : [];
$sections = isset($sections) && is_array($sections) ? $sections : [];
$has_navigation = ! empty($navigation);
$has_highlights = ! empty($highlights);
$has_inclusions = ! empty($inclusions) || ! empty($exclusions);
$has_meeting = isset($meeting_points['primary']) && is_array($meeting_points['primary']);
$has_extras = ! empty($what_to_bring) || ! empty($notes) || ! empty($policy);
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
    array_slice($gallery, 1),
    static fn ($image) => is_array($image) && ! empty($image['url'])
));
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
$overview_themes = isset($overview['themes']) && is_array($overview['themes']) ? $overview['themes'] : [];
$overview_languages = isset($overview['language_badges']) && is_array($overview['language_badges']) ? $overview['language_badges'] : [];
$overview_language_terms = isset($overview['language_terms']) && is_array($overview['language_terms']) ? $overview['language_terms'] : [];
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
$overview_short_description = isset($overview['short_description']) ? (string) $overview['short_description'] : '';
$overview_meeting = isset($overview['meeting']) && is_array($overview['meeting']) ? $overview['meeting'] : [];
$overview_meeting_title = isset($overview_meeting['title']) ? (string) $overview_meeting['title'] : '';
$overview_meeting_address = isset($overview_meeting['address']) ? (string) $overview_meeting['address'] : '';
$overview_meeting_summary = isset($overview_meeting['summary']) ? (string) $overview_meeting['summary'] : '';
$overview_family = ! empty($overview['family_friendly']);
$overview_has_content = isset($overview_has_content) ? (bool) $overview_has_content : null;

if (null === $overview_has_content) {
    $overview_has_content = ! empty($overview_themes)
        || ! empty($overview_languages)
        || ! empty($overview_biases)
        || '' !== $overview_short_description
        || '' !== $overview_meeting_summary
        || $overview_family;
}

$has_overview = ! empty($sections['overview']) && $overview_has_content;
$cta_label = esc_html__('Controlla disponibilità', 'fp-experiences');

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

    <div class="fp-grid" data-fp-page>
        <main class="fp-main">
            <?php if (! empty($sections['hero'])) : ?>
                <section class="fp-section fp-hero-section" id="fp-exp-section-hero" data-fp-section="hero">
                    <div class="fp-hero-section__inner">
                        <div class="fp-hero-media" aria-hidden="<?php echo null === $primary_image ? 'true' : 'false'; ?>">
                            <?php if ($primary_image) : ?>
                                <img
                                    class="fp-hero-media__image"
                                    src="<?php echo esc_url($primary_image['url']); ?>"
                                    <?php if (! empty($primary_image['srcset'])) : ?>srcset="<?php echo esc_attr($primary_image['srcset']); ?>"<?php endif; ?>
                                    sizes="100vw"
                                    <?php if (! empty($primary_image['width'])) : ?>width="<?php echo esc_attr((string) $primary_image['width']); ?>"<?php endif; ?>
                                    <?php if (! empty($primary_image['height'])) : ?>height="<?php echo esc_attr((string) $primary_image['height']); ?>"<?php endif; ?>
                                    alt="<?php echo esc_attr($experience['title']); ?>"
                                    loading="eager"
                                    decoding="async"
                                    fetchpriority="high"
                                />
                            <?php else : ?>
                                <div class="fp-hero fp-exp-gallery fp-exp-gallery--placeholder">
                                    <span aria-hidden="true"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="fp-hero-body">
                            <div class="fp-hero-body__header">
                                <div class="fp-eyebrow">
                                    <span class="fp-badge">FP Experiences</span>
                                </div>
                                <h1 class="fp-title"><?php echo esc_html($experience['title']); ?></h1>
                            </div>
                            <?php if ('' !== $hero_summary) : ?>
                                <p class="fp-summary"><?php echo esc_html($hero_summary); ?></p>
                            <?php endif; ?>
                            <?php if (! empty($hero_highlights)) : ?>
                                <ul class="fp-hero-highlights" role="list">
                                    <?php foreach ($hero_highlights as $highlight) : ?>
                                        <li class="fp-hero-highlights__item"><?php echo esc_html($highlight); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (! empty($hero_fact_badges)) : ?>
                                <ul class="fp-hero-facts" role="list">
                                    <?php foreach ($hero_fact_badges as $badge) : ?>
                                        <li class="fp-hero-facts__item">
                                                <span class="fp-hero-facts__icon" aria-hidden="true">
                                                    <?php if ('clock' === ($badge['icon'] ?? '')) : ?>
                                                        <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.59 2.12 2.12-1.41 1.41-2.83-2.83V7h2.12Z"/></svg>
                                                    <?php else : ?>
                                                        <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 12.88 9.17 10H5a3 3 0 0 0-3 3v7h6v-4h2v4h6v-7a3 3 0 0 0-3-3h-1.17Zm9-2.88a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/></svg>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="fp-hero-facts__text"><?php echo esc_html((string) ($badge['label'] ?? '')); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($gift_enabled) : ?>
                <section class="fp-exp-section fp-exp-gift" id="fp-exp-hero-gift" data-fp-section="hero-gift">
                    <div class="fp-exp-gift__body">
                        <div class="fp-exp-gift__content">
                            <span class="fp-exp-gift__eyebrow"><?php esc_html_e('Regali', 'fp-experiences'); ?></span>
                            <h2 class="fp-exp-gift__title fp-exp-section__title"><?php esc_html_e('Gift this experience', 'fp-experiences'); ?></h2>
                            <p class="fp-exp-gift__description"><?php esc_html_e('Acquista un voucher e invialo con un messaggio personalizzato in pochi clic.', 'fp-experiences'); ?></p>
                        </div>
                        <a
                            href="#fp-exp-gift"
                            class="fp-exp-button fp-exp-button--secondary"
                            data-fp-gift-toggle
                        >
                            <?php esc_html_e('Gift this experience', 'fp-experiences'); ?>
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($has_overview) : ?>
                <section class="fp-exp-section fp-exp-overview" id="fp-exp-section-overview" data-fp-section="overview">
                    <div class="fp-exp-overview__grid">
                        <?php if (! empty($overview_themes)) : ?>
                            <div class="fp-exp-overview__item">
                                <span class="fp-exp-overview__label"><?php esc_html_e('Themes', 'fp-experiences'); ?></span>
                                <div class="fp-exp-overview__chips">
                                    <?php foreach ($overview_themes as $theme) : ?>
                                        <span class="fp-exp-overview__chip"><?php echo esc_html((string) $theme); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ('' !== $overview_short_description) : ?>
                            <div class="fp-exp-overview__item">
                                <span class="fp-exp-overview__label"><?php esc_html_e('Descrizione breve', 'fp-experiences'); ?></span>
                                <span class="fp-exp-overview__value"><?php echo esc_html($overview_short_description); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($overview_languages)) : ?>
                            <div class="fp-exp-overview__item">
                                <span class="fp-exp-overview__label"><?php esc_html_e('Languages', 'fp-experiences'); ?></span>
                                <ul class="fp-exp-overview__languages" role="list">
                                    <?php foreach ($overview_languages as $badge) :
                                        if (! is_array($badge)) {
                                            continue;
                                        }

                                        $language_meta = isset($badge['language']) && is_array($badge['language']) ? $badge['language'] : [];
                                        $sprite_id = isset($language_meta['sprite']) ? (string) $language_meta['sprite'] : '';
                                        $aria_label = isset($language_meta['aria_label']) ? (string) $language_meta['aria_label'] : (string) ($badge['label'] ?? '');
                                        $readable_label = isset($language_meta['label']) ? (string) $language_meta['label'] : (string) ($badge['label'] ?? '');
                                        $code = isset($language_meta['code']) ? (string) $language_meta['code'] : (string) ($badge['label'] ?? '');
                                        ?>
                                        <li class="fp-exp-overview__language">
                                            <?php if ($sprite_id) : ?>
                                                <span class="fp-exp-overview__language-flag" role="img" aria-label="<?php echo esc_attr($aria_label); ?>">
                                                    <svg viewBox="0 0 24 16" aria-hidden="true" focusable="false">
                                                        <use href="<?php echo esc_url($language_sprite . '#' . $sprite_id); ?>"></use>
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                            <span class="fp-exp-overview__language-code"><?php echo esc_html($code); ?></span>
                                            <span class="screen-reader-text"><?php echo esc_html($readable_label); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (! empty($overview_language_terms)) : ?>
                                    <span class="fp-exp-overview__muted"><?php echo esc_html(implode(', ', array_map('strval', $overview_language_terms))); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($overview_biases)) : ?>
                            <div class="fp-exp-overview__item">
                                <span class="fp-exp-overview__label"><?php esc_html_e('Badge di fiducia', 'fp-experiences'); ?></span>
                                <ul class="fp-exp-overview__chips" role="list">
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
                            </div>
                        <?php endif; ?>

                        <?php if ('' !== $overview_meeting_summary) : ?>
                            <div class="fp-exp-overview__item">
                                <span class="fp-exp-overview__label"><?php esc_html_e('Meeting point', 'fp-experiences'); ?></span>
                                <?php if ('' !== $overview_meeting_title) : ?>
                                    <span class="fp-exp-overview__value"><?php echo esc_html($overview_meeting_title); ?></span>
                                <?php endif; ?>
                                <?php if ('' !== $overview_meeting_address) : ?>
                                    <span class="fp-exp-overview__muted"><?php echo esc_html($overview_meeting_address); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($overview_family) : ?>
                            <div class="fp-exp-overview__item">
                                <span class="fp-exp-overview__label"><?php esc_html_e('Family', 'fp-experiences'); ?></span>
                                <span class="fp-exp-overview__value fp-exp-overview__value--badge"><?php esc_html_e('Family friendly', 'fp-experiences'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($show_gallery) : ?>
                <section class="fp-exp-section fp-exp-gallery" id="fp-exp-section-gallery" data-fp-section="gallery">
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
                    class="fp-section fp-gift"
                    id="fp-exp-gift"
                    data-fp-gift
                    data-fp-gift-config="<?php echo esc_attr(wp_json_encode($gift_config)); ?>"
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

        <?php if ('none' !== $sidebar_position && ! empty($widget_html)) : ?>
            <aside
                class="fp-aside"
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
            <button type="button" class="fp-exp-page__sticky-button" data-fp-scroll="widget" data-fp-cta="sticky">
                <?php echo $cta_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    <?php endif; ?>
</div>
