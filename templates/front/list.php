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
$layout_classes = [];
$filter_chips = isset($filter_chips) && is_array($filter_chips) ? $filter_chips : [];
$has_active_filters = ! empty($has_active_filters);
$reset_url = isset($reset_url) ? (string) $reset_url : '';

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
$tracking_json = ! empty($tracking_items) ? wp_json_encode($tracking_items) : '';
$base_view_url = remove_query_arg(['fp_exp_view', 'fp_exp_page']);
$grid_url = add_query_arg(['fp_exp_view' => 'grid', 'fp_exp_page' => 1], $base_view_url);
$list_url = add_query_arg(['fp_exp_view' => 'list', 'fp_exp_page' => 1], $base_view_url);
$current_order = isset($state['order']) ? (string) $state['order'] : 'ASC';
$current_orderby = isset($state['orderby']) ? (string) $state['orderby'] : 'menu_order';
$current_view = isset($state['view']) ? (string) $state['view'] : $view;
$current_page = isset($state['page']) ? (int) $state['page'] : 1;
$results_label = sprintf(
    _n('%d experience found', '%d experiences found', (int) $total, 'fp-experiences'),
    (int) $total
);
?>
<section
    class="<?php echo $container_classes; ?>"
    data-fp-shortcode="list"
    data-fp-list-view="<?php echo esc_attr($current_view); ?>"
    <?php if ($tracking_json) : ?>data-fp-items='<?php echo esc_attr($tracking_json); ?>'<?php endif; ?>
