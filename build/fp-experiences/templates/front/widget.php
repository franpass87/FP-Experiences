<?php
/**
 * Booking widget template.
 *
 * @var array<string, mixed> $experience
 * @var array<int, array<string, mixed>> $tickets
 * @var array<int, array<string, mixed>> $addons
 * @var array<int, array<string, mixed>> $slots
 * @var array<string, array<int, array<string, mixed>>> $calendar
 * @var array<string, bool> $behavior
 * @var array<string, mixed> $rtb
 * @var string $rtb_nonce
 * @var string $scope_class
 * @var string $schema_json
 * @var string $display_context
 */

if (! defined('ABSPATH')) {
    exit;
}

$language_sprite = \FP_Exp\Utils\LanguageHelper::get_sprite_url();


$dialog_id = $scope_class . '-dialog';
$marketing_id = $scope_class . '-consent-marketing';
$privacy_id = $scope_class . '-consent-privacy';

$display_context = isset($display_context) ? (string) $display_context : '';
$config_version = isset($config_version) ? (string) $config_version : '';

$slots = is_array($slots) ? $slots : [];
$tickets = is_array($tickets) ? $tickets : [];
$addons = is_array($addons) ? $addons : [];
$calendar = is_array($calendar) ? $calendar : [];
$behavior_defaults = [
    'sticky' => false,
    'show_calendar' => false,
];
$behavior = is_array($behavior) ? array_merge($behavior_defaults, $behavior) : $behavior_defaults;
$rtb_defaults = [
    'enabled' => false,
    'mode' => 'off',
    'forced' => false,
];
$rtb = is_array($rtb) ? array_merge($rtb_defaults, $rtb) : $rtb_defaults;

$dataset = [
    'experienceId' => (int) $experience['id'],
    'experienceTitle' => wp_strip_all_tags((string) $experience['title']),
    'experienceUrl' => esc_url_raw((string) ($experience['permalink'] ?? '')),
    'slots' => $slots,
    'tickets' => $tickets,
    'addons' => $addons,
    'calendar' => $calendar,
    'behavior' => $behavior,
    'rtb' => $rtb,
    'nonce' => $rtb_nonce,
    'displayContext' => $display_context,
    'timezone' => (string) wp_timezone_string(),
    'version' => $config_version,
];

$container_class = 'fp-exp fp-exp-widget ' . esc_attr($scope_class);
$rtb_enabled = ! empty($rtb['enabled']);
$rtb_mode = isset($rtb['mode']) ? (string) $rtb['mode'] : 'off';
$rtb_forced = ! empty($rtb['forced']);
$rtb_submit_label = 'pay_later' === $rtb_mode
    ? esc_html__('Invia approvazione con link di pagamento', 'fp-experiences')
    : esc_html__('Invia richiesta di prenotazione', 'fp-experiences');

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
$cta_label = esc_html__('Controlla disponibilità', 'fp-experiences');

$language_badges = isset($experience['language_badges']) && is_array($experience['language_badges'])
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

$duration_minutes = isset($experience['duration']) ? (int) $experience['duration'] : 0;
$duration_label = '';

if ($duration_minutes > 0) {
    $hours = (int) floor($duration_minutes / 60);
    $minutes = $duration_minutes % 60;
    $duration_label = $hours > 0
        ? sprintf(esc_html__('%dh %02dm', 'fp-experiences'), $hours, $minutes)
        : sprintf(esc_html__('%d minuti', 'fp-experiences'), $minutes);
}

$price_from_value = null;

foreach ($slots as $slot) {
    if (! is_array($slot)) {
        continue;
    }

    $price = isset($slot['price_from']) ? (float) $slot['price_from'] : 0.0;

    if ($price <= 0) {
        continue;
    }

    $price_from_value = null === $price_from_value ? $price : min($price_from_value, $price);
}

