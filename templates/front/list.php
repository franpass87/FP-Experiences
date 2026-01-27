<?php
/**
 * Experiences listing template.
 *
 * @var array<int, array<string, mixed>> $experiences
 * @var array<string, mixed>             $filters
 * @var array<string, mixed>             $state
 * @var string                           $view
 * @var int                              $total
 * @var array<int, array<string, mixed>> $pagination_links
 * @var array<int, array<string, mixed>> $tracking_items
 * @var string                           $scope_class
 * @var string                           $schema_json
 */

if (! defined('ABSPATH')) {
    exit;
}

$layout = isset($layout) && is_array($layout) ? $layout : [];
$language_sprite = \FP_Exp\Utils\LanguageHelper::get_sprite_url();
$layout_classes = [];
$variant = isset($variant) ? (string) $variant : 'default';
$is_cards_variant = 'cards' === $variant;

if (! empty($layout['desktop'])) {
    $layout_classes[] = 'fp-listing--cols-desktop-' . (int) $layout['desktop'];
}

if (! empty($layout['tablet'])) {
    $layout_classes[] = 'fp-listing--cols-tablet-' . (int) $layout['tablet'];
}

if (! empty($layout['mobile'])) {
    $layout_classes[] = 'fp-listing--cols-mobile-' . (int) $layout['mobile'];
}

if (! empty($layout['gap'])) {
    $layout_classes[] = 'fp-listing--gap-' . preg_replace('/[^a-z0-9_-]/i', '', $layout['gap']);
}

$container_classes = 'fp-exp ' . esc_attr($scope_class) . ' fp-listing fp-listing--' . esc_attr($view);
if ($layout_classes) {
    $container_classes .= ' ' . esc_attr(implode(' ', $layout_classes));
}
$variant_class = preg_replace('/[^a-z0-9_-]/i', '', $variant);
if ('' !== $variant_class) {
    $container_classes .= ' fp-listing--variant-' . $variant_class;
}
$tracking_json = ! empty($tracking_items) ? wp_json_encode($tracking_items) : '';
$base_view_url = remove_query_arg(['fp_exp_view', 'fp_exp_page']);
$grid_url = add_query_arg(['fp_exp_view' => 'grid', 'fp_exp_page' => 1], $base_view_url);
$list_url = add_query_arg(['fp_exp_view' => 'list', 'fp_exp_page' => 1], $base_view_url);
$current_order = isset($state['order']) ? (string) $state['order'] : 'ASC';
$current_orderby = isset($state['orderby']) ? (string) $state['orderby'] : 'menu_order';
$current_view = isset($state['view']) ? (string) $state['view'] : $view;
$current_page = isset($state['page']) ? (int) $state['page'] : 1;
$results_label = sprintf(
    _n('%d esperienza trovata', '%d esperienze trovate', (int) $total, 'fp-experiences'),
    (int) $total
);

$currency_code = isset($currency) && is_string($currency) ? $currency : (string) get_option('woocommerce_currency', 'EUR');
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
?>
<section
    class="<?php echo $container_classes; ?>"
    data-fp-shortcode="list"
    data-fp-list-view="<?php echo esc_attr($current_view); ?>"
    <?php if ($tracking_json) : ?>data-fp-items='<?php echo esc_attr($tracking_json); ?>'<?php endif; ?>
