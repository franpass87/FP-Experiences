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


$dialog_id = $scope_class . '-dialog';
$marketing_id = $scope_class . '-consent-marketing';
$privacy_id = $scope_class . '-consent-privacy';

$display_context = isset($display_context) ? (string) $display_context : '';

$dataset = [
    'experienceId' => $experience['id'],
    'experienceTitle' => $experience['title'],
    'experienceUrl' => $experience['permalink'] ?? '',
    'slots' => $slots,
    'tickets' => $tickets,
    'addons' => $addons,
    'calendar' => $calendar,
    'behavior' => $behavior,
    'rtb' => $rtb,
    'nonce' => $rtb_nonce,
    'displayContext' => $display_context,
];

$container_class = 'fp-exp fp-exp-widget ' . esc_attr($scope_class);
$rtb_enabled = ! empty($rtb['enabled']);
$rtb_mode = isset($rtb['mode']) ? (string) $rtb['mode'] : 'off';
$rtb_forced = ! empty($rtb['forced']);
$rtb_submit_label = 'pay_later' === $rtb_mode
    ? esc_html__('Send approval with payment link', 'fp-experiences')
    : esc_html__('Send booking request', 'fp-experiences');
?>
<div
    class="<?php echo $container_class; ?>"
    data-fp-shortcode="widget"
    data-config="<?php echo esc_attr(wp_json_encode($dataset)); ?>"
    data-sticky="<?php echo esc_attr($behavior['sticky'] ? '1' : '0'); ?>"
    data-display-context="<?php echo esc_attr($display_context); ?>"
