<?php
/**
 * Simple archive template.
 *
 * @var array<int, array<string, mixed>> $experiences
 * @var string                           $view
 * @var int                              $columns
 * @var string                           $scope_class
 */

if (! defined('ABSPATH')) {
    exit;
}

$view = in_array($view ?? '', ['grid', 'list'], true) ? $view : 'grid';
$columns = max(1, min(4, (int) ($columns ?? 3)));

$class_names = [
    'fp-exp',
    is_string($scope_class ?? null) ? $scope_class : '',
    'fp-simple-archive',
    'fp-simple-archive--' . $view,
    'fp-simple-archive--cols-' . $columns,
];

$class_names = array_filter(array_map('sanitize_html_class', $class_names));
$container_class = implode(' ', $class_names);

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
<section class="<?php echo esc_attr($container_class); ?>" data-fp-shortcode="simple-archive">
    <div class="fp-simple-archive__inner">
        <header class="fp-simple-archive__header">
            <h2 class="fp-simple-archive__title"><?php esc_html_e('Esperienze', 'fp-experiences'); ?></h2>
            <p class="fp-simple-archive__subtitle"><?php esc_html_e('Avventure selezionate pronte da prenotare.', 'fp-experiences'); ?></p>
        </header>

        <?php if (empty($experiences)) : ?>
            <p class="fp-simple-archive__empty"><?php esc_html_e('Nessuna esperienza disponibile al momento. Torna a trovarci presto.', 'fp-experiences'); ?></p>
        <?php else : ?>
            <div class="fp-simple-archive__list">
                <?php foreach ($experiences as $experience) :
                    $title = isset($experience['title']) ? (string) $experience['title'] : '';
                    $details_url = isset($experience['details_url']) ? (string) $experience['details_url'] : '';
                    $booking_url = isset($experience['booking_url']) ? (string) $experience['booking_url'] : $details_url;
                    $thumbnail = isset($experience['thumbnail']) ? (string) $experience['thumbnail'] : '';
                    $duration = isset($experience['duration']) ? (string) $experience['duration'] : '';
                    $price_display = isset($experience['price_from_display']) ? (string) $experience['price_from_display'] : '';
                    $formatted_price_display = '' !== $price_display ? $format_currency($price_display) : '';
                    ?>
                    <article class="fp-simple-archive__card">
                        <a class="fp-simple-archive__media" href="<?php echo esc_url($details_url); ?>">
                            <?php if ($thumbnail) : ?>
                                <img
                                    src="<?php echo esc_url($thumbnail); ?>"
                                    alt="<?php echo esc_attr($title); ?>"
                                    loading="lazy"
                                />
                            <?php else : ?>
                                <span class="fp-simple-archive__placeholder" aria-hidden="true">
                                    <span class="fp-simple-archive__placeholder-icon" aria-hidden="true">📸</span>
                                </span>
                            <?php endif; ?>
                        </a>
                        <div class="fp-simple-archive__body">
                            <h3 class="fp-simple-archive__name">
                                <a href="<?php echo esc_url($details_url); ?>"><?php echo esc_html($title); ?></a>
                            </h3>
                            <?php if ($duration) : ?>
                                <p class="fp-simple-archive__meta fp-simple-archive__meta--duration">
                                    <span class="fp-simple-archive__meta-label"><?php esc_html_e('Durata', 'fp-experiences'); ?>:</span>
                                    <span class="fp-simple-archive__meta-value"><?php echo esc_html($duration); ?></span>
                                </p>
                            <?php endif; ?>
                            <?php if ('' !== $formatted_price_display) : ?>
                                <p class="fp-simple-archive__meta fp-simple-archive__meta--price">
                                    <span class="fp-simple-archive__meta-label"><?php esc_html_e('Da', 'fp-experiences'); ?></span>
                                    <span class="fp-simple-archive__meta-value"><?php echo esc_html($formatted_price_display); ?></span>
                                </p>
                            <?php endif; ?>
                            <div class="fp-simple-archive__actions">
                                <a class="fp-simple-archive__cta fp-simple-archive__cta--details" href="<?php echo esc_url($details_url); ?>">
                                    <?php esc_html_e('Dettagli', 'fp-experiences'); ?>
                                </a>
                                <a class="fp-simple-archive__cta fp-simple-archive__cta--book" href="<?php echo esc_url($booking_url); ?>">
                                    <?php esc_html_e('Prenota', 'fp-experiences'); ?>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