>
    <header class="fp-listing__header">
        <?php if (! $is_cards_variant) : ?>
            <div class="fp-listing__controls">
                <div class="fp-listing__view" role="group" aria-label="<?php esc_attr_e('Cambia layout', 'fp-experiences'); ?>">
                    <a class="fp-listing__view-toggle<?php echo 'grid' === $current_view ? ' is-active' : ''; ?>" href="<?php echo esc_url($grid_url); ?>"><?php esc_html_e('Griglia', 'fp-experiences'); ?></a>
                    <a class="fp-listing__view-toggle<?php echo 'list' === $current_view ? ' is-active' : ''; ?>" href="<?php echo esc_url($list_url); ?>"><?php esc_html_e('Lista', 'fp-experiences'); ?></a>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($experiences)) : ?>
        <p class="fp-listing__empty"><?php esc_html_e('Nessuna esperienza disponibile al momento. Torna a trovarci presto.', 'fp-experiences'); ?></p>
    <?php else : ?>
        <div class="fp-listing__grid fp-listing__grid--<?php echo esc_attr($current_view); ?><?php echo $is_cards_variant ? ' fp-listing__grid--cards' : ''; ?>">
            <?php foreach ($experiences as $experience) : ?>
                <?php
                $language_labels = isset($experience['language_labels']) && is_array($experience['language_labels']) ? array_values(array_filter(array_map('strval', $experience['language_labels']))) : [];
                $duration_label = isset($experience['duration_label']) ? (string) $experience['duration_label'] : '';
                $primary_theme = isset($experience['primary_theme']) ? (string) $experience['primary_theme'] : '';
                $raw_price_from_display = isset($experience['price_from_display']) ? (string) $experience['price_from_display'] : '';
                $formatted_price_from_display = '' !== $raw_price_from_display ? $format_currency($raw_price_from_display) : '';
                $highlights = [];
                if (! empty($experience['highlights']) && is_array($experience['highlights'])) {
                    $highlights = array_filter($experience['highlights'], 'is_string');
                }
                $short_description = ! empty($experience['short_description']) ? (string) $experience['short_description'] : '';
                ?>
                <?php
                $is_event = isset($experience['is_event']) && $experience['is_event'];
                $card_classes = $is_cards_variant ? 'fp-listing__card fp-listing__card--gyg' : 'fp-listing__card';
                if ($is_event) {
                    $card_classes .= ' fp-listing__card--event fp-listing__card--full-width';
                }
                ?>
                <?php if ($is_cards_variant) : ?>
                    <article
                        class="<?php echo esc_attr($card_classes); ?>"
                        data-experience-id="<?php echo esc_attr((string) $experience['id']); ?>"
                        data-experience-name="<?php echo esc_attr($experience['title']); ?>"
                        data-experience-price="<?php echo esc_attr((string) ($experience['price_from'] ?? '')); ?>"
                    >
                        <a class="fp-listing__media" href="<?php echo esc_url($experience['permalink']); ?>">
                            <?php if (! empty($experience['thumbnail'])) : ?>
                                <img
                                    src="<?php echo esc_url($experience['thumbnail']); ?>"
                                    alt=""
                                    loading="lazy"
                                    class="fp-listing__image"
                                />
                            <?php else : ?>
                                <span class="fp-listing__image fp-listing__image--placeholder" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <div class="fp-listing__body">
                            <?php if ('' !== $primary_theme) : ?>
                                <div class="fp-listing__eyebrow">
                                    <span class="fp-listing__pill"><?php echo esc_html($primary_theme); ?></span>
                                </div>
                            <?php endif; ?>
                            <h3 class="fp-listing__name">
                                <a href="<?php echo esc_url($experience['permalink']); ?>"><?php echo esc_html($experience['title']); ?></a>
                            </h3>
                            <?php if (! empty($highlights) || '' !== $short_description) : ?>
                                <div class="fp-listing__summary">
                                    <?php if (! empty($highlights)) : ?>
                                        <ul class="fp-listing__highlights">
                                            <?php foreach ($highlights as $highlight) : ?>
                                                <li><?php echo esc_html($highlight); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if ('' !== $short_description) : ?>
                                        <div class="fp-listing__description-wrapper">
                                            <p class="fp-listing__description is-clamped" data-fp-text-clamp><?php echo esc_html($short_description); ?></p>
                                            <button type="button" class="fp-listing__read-more" data-fp-read-more aria-expanded="false">
                                                <span class="fp-listing__read-more-text" data-expand-text="<?php esc_attr_e('Leggi di più', 'fp-experiences'); ?>" data-collapse-text="<?php esc_attr_e('Mostra meno', 'fp-experiences'); ?>">
                                                    <?php esc_html_e('Leggi di più', 'fp-experiences'); ?>
                                                </span>
                                                <svg class="fp-listing__read-more-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                                                    <path fill="currentColor" d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ('' !== $duration_label || ! empty($language_labels)) : ?>
                                <div class="fp-listing__meta">
                                    <?php if ('' !== $duration_label) : ?>
                                        <span class="fp-listing__meta-item">
                                            <span class="fp-listing__meta-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" role="img" focusable="false">
                                                    <path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.59 2.12 2.12-1.41 1.41-2.83-2.83V7h2.12Z" />
                                                </svg>
                                            </span>
                                            <span><?php echo esc_html($duration_label); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (! empty($language_labels)) : ?>
                                        <span class="fp-listing__meta-item">
                                            <span class="fp-listing__meta-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" role="img" focusable="false">
                                                    <path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm6.9 9h-2.16a22.46 22.46 0 0 0-1.6-7 8 8 0 0 1 3.76 7ZM12 20a20.41 20.41 0 0 1-2-8h4a20.41 20.41 0 0 1-2 8Zm-2.14-10a20.41 20.41 0 0 1 2.14-8 20.41 20.41 0 0 1 2.14 8Zm-1.1-7a22.46 22.46 0 0 0-1.6 7H5.1A8 8 0 0 1 8.76 3ZM4 13h2.16a22.46 22.46 0 0 0 1.6 7A8 8 0 0 1 4 13Zm11.24 7a22.46 22.46 0 0 0 1.6-7H20a8 8 0 0 1-3.76 7Z" />
                                                </svg>
                                            </span>
                                            <span><?php echo esc_html(implode(', ', $language_labels)); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <footer class="fp-listing__footer fp-listing__footer--gyg">
                            <?php if ('' !== $formatted_price_from_display) : ?>
                                <div class="fp-listing__price">
                                    <span class="fp-listing__price-value"><?php printf(esc_html__('Da %s', 'fp-experiences'), esc_html($formatted_price_from_display)); ?></span>
                                    <span class="fp-listing__price-note"><?php esc_html_e('a persona', 'fp-experiences'); ?></span>
                                </div>
                            <?php endif; ?>
                            <a class="fp-listing__cta" href="<?php echo esc_url($experience['permalink']); ?>"><?php esc_html_e('Dettagli', 'fp-experiences'); ?></a>
                        </footer>
                    </article>
                <?php else : ?>
                    <article
                        class="<?php echo esc_attr($card_classes); ?>"
                        data-experience-id="<?php echo esc_attr((string) $experience['id']); ?>"
                        data-experience-name="<?php echo esc_attr($experience['title']); ?>"
                        data-experience-price="<?php echo esc_attr((string) ($experience['price_from'] ?? '')); ?>"
                    >
                        <a class="fp-listing__media" href="<?php echo esc_url($experience['permalink']); ?>">
                            <?php if (! empty($experience['thumbnail'])) : ?>
                                <img
                                    src="<?php echo esc_url($experience['thumbnail']); ?>"
                                    alt=""
                                    loading="lazy"
                                    class="fp-listing__image"
                                />
                            <?php else : ?>
                                <span class="fp-listing__image fp-listing__image--placeholder" aria-hidden="true"></span>
                            <?php endif; ?>
                            <?php if ('' !== $formatted_price_from_display) : ?>
                                <span class="fp-listing__price-tag"><?php printf(esc_html__('Da %s', 'fp-experiences'), esc_html($formatted_price_from_display)); ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="fp-listing__body">
                            <h3 class="fp-listing__name">
                                <a href="<?php echo esc_url($experience['permalink']); ?>"><?php echo esc_html($experience['title']); ?></a>
                            </h3>
                            <?php if (! empty($experience['badges'])) : ?>
                                <ul class="fp-listing__badges">
                                    <?php foreach ($experience['badges'] as $badge) : ?>
                                        <?php if ('language' === ($badge['context'] ?? '')) :
                                            $language_meta = isset($badge['language']) && is_array($badge['language']) ? $badge['language'] : [];
                                            $sprite_id = isset($language_meta['sprite']) ? (string) $language_meta['sprite'] : '';
                                            $aria_label = isset($language_meta['aria_label']) ? (string) $language_meta['aria_label'] : (string) ($badge['label'] ?? '');
                                            $readable_label = isset($language_meta['label']) ? (string) $language_meta['label'] : (string) ($badge['label'] ?? '');
                                            ?>
                                            <li class="fp-listing__badge fp-listing__badge--language">
                                                <?php if ($sprite_id) : ?>
                                                    <span class="fp-listing__badge-flag" role="img" aria-label="<?php echo esc_attr($aria_label); ?>">
                                                        <svg viewBox="0 0 24 16" aria-hidden="true" focusable="false">
                                                            <use href="<?php echo esc_url($language_sprite . '#' . $sprite_id); ?>"></use>
                                                        </svg>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="fp-listing__badge-text" aria-hidden="true"><?php echo esc_html((string) ($badge['label'] ?? '')); ?></span>
                                                <span class="screen-reader-text"><?php echo esc_html($readable_label); ?></span>
                                            </li>
                                        <?php else : ?>
                                            <?php
                                            $badge_context = isset($badge['context']) ? (string) $badge['context'] : '';
                                            $badge_slug = isset($badge['id']) ? sanitize_html_class((string) $badge['id']) : '';
                                            $badge_classes = ['fp-listing__badge'];

                                            if ('' !== $badge_context) {
                                                $badge_classes[] = 'fp-listing__badge--' . sanitize_html_class($badge_context);
                                            }

                                            if ('' !== $badge_slug) {
                                                $badge_classes[] = 'fp-listing__badge--' . $badge_slug;
                                            }
                                            ?>
                                            <li class="<?php echo esc_attr(implode(' ', $badge_classes)); ?>"><?php echo esc_html((string) ($badge['label'] ?? '')); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (! empty($experience['highlights'])) : ?>
                                <ul class="fp-listing__highlights">
                                    <?php foreach ($experience['highlights'] as $highlight) : ?>
                                        <li><?php echo esc_html($highlight); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif (! empty($experience['short_description'])) : ?>
                                <p class="fp-listing__excerpt"><?php echo esc_html($experience['short_description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <footer class="fp-listing__footer">
                            <a class="fp-listing__cta" href="<?php echo esc_url($experience['permalink']); ?>"><?php esc_html_e('Dettagli', 'fp-experiences'); ?></a>
                            <?php if (! empty($experience['map_url'])) : ?>
                                <a class="fp-listing__map" href="<?php echo esc_url($experience['map_url']); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('Vedi sulla mappa', 'fp-experiences'); ?></a>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($pagination_links)) : ?>
        <nav class="fp-listing__pagination" role="navigation" aria-label="<?php esc_attr_e('Navigazione esperienze', 'fp-experiences'); ?>">
            <ul class="fp-listing__pagination-list">
                <?php
                $prev = $current_page > 1 ? $pagination_links[$current_page - 2] ?? null : null;
                $next = $current_page < count($pagination_links) ? $pagination_links[$current_page] ?? null : null;
                ?>
                <li class="fp-listing__pagination-item fp-listing__pagination-item--prev<?php echo $prev ? '' : ' is-disabled'; ?>">
                    <?php if ($prev) : ?>
                        <a href="<?php echo esc_url($prev['url']); ?>"><?php esc_html_e('Precedente', 'fp-experiences'); ?></a>
                    <?php else : ?>
                        <span><?php esc_html_e('Precedente', 'fp-experiences'); ?></span>
                    <?php endif; ?>
                </li>
                <?php foreach ($pagination_links as $page) : ?>
                    <li class="fp-listing__pagination-item<?php echo ! empty($page['is_current']) ? ' is-current' : ''; ?>">
                        <?php if (! empty($page['is_current'])) : ?>
                            <span aria-current="page"><?php echo esc_html((string) $page['page']); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url($page['url']); ?>"><?php echo esc_html((string) $page['page']); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <li class="fp-listing__pagination-item fp-listing__pagination-item--next<?php echo $next ? '' : ' is-disabled'; ?>">
                    <?php if ($next) : ?>
                        <a href="<?php echo esc_url($next['url']); ?>"><?php esc_html_e('Successivo', 'fp-experiences'); ?></a>
                    <?php else : ?>
                        <span><?php esc_html_e('Successivo', 'fp-experiences'); ?></span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema"><?php echo wp_kses_post($schema_json); ?></script>
    <?php endif; ?>
</section>