>
    <div class="fp-exp-widget__header">
        <h2 class="fp-exp-widget__title"><?php echo esc_html($experience['title']); ?></h2>
        <?php if (! empty($experience['highlights'])) : ?>
            <ul class="fp-exp-widget__highlights">
                <?php foreach ($experience['highlights'] as $highlight) : ?>
                    <li><?php echo esc_html($highlight); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="fp-exp-widget__meta">
            <?php if (! empty($experience['duration'])) : ?>
                <span class="fp-exp-widget__meta-item">
                    <strong><?php echo esc_html__('Duration', 'fp-experiences'); ?></strong>
                    <span><?php echo esc_html(sprintf(esc_html__('%d minutes', 'fp-experiences'), (int) $experience['duration'])); ?></span>
                </span>
            <?php endif; ?>
            <?php if (! empty($experience['languages'])) : ?>
                <span class="fp-exp-widget__meta-item">
                    <strong><?php echo esc_html__('Languages', 'fp-experiences'); ?></strong>
                    <span><?php echo esc_html(implode(', ', $experience['languages'])); ?></span>
                </span>
            <?php endif; ?>
            <?php if (! empty($experience['meeting_point'])) : ?>
                <span class="fp-exp-widget__meta-item">
                    <strong><?php echo esc_html__('Meeting point', 'fp-experiences'); ?></strong>
                    <span><?php echo esc_html($experience['meeting_point']); ?></span>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($behavior['sticky']) : ?>
        <button
            type="button"
            class="fp-exp-widget__open"
            data-fp-widget-open="1"
            aria-expanded="false"
            aria-controls="<?php echo esc_attr($dialog_id); ?>"
        >
            <span class="fp-exp-widget__open-label"><?php echo esc_html__('Open booking panel', 'fp-experiences'); ?></span>
        </button>
    <?php endif; ?>
    <div
        class="fp-exp-widget__body"
        data-sticky="<?php echo esc_attr($behavior['sticky'] ? '1' : '0'); ?>"
        id="<?php echo esc_attr($dialog_id); ?>"
        <?php if ($behavior['sticky']) : ?>role="dialog" aria-modal="true"<?php else : ?>role="group"<?php endif; ?>
    >
        <?php if ($behavior['sticky']) : ?>
            <button
                type="button"
                class="fp-exp-widget__close"
                data-fp-widget-close="1"
                aria-label="<?php echo esc_attr__('Close booking panel', 'fp-experiences'); ?>"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        <?php endif; ?>
        <ol class="fp-exp-widget__steps">
            <li class="fp-exp-step fp-exp-step--dates" data-fp-step="dates">
                <header>
                    <span class="fp-exp-step__number">1</span>
                    <h3 class="fp-exp-step__title"><?php echo esc_html__('Choose a date', 'fp-experiences'); ?></h3>
                </header>
                <div class="fp-exp-step__content">
                    <div class="fp-exp-calendar" data-show-calendar="<?php echo esc_attr($behavior['show_calendar'] ? '1' : '0'); ?>">
                        <?php foreach ($calendar as $month_key => $month_data) : ?>
                            <section class="fp-exp-calendar__month" data-month="<?php echo esc_attr($month_key); ?>">
                                <header class="fp-exp-calendar__month-header"><?php echo esc_html($month_data['month_label']); ?></header>
                                <div class="fp-exp-calendar__grid">
                                    <?php foreach ($month_data['days'] as $day => $day_slots) : ?>
                                        <button type="button" class="fp-exp-calendar__day" data-date="<?php echo esc_attr($day); ?>" data-available="<?php echo esc_attr(count($day_slots) > 0 ? '1' : '0'); ?>">
                                            <span class="fp-exp-calendar__day-label"><?php echo esc_html($day); ?></span>
                                            <span class="fp-exp-calendar__day-count"><?php echo esc_html(sprintf(esc_html__('%d slots', 'fp-experiences'), count($day_slots))); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <div class="fp-exp-slots" aria-live="polite" data-empty-label="<?php echo esc_attr__('Select a date to view time slots', 'fp-experiences'); ?>">
                        <p class="fp-exp-slots__placeholder"><?php echo esc_html__('Select a date to view time slots', 'fp-experiences'); ?></p>
                    </div>
                </div>
            </li>
            <li class="fp-exp-step fp-exp-step--party" data-fp-step="party">
                <header>
                    <span class="fp-exp-step__number">2</span>
                    <h3 class="fp-exp-step__title"><?php echo esc_html__('Select tickets', 'fp-experiences'); ?></h3>
                </header>
                <div class="fp-exp-step__content">
                    <table class="fp-exp-party-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('Ticket type', 'fp-experiences'); ?></th>
                                <th scope="col"><?php echo esc_html__('Price', 'fp-experiences'); ?></th>
                                <th scope="col"><?php echo esc_html__('Quantity', 'fp-experiences'); ?></th>
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
                                        <span class="fp-exp-ticket__price" data-price="<?php echo esc_attr((string) $ticket['price']); ?>">€<?php echo esc_html(number_format_i18n((float) $ticket['price'], 2)); ?></span>
                                    </td>
                                    <td>
                                        <div class="fp-exp-quantity">
                                            <button type="button" class="fp-exp-quantity__control" data-action="decrease" aria-label="<?php echo esc_attr(sprintf(esc_html__('Decrease %s', 'fp-experiences'), $ticket['label'])); ?>">−</button>
                                            <input type="number" class="fp-exp-quantity__input" min="0" max="<?php echo esc_attr((string) ($ticket['cap'] ?? '')); ?>" value="0" aria-label="<?php echo esc_attr(sprintf(esc_html__('%s quantity', 'fp-experiences'), $ticket['label'])); ?>">
                                            <button type="button" class="fp-exp-quantity__control" data-action="increase" aria-label="<?php echo esc_attr(sprintf(esc_html__('Increase %s', 'fp-experiences'), $ticket['label'])); ?>">+</button>
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
                        <h3 class="fp-exp-step__title"><?php echo esc_html__('Add-ons', 'fp-experiences'); ?></h3>
                    </header>
                    <div class="fp-exp-step__content">
                        <ul class="fp-exp-addons">
                            <?php foreach ($addons as $addon) : ?>
                                <li class="fp-exp-addon" data-addon="<?php echo esc_attr($addon['slug']); ?>">
                                    <label>
                                        <input type="checkbox" value="1">
                                        <span class="fp-exp-addon__details">
                                            <span class="fp-exp-addon__label"><?php echo esc_html($addon['label']); ?></span>
                                            <?php if (! empty($addon['description'])) : ?>
                                                <small class="fp-exp-addon__description"><?php echo esc_html($addon['description']); ?></small>
                                            <?php endif; ?>
                                        </span>
                                        <span class="fp-exp-addon__price" data-price="<?php echo esc_attr((string) $addon['price']); ?>">€<?php echo esc_html(number_format_i18n((float) $addon['price'], 2)); ?></span>
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
                    <h3 class="fp-exp-step__title"><?php echo esc_html__('Summary', 'fp-experiences'); ?></h3>
                </header>
                <div class="fp-exp-step__content">
                    <div class="fp-exp-summary" data-empty-label="<?php echo esc_attr__('Select tickets to see the summary', 'fp-experiences'); ?>">
                        <p class="fp-exp-summary__empty"><?php echo esc_html__('Select tickets to see the summary', 'fp-experiences'); ?></p>
                    </div>
                    <?php if ($rtb_enabled) : ?>
                        <form
                            class="fp-exp-rtb-form"
                            data-fp-rtb-form="1"
                            data-nonce="<?php echo esc_attr($rtb_nonce); ?>"
                            data-error-name="<?php echo esc_attr__('Enter your name.', 'fp-experiences'); ?>"
                            data-error-email="<?php echo esc_attr__('Enter your email address.', 'fp-experiences'); ?>"
                            data-error-email-format="<?php echo esc_attr__('Enter a valid email address.', 'fp-experiences'); ?>"
                            data-error-privacy="<?php echo esc_attr__('Accept the privacy policy to continue.', 'fp-experiences'); ?>"
                        >
                            <input type="hidden" name="experience_id" value="<?php echo esc_attr((string) $experience['id']); ?>">
                            <input type="hidden" name="slot_id" value="">
                            <input type="hidden" name="tickets" value="">
                            <input type="hidden" name="addons" value="">
                            <input type="hidden" name="mode" value="<?php echo esc_attr($rtb_mode); ?>">
                            <input type="hidden" name="forced" value="<?php echo esc_attr($rtb_forced ? '1' : '0'); ?>">
                            <div
                                class="fp-exp-error-summary"
                                data-fp-error-summary
                                role="alert"
                                aria-live="assertive"
                                tabindex="-1"
                                hidden
                                data-intro="<?php echo esc_attr__('Please review the highlighted fields:', 'fp-experiences'); ?>"
                            ></div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-name-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Name and surname', 'fp-experiences'); ?> <span class="fp-exp-required" aria-hidden="true">*</span></label>
                                <input type="text" id="fp-exp-rtb-name-<?php echo esc_attr($scope_class); ?>" name="name" class="fp-exp-input" required>
                            </div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-email-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Email', 'fp-experiences'); ?> <span class="fp-exp-required" aria-hidden="true">*</span></label>
                                <input type="email" id="fp-exp-rtb-email-<?php echo esc_attr($scope_class); ?>" name="email" class="fp-exp-input" required>
                            </div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-phone-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Phone number', 'fp-experiences'); ?></label>
                                <input type="tel" id="fp-exp-rtb-phone-<?php echo esc_attr($scope_class); ?>" name="phone" class="fp-exp-input">
                            </div>
                            <div class="fp-exp-field">
                                <label class="fp-exp-label" for="fp-exp-rtb-notes-<?php echo esc_attr($scope_class); ?>"><?php echo esc_html__('Notes or special requests', 'fp-experiences'); ?></label>
                                <textarea id="fp-exp-rtb-notes-<?php echo esc_attr($scope_class); ?>" name="notes" class="fp-exp-textarea" rows="4"></textarea>
                            </div>
                            <div class="fp-exp-field fp-exp-field--checkbox">
                                <label for="<?php echo esc_attr($marketing_id); ?>">
                                    <input type="checkbox" id="<?php echo esc_attr($marketing_id); ?>" name="consent_marketing" value="1">
                                    <span><?php echo esc_html__('I would like to receive news and marketing updates.', 'fp-experiences'); ?></span>
                                </label>
                            </div>
                            <div class="fp-exp-field fp-exp-field--checkbox">
                                <label for="<?php echo esc_attr($privacy_id); ?>">
                                    <input type="checkbox" id="<?php echo esc_attr($privacy_id); ?>" name="consent_privacy" value="1" required>
                                    <span><?php echo esc_html__('I agree to the privacy policy and terms of booking.', 'fp-experiences'); ?></span>
                                </label>
                            </div>
                            <div class="fp-exp-rtb-form__actions">
                                <button type="submit" class="fp-exp-summary__cta" disabled><?php echo $rtb_submit_label; ?></button>
                            </div>
                            <div class="fp-exp-rtb-form__status" role="status" aria-live="polite" data-loading="<?php echo esc_attr__('Sending your request…', 'fp-experiences'); ?>" data-success="<?php echo esc_attr__('Request received! We will reply soon.', 'fp-experiences'); ?>" data-error="<?php echo esc_attr__('Unable to submit your request. Please try again.', 'fp-experiences'); ?>"></div>
                        </form>
                    <?php else : ?>
                        <button type="button" class="fp-exp-summary__cta" disabled>
                            <?php echo esc_html__('Proceed to checkout', 'fp-experiences'); ?>
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