>
    <header class="fp-listing__header">
        <div class="fp-listing__intro">
            <h2 class="fp-listing__title"><?php esc_html_e('Experiences', 'fp-experiences'); ?></h2>
            <p class="fp-listing__count" aria-live="polite"><?php echo esc_html($results_label); ?></p>
        </div>
        <div class="fp-listing__controls">
            <?php if ((! empty($filter_chips) || $has_active_filters) && $reset_url) : ?>
                <div class="fp-listing__chips" aria-live="polite">
                    <?php foreach ($filter_chips as $chip) : ?>
                        <a class="fp-listing__chip" href="<?php echo esc_url($chip['url']); ?>">
                            <span class="fp-listing__chip-text"><?php echo esc_html($chip['label']); ?></span>
                            <span class="fp-listing__chip-close" aria-hidden="true">&times;</span>
                            <span class="screen-reader-text"><?php echo esc_html($chip['sr_label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="fp-listing__chip fp-listing__chip--clear" href="<?php echo esc_url($reset_url); ?>">
                        <span class="fp-listing__chip-text"><?php esc_html_e('Reset filters', 'fp-experiences'); ?></span>
                    </a>
                </div>
            <?php endif; ?>
            <form class="fp-listing__filters" method="get">
                <input type="hidden" name="fp_exp_page" value="1" />
                <input type="hidden" name="fp_exp_view" value="<?php echo esc_attr($current_view); ?>" />
                <?php if (isset($filters['search'])) : ?>
                    <div class="fp-listing__field fp-listing__field--search">
                        <label class="fp-listing__label" for="fp-exp-search"><?php esc_html_e('Search', 'fp-experiences'); ?></label>
                        <input
                            type="search"
                            id="fp-exp-search"
                            name="fp_exp_search"
                            class="fp-listing__input"
                            value="<?php echo esc_attr((string) ($filters['search']['value'] ?? '')); ?>"
                            placeholder="<?php esc_attr_e('Search experiences…', 'fp-experiences'); ?>"
                        />
                    </div>
                <?php endif; ?>

                <?php if (isset($filters['theme'])) : ?>
                    <div class="fp-listing__field">
                        <label class="fp-listing__label" for="fp-exp-theme"><?php esc_html_e('Theme', 'fp-experiences'); ?></label>
                        <select id="fp-exp-theme" name="fp_exp_theme[]" class="fp-listing__select" multiple>
                            <?php foreach ($filters['theme']['choices'] as $choice) : ?>
                                <option value="<?php echo esc_attr($choice['slug']); ?>" <?php selected(in_array($choice['slug'], (array) ($filters['theme']['value'] ?? []), true)); ?>>
                                    <?php echo esc_html($choice['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (isset($filters['language'])) : ?>
                    <div class="fp-listing__field">
                        <label class="fp-listing__label" for="fp-exp-language"><?php esc_html_e('Language', 'fp-experiences'); ?></label>
                        <select id="fp-exp-language" name="fp_exp_language[]" class="fp-listing__select" multiple>
                            <?php foreach ($filters['language']['choices'] as $choice) : ?>
                                <option value="<?php echo esc_attr($choice['slug']); ?>" <?php selected(in_array($choice['slug'], (array) ($filters['language']['value'] ?? []), true)); ?>>
                                    <?php echo esc_html($choice['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (isset($filters['duration'])) : ?>
                    <div class="fp-listing__field">
                        <label class="fp-listing__label" for="fp-exp-duration"><?php esc_html_e('Duration', 'fp-experiences'); ?></label>
                        <select id="fp-exp-duration" name="fp_exp_duration[]" class="fp-listing__select" multiple>
                            <?php foreach ($filters['duration']['choices'] as $choice) : ?>
                                <option value="<?php echo esc_attr($choice['slug']); ?>" <?php selected(in_array($choice['slug'], (array) ($filters['duration']['value'] ?? []), true)); ?>>
                                    <?php echo esc_html($choice['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (isset($filters['price'])) :
                    $price_min = max(0.0, (float) ($filters['price']['min'] ?? 0));
                    $price_max = max($price_min, (float) ($filters['price']['max'] ?? 0));
                    $current_min_price = max($price_min, (float) ($filters['price']['value']['min'] ?? $price_min));
                    $current_max_price = max($current_min_price, (float) ($filters['price']['value']['max'] ?? $price_max));
                    ?>
                    <div class="fp-listing__field fp-listing__field--range">
                        <span class="fp-listing__label"><?php esc_html_e('Price range (€)', 'fp-experiences'); ?></span>
                        <div class="fp-listing__range-inputs">
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Minimum price', 'fp-experiences'); ?></span>
                                <input
                                    type="number"
                                    name="fp_exp_price_min"
                                    class="fp-listing__input"
                                    min="<?php echo esc_attr((string) $price_min); ?>"
                                    max="<?php echo esc_attr((string) $price_max); ?>"
                                    value="<?php echo esc_attr((string) $current_min_price); ?>"
                                />
                            </label>
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Maximum price', 'fp-experiences'); ?></span>
                                <input
                                    type="number"
                                    name="fp_exp_price_max"
                                    class="fp-listing__input"
                                    min="<?php echo esc_attr((string) $price_min); ?>"
                                    max="<?php echo esc_attr((string) $price_max); ?>"
                                    value="<?php echo esc_attr((string) $current_max_price); ?>"
                                />
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($filters['family'])) : ?>
                    <div class="fp-listing__field fp-listing__field--checkbox">
                        <label class="fp-listing__checkbox">
                            <input type="checkbox" name="fp_exp_family" value="1" <?php checked(! empty($filters['family']['value'])); ?> />
                            <span><?php esc_html_e('Family-friendly only', 'fp-experiences'); ?></span>
                        </label>
                    </div>
                <?php endif; ?>

                <?php if (isset($filters['date'])) : ?>
                    <div class="fp-listing__field">
                        <label class="fp-listing__label" for="fp-exp-date"><?php esc_html_e('Date', 'fp-experiences'); ?></label>
                        <input
                            type="date"
                            id="fp-exp-date"
                            name="fp_exp_date"
                            class="fp-listing__input"
                            value="<?php echo esc_attr((string) ($filters['date']['value'] ?? '')); ?>"
                        />
                    </div>
                <?php endif; ?>

                <div class="fp-listing__field fp-listing__field--sort">
                    <label class="fp-listing__label" for="fp-exp-orderby"><?php esc_html_e('Sort by', 'fp-experiences'); ?></label>
                    <select id="fp-exp-orderby" name="fp_exp_orderby" class="fp-listing__select">
                        <option value="menu_order" <?php selected('menu_order' === $current_orderby); ?>><?php esc_html_e('Featured', 'fp-experiences'); ?></option>
                        <option value="date" <?php selected('date' === $current_orderby); ?>><?php esc_html_e('Publish date', 'fp-experiences'); ?></option>
                        <option value="title" <?php selected('title' === $current_orderby); ?>><?php esc_html_e('Title', 'fp-experiences'); ?></option>
                        <option value="price" <?php selected('price' === $current_orderby); ?>><?php esc_html_e('Price', 'fp-experiences'); ?></option>
                    </select>
                </div>

                <div class="fp-listing__field fp-listing__field--order">
                    <label class="fp-listing__label" for="fp-exp-order"><?php esc_html_e('Order', 'fp-experiences'); ?></label>
                    <select id="fp-exp-order" name="fp_exp_order" class="fp-listing__select">
                        <option value="ASC" <?php selected('ASC' === $current_order); ?>><?php esc_html_e('Ascending', 'fp-experiences'); ?></option>
                        <option value="DESC" <?php selected('DESC' === $current_order); ?>><?php esc_html_e('Descending', 'fp-experiences'); ?></option>
                    </select>
                </div>

                <div class="fp-listing__actions">
                    <button type="submit" class="fp-listing__submit"><?php esc_html_e('Apply filters', 'fp-experiences'); ?></button>
                </div>
            </form>
            <div class="fp-listing__view" role="group" aria-label="<?php esc_attr_e('Change layout', 'fp-experiences'); ?>">
                <a class="fp-listing__view-toggle<?php echo 'grid' === $current_view ? ' is-active' : ''; ?>" href="<?php echo esc_url($grid_url); ?>"><?php esc_html_e('Grid', 'fp-experiences'); ?></a>
                <a class="fp-listing__view-toggle<?php echo 'list' === $current_view ? ' is-active' : ''; ?>" href="<?php echo esc_url($list_url); ?>"><?php esc_html_e('List', 'fp-experiences'); ?></a>
            </div>
        </div>
    </header>

    <?php if (empty($experiences)) : ?>
        <p class="fp-listing__empty"><?php esc_html_e('No experiences match your filters. Try adjusting your search.', 'fp-experiences'); ?></p>
    <?php else : ?>
        <div class="fp-listing__grid fp-listing__grid--<?php echo esc_attr($current_view); ?>">
            <?php foreach ($experiences as $experience) : ?>
                <article
                    class="fp-listing__card"
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
                        <?php if (! empty($experience['price_from_display'])) : ?>
                            <span class="fp-listing__price-tag"><?php printf(esc_html__('From €%s', 'fp-experiences'), esc_html($experience['price_from_display'])); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="fp-listing__body">
                        <h3 class="fp-listing__name">
                            <a href="<?php echo esc_url($experience['permalink']); ?>"><?php echo esc_html($experience['title']); ?></a>
                        </h3>
                        <?php if (! empty($experience['badges'])) : ?>
                            <ul class="fp-listing__badges">
                                <?php foreach ($experience['badges'] as $badge) : ?>
                                    <li class="fp-listing__badge fp-listing__badge--<?php echo esc_attr($badge['context']); ?>"><?php echo esc_html($badge['label']); ?></li>
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
                        <a class="fp-listing__cta" href="<?php echo esc_url($experience['permalink']); ?>"><?php esc_html_e('Details', 'fp-experiences'); ?></a>
                        <?php if (! empty($experience['map_url'])) : ?>
                            <a class="fp-listing__map" href="<?php echo esc_url($experience['map_url']); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('View on map', 'fp-experiences'); ?></a>
                        <?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($pagination_links)) : ?>
        <nav class="fp-listing__pagination" role="navigation" aria-label="<?php esc_attr_e('Experiences navigation', 'fp-experiences'); ?>">
            <ul class="fp-listing__pagination-list">
                <?php
                $prev = $current_page > 1 ? $pagination_links[$current_page - 2] ?? null : null;
                $next = $current_page < count($pagination_links) ? $pagination_links[$current_page] ?? null : null;
                ?>
                <li class="fp-listing__pagination-item fp-listing__pagination-item--prev<?php echo $prev ? '' : ' is-disabled'; ?>">
                    <?php if ($prev) : ?>
                        <a href="<?php echo esc_url($prev['url']); ?>"><?php esc_html_e('Previous', 'fp-experiences'); ?></a>
                    <?php else : ?>
                        <span><?php esc_html_e('Previous', 'fp-experiences'); ?></span>
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
                        <a href="<?php echo esc_url($next['url']); ?>"><?php esc_html_e('Next', 'fp-experiences'); ?></a>
                    <?php else : ?>
                        <span><?php esc_html_e('Next', 'fp-experiences'); ?></span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema"><?php echo wp_kses_post($schema_json); ?></script>
    <?php endif; ?>
</section>