if (null === $price_from_value) {
    foreach ($tickets as $ticket) {
        if (! is_array($ticket)) {
            continue;
        }

        $price = isset($ticket['price']) ? (float) $ticket['price'] : 0.0;

        if ($price <= 0) {
            continue;
        }

        $price_from_value = null === $price_from_value ? $price : min($price_from_value, $price);
    }
}

$price_from_display = null !== $price_from_value && $price_from_value > 0
    ? $format_currency(number_format_i18n($price_from_value, 0))
    : '';
?>
<div
    class="<?php echo $container_class; ?>"
    data-fp-shortcode="widget"
    data-config="<?php echo esc_attr(wp_json_encode($dataset, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>"
    data-sticky="<?php echo esc_attr($behavior['sticky'] ? '1' : '0'); ?>"
    data-display-context="<?php echo esc_attr($display_context); ?>"
    data-config-version="<?php echo esc_attr($config_version); ?>"
>
    <div
        class="fp-exp-widget__body"
        data-sticky="<?php echo esc_attr($behavior['sticky'] ? '1' : '0'); ?>"
        id="<?php echo esc_attr($dialog_id); ?>"
        <?php if ($behavior['sticky']) : ?>role="region"<?php else : ?>role="group"<?php endif; ?>
    >
        <div class="fp-exp-hero__card fp-exp-widget__hero-card">
            <?php if ('' !== $price_from_display) : ?>
                <div class="fp-exp-hero__price" data-fp-scroll-target="calendar">
                    <span class="fp-exp-hero__price-label"><?php esc_html_e('Da', 'fp-experiences'); ?></span>
                    <span class="fp-exp-hero__price-value"><?php echo esc_html($price_from_display); ?></span>
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
            </div>

            <?php if (! empty($language_badges) || '' !== $duration_label) : ?>
                <ul class="fp-exp-hero__facts fp-exp-hero__facts--widget" role="list">
                    <?php if (! empty($language_badges)) : ?>
                        <li class="fp-exp-hero__fact fp-exp-hero__fact--languages">
                            <span class="fp-exp-hero__fact-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="img" aria-hidden="true" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5.33 9h-1.83a19.46 19.46 0 0 0-.87-4 8 8 0 0 1 2.7 4ZM12 4a17.43 17.43 0 0 1 2.44 7H9.56A17.43 17.43 0 0 1 12 4ZM8.37 6.91a19.46 19.46 0 0 0-.87 4H5.67a8 8 0 0 1 2.7-4ZM4 12h3.5a19.43 19.43 0 0 0 .88 4H6.33A8 8 0 0 1 4 12Zm2.37 6h2.64a21.13 21.13 0 0 0 1.87 3.38A8 8 0 0 1 6.37 18Zm5.63 3a19.1 19.1 0 0 1-2.55-5h5.1A19.1 19.1 0 0 1 12 21Zm2.69.38A21.13 21.13 0 0 0 15 18h2.64a8 8 0 0 1-3 3.38ZM17.67 16H15.62a19.43 19.43 0 0 0 .88-4H20a8 8 0 0 1-2.33 4Z"/></svg>
                            </span>
                            <div class="fp-exp-hero__fact-content">
                                <span class="fp-exp-hero__fact-label"><?php esc_html_e('Lingue disponibili', 'fp-experiences'); ?></span>
                                <ul class="fp-exp-hero__language-list" role="list">
                                    <?php foreach ($language_badges as $language) : ?>
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

                    <?php if ('' !== $duration_label) : ?>
                        <li class="fp-exp-hero__fact fp-exp-hero__fact--duration">
                            <span class="fp-exp-hero__fact-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="img" aria-hidden="true" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.59 2.12 2.12-1.41 1.41-2.83-2.83V7h2.12Z"/></svg>
                            </span>
                            <div class="fp-exp-hero__fact-content">
                                <span class="fp-exp-hero__fact-label"><?php esc_html_e('Durata', 'fp-experiences'); ?></span>
                                <span class="fp-exp-hero__fact-value"><?php echo esc_html($duration_label); ?></span>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>

        <ol class="fp-exp-widget__steps">
            <li class="fp-exp-step fp-exp-step--dates" data-fp-step="dates" data-fp-section="calendar">
                <header>
                    <span class="fp-exp-step__number">1</span>
                    <h3 class="fp-exp-step__title"><?php echo esc_html__('Scegli una data', 'fp-experiences'); ?></h3>
                </header>
                <div class="fp-exp-step__content">
                    <?php
                    // Limita solo la data minima all'oggi; nessun limite massimo
                    $min_date_attr = gmdate('Y-m-d');
                    ?>
                    <div class="fp-exp-date-picker">
                        <label class="fp-exp-label" for="fp-exp-date-input"><?php echo esc_html__('Data', 'fp-experiences'); ?></label>
                        <input
                            type="date"
                            id="fp-exp-date-input"
                            class="fp-exp-input fp-exp-date-input"
                            min="<?php echo esc_attr($min_date_attr); ?>"
                            data-fp-date-input
                        />
                    </div>
                    <div class="fp-exp-calendar" data-show-calendar="<?php echo esc_attr($behavior['show_calendar'] ? '1' : '0'); ?>" hidden>
                        <?php foreach ($calendar as $month_key => $month_data) :
                            $month_label = isset($month_data['month_label']) ? (string) $month_data['month_label'] : '';
                            $month_days = isset($month_data['days']) && is_array($month_data['days']) ? $month_data['days'] : [];
                            ?>
                            <section class="fp-exp-calendar__month" data-month="<?php echo esc_attr($month_key); ?>">
                                <header class="fp-exp-calendar__month-header"><?php echo esc_html($month_label); ?></header>
                                <?php
                                // Calcolo intestazioni giorni e riempimento griglia 7xN
                                try {
                                    $first_of_month = new \DateTimeImmutable($month_key . '-01');
                                    $days_in_month = (int) $first_of_month->format('t');
                                    $leading = max(0, (int) $first_of_month->format('N') - 1); // Lun=1..Dom=7
                                } catch (\Exception $e) {
                                    $first_of_month = null;
                                    $days_in_month = 31;
                                    $leading = 0;
                                }

                                // Etichette giorni (Lun..Dom) basate su locale breve
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
                </div>
            </li>
            <li class="fp-exp-step fp-exp-step--party" data-fp-step="party">
                <header>
                    <span class="fp-exp-step__number">2</span>
                    <h3 class="fp-exp-step__title"><?php echo esc_html__('Seleziona i biglietti', 'fp-experiences'); ?></h3>
                </header>
                <div class="fp-exp-step__content">
                    <table class="fp-exp-party-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('Tipo biglietto', 'fp-experiences'); ?></th>
                                <th scope="col"><?php echo esc_html__('Prezzo', 'fp-experiences'); ?></th>
                                <th scope="col"><?php echo esc_html__('Quantità', 'fp-experiences'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket) : ?>
                                <tr data-ticket="<?php echo esc_attr($ticket['slug']); ?>">
                                    <th scope="row">
                                        <span class="fp-exp-ticket__label"><?php echo esc_html($ticket['label']); ?></span>
                                        <?php if (! empty($ticket['description'])) : ?>
                                            <small class="fp-exp-ticket__description"><?php echo esc_html($ticket['description']); ?></small>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <?php
                                        $ticket_price_display = $format_currency(number_format_i18n((float) $ticket['price'], 2));
                                        ?>
                                        <span class="fp-exp-ticket__price" data-price="<?php echo esc_attr((string) $ticket['price']); ?>"><?php echo esc_html($ticket_price_display); ?></span>
                                    </td>
                                    <td>
                                        <div class="fp-exp-quantity">
                                            <button type="button" class="fp-exp-quantity__control" data-action="decrease" aria-label="<?php echo esc_attr(sprintf(esc_html__('Riduci %s', 'fp-experiences'), $ticket['label'])); ?>">
                                                <span class="screen-reader-text"><?php echo esc_html(sprintf(esc_html__('Riduci %s', 'fp-experiences'), $ticket['label'])); ?></span>
                                                <span aria-hidden="true" class="fp-exp-quantity__icon">
                                                    <svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false" width="18" height="18">
                                                        <path d="M6 12h12" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8" />
                                                    </svg>
                                                </span>
                                            </button>
                                            <input type="number" class="fp-exp-quantity__input" min="0" max="<?php echo esc_attr((string) ($ticket['cap'] ?? '')); ?>" value="0" aria-label="<?php echo esc_attr(sprintf(esc_html__('Quantità %s', 'fp-experiences'), $ticket['label'])); ?>">
                                            <button type="button" class="fp-exp-quantity__control" data-action="increase" aria-label="<?php echo esc_attr(sprintf(esc_html__('Aumenta %s', 'fp-experiences'), $ticket['label'])); ?>">
                                                <span class="screen-reader-text"><?php echo esc_html(sprintf(esc_html__('Aumenta %s', 'fp-experiences'), $ticket['label'])); ?></span>
                                                <span aria-hidden="true" class="fp-exp-quantity__icon">
                                                    <svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false" width="18" height="18">
                                                        <path d="M12 6v12" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8" />
                                                        <path d="M6 12h12" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8" />
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </li>
            <?php if (! empty($addons)) : ?>
                <li class="fp-exp-step fp-exp-step--addons" data-fp-step="addons">
                    <header>
                        <span class="fp-exp-step__number">3</span>
                        <h3 class="fp-exp-step__title"><?php echo esc_html__('Extra', 'fp-experiences'); ?></h3>
                    </header>
                    <div class="fp-exp-step__content">
                        <ul class="fp-exp-addons">
                            <?php foreach ($addons as $addon) : ?>
                                <?php
                                $addon_image = $addon['image'] ?? [];
                                $image_url = isset($addon_image['url']) ? (string) $addon_image['url'] : '';
                                $image_width = isset($addon_image['width']) ? (int) $addon_image['width'] : 0;
                                $image_height = isset($addon_image['height']) ? (int) $addon_image['height'] : 0;
                                ?>
                                <li class="fp-exp-addon" data-addon="<?php echo esc_attr($addon['slug']); ?>">
                                    <label class="fp-exp-addon__card">
                                        <span class="fp-exp-addon__input">
                                            <input type="checkbox" value="1">
                                        </span>
                                        <span class="fp-exp-addon__media">
                                            <?php if ($image_url) : ?>
                                                <img
                                                    src="<?php echo esc_url($image_url); ?>"
                                                    alt="<?php echo esc_attr($addon['label']); ?>"
                                                    loading="lazy"
                                                    <?php if ($image_width > 0) : ?> width="<?php echo esc_attr((string) $image_width); ?>"<?php endif; ?>
                                                    <?php if ($image_height > 0) : ?> height="<?php echo esc_attr((string) $image_height); ?>"<?php endif; ?>
                                                />
                                            <?php else : ?>
                                                <span class="fp-exp-addon__media-placeholder" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5">
                                                            <rect x="3.75" y="8.25" width="16.5" height="12" rx="2" />
                                                            <path d="M3.75 11.25h16.5" />
                                                            <path d="M12 3.75c-1.657 0-3 1.231-3 2.75 0 1.519 1.343 2.75 3 2.75s3-1.231 3-2.75c0-1.519-1.343-2.75-3-2.75Zm0 0C12 3 11.25 2.25 10.5 2.25S9 3 9 3.75" />
                                                            <path d="M12 3.75c0-.75.75-1.5 1.5-1.5s1.5.75 1.5 1.5" />
                                                        </g>
                                                    </svg>
                                                </span>
                                                <span class="screen-reader-text"><?php esc_html_e('Nessuna immagine disponibile per questo extra', 'fp-experiences'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="fp-exp-addon__content">
                                            <span class="fp-exp-addon__header">
                                                <span class="fp-exp-addon__label"><?php echo esc_html($addon['label']); ?></span>
                                                <?php
                                                $addon_price_display = $format_currency(number_format_i18n((float) $addon['price'], 2));
                                                ?>
                                                <span class="fp-exp-addon__price" data-price="<?php echo esc_attr((string) $addon['price']); ?>"><?php echo esc_html($addon_price_display); ?></span>
                                            </span>
                                            <?php if (! empty($addon['description'])) : ?>
                                                <p class="fp-exp-addon__summary"><?php echo esc_html($addon['description']); ?></p>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php endif; ?>
            <li class="fp-exp-step fp-exp-step--summary" data-fp-step="summary">
                <header>
                    <span class="fp-exp-step__number"><?php echo esc_html(empty($addons) ? '3' : '4'); ?></span>
                    <h3 class="fp-exp-step__title"><?php echo esc_html__('Riepilogo', 'fp-experiences'); ?></h3>
                </header>
                <div class="fp-exp-step__content">
                    <div
                        class="fp-exp-summary"
                        data-empty-label="<?php echo esc_attr__('Seleziona i biglietti per vedere il riepilogo', 'fp-experiences'); ?>"
                        data-loading-label="<?php echo esc_attr__('Aggiornamento prezzo…', 'fp-experiences'); ?>"
                        data-error-label="<?php echo esc_attr__('Impossibile aggiornare il prezzo. Riprova.', 'fp-experiences'); ?>"
                        data-slot-label="<?php echo esc_attr__('Scegli un orario per confermare prezzo e disponibilità.', 'fp-experiences'); ?>"
                        data-tax-label="<?php echo esc_attr__('Tasse incluse ove applicabile.', 'fp-experiences'); ?>"
                        data-base-label="<?php echo esc_attr__('Prezzo base', 'fp-experiences'); ?>"
                    >
                        <div class="fp-exp-summary__status" data-fp-summary-status role="status" aria-live="polite">
                            <p class="fp-exp-summary__message"><?php echo esc_html__('Seleziona i biglietti per vedere il riepilogo', 'fp-experiences'); ?></p>
                        </div>
                        <div class="fp-exp-summary__body" data-fp-summary-body hidden>
                            <ul class="fp-exp-summary__lines" data-fp-summary-lines></ul>
                            <ul class="fp-exp-summary__adjustments" data-fp-summary-adjustments hidden></ul>
                            <div class="fp-exp-summary__total" data-fp-summary-total-row>
                                <span class="fp-exp-summary__total-label"><?php esc_html_e('Totale', 'fp-experiences'); ?></span>
                                <span class="fp-exp-summary__total-amount" data-fp-summary-total></span>
                            </div>
                            <p class="fp-exp-summary__disclaimer" data-fp-summary-disclaimer hidden></p>
                        </div>
                    </div>
                    <?php if ($rtb_enabled) : ?>
                        <form
                            class="fp-exp-rtb-form"
                            data-fp-rtb-form="1"
                            data-nonce="<?php echo esc_attr($rtb_nonce); ?>"
                            data-error-name="<?php echo esc_attr__('Inserisci il tuo nome.', 'fp-experiences'); ?>"
                            data-error-email="<?php echo esc_attr__('Inserisci il tuo indirizzo email.', 'fp-experiences'); ?>"
                            data-error-email-format="<?php echo esc_attr__('Inserisci un indirizzo email valido.', 'fp-experiences'); ?>"
                            data-error-privacy="<?php echo esc_attr__('Accetta l\'informativa privacy per continuare.', 'fp-experiences'); ?>"
                        >
                            <input type="hidden" name="experience_id" value="<?php echo esc_attr((string) $experience['id']); ?>">
                            <input type="hidden" name="slot_id" value="">
                            <input type="hidden" name="tickets" value="">
                            <input type="hidden" name="addons" value="">
                            <input type="hidden" name="mode" value="<?php echo esc_attr($rtb_mode); ?>">
                            <input type="hidden" name="forced" value="<?php echo esc_attr($rtb_forced ? '1' : '0'); ?>">
                            <input type="hidden" name="start" value="" />
                            <input type="hidden" name="end" value="" />
                            <div
                                class="fp-exp-error-summary"
                                data-fp-error-summary
                                role="alert"
                                aria-live="assertive"
                                tabindex="-1"
                                hidden
                                data-intro="<?php echo esc_attr__('Controlla i campi evidenziati:', 'fp-experiences'); ?>"
                            ></div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-name-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Nome e cognome', 'fp-experiences'); ?> <span class="fp-exp-required" aria-hidden="true">*</span></label>
                                <input type="text" id="fp-exp-rtb-name-<?php echo esc_attr($scope_class); ?>" name="name" class="fp-exp-input" required>
                            </div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-email-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Email', 'fp-experiences'); ?> <span class="fp-exp-required" aria-hidden="true">*</span></label>
                                <input type="email" id="fp-exp-rtb-email-<?php echo esc_attr($scope_class); ?>" name="email" class="fp-exp-input" required>
                            </div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-phone-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Numero di telefono', 'fp-experiences'); ?></label>
                                <input type="tel" id="fp-exp-rtb-phone-<?php echo esc_attr($scope_class); ?>" name="phone" class="fp-exp-input">
                            </div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-notes-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Note o richieste particolari', 'fp-experiences'); ?></label>
                                <textarea id="fp-exp-rtb-notes-<?php echo esc_attr($scope_class); ?>" name="notes" class="fp-exp-textarea" rows="4"></textarea>
                            </div>
                            <div class="fp-exp-field fp-exp-field--checkbox">
                                <label for="<?php echo esc_attr($marketing_id); ?>">
                                    <input type="checkbox" id="<?php echo esc_attr($marketing_id); ?>" name="consent_marketing" value="1">
                                    <span><?php echo esc_html__('Desidero ricevere novità e comunicazioni di marketing.', 'fp-experiences'); ?></span>
                                </label>
                            </div>
                            <div class="fp-exp-field fp-exp-field--checkbox">
                                <label for="<?php echo esc_attr($privacy_id); ?>">
                                    <input type="checkbox" id="<?php echo esc_attr($privacy_id); ?>" name="consent_privacy" value="1" required>
                                    <span><?php echo esc_html__('Accetto l\'informativa privacy e i termini di prenotazione.', 'fp-experiences'); ?></span>
                                </label>
                            </div>
                            <div class="fp-exp-rtb-form__actions">
                                <button type="submit" class="fp-exp-summary__cta" disabled><?php echo $rtb_submit_label; ?></button>
                            </div>
                            <div class="fp-exp-rtb-form__status" role="status" aria-live="polite" data-loading="<?php echo esc_attr__('Invio della richiesta…', 'fp-experiences'); ?>" data-success="<?php echo esc_attr__('Richiesta ricevuta! Ti risponderemo al più presto.', 'fp-experiences'); ?>" data-error="<?php echo esc_attr__('Impossibile inviare la richiesta. Riprova.', 'fp-experiences'); ?>"></div>
                        </form>
                    <?php else : ?>
                        <button type="button" class="fp-exp-summary__cta" disabled>
                            <?php echo esc_html__('Procedi al pagamento', 'fp-experiences'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </li>
        </ol>
    </div>
    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema">
            <?php echo wp_kses_post($schema_json); ?>
        </script>
    <?php endif; ?>
</div>
