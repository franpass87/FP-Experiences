<?php
/**
 * Isolated checkout template.
 *
 * @var string $scope_class
 * @var string $nonce
 * @var array<string, string> $strings
 * @var string $schema_json
 * @var string $currency
 * @var array<int, array<string, mixed>> $cart_items
 * @var array<string, mixed> $cart_totals
 * @var string $total_formatted
 * @var bool $cart_locked
 */

if (! defined('ABSPATH')) {
    exit;
}

$container_class = 'fp-exp fp-exp-checkout ' . esc_attr($scope_class);
?>
<section class="<?php echo $container_class; ?>" data-fp-shortcode="checkout" data-cart-locked="<?php echo $cart_locked ? '1' : '0'; ?>">
    <header class="fp-exp-checkout__header">
        <h1 class="fp-exp-checkout__title"><?php echo esc_html__('Complete your booking', 'fp-experiences'); ?></h1>
        <p class="fp-exp-checkout__notice"><?php echo esc_html($strings['cart_locked']); ?></p>
    </header>
    <form
        class="fp-exp-checkout__form"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        novalidate
        data-error-required="<?php echo esc_attr__('Completa il campo %s.', 'fp-experiences'); ?>"
        data-error-email="<?php echo esc_attr__('Inserisci un indirizzo email valido.', 'fp-experiences'); ?>"
    >
        <div
            class="fp-exp-error-summary"
            data-fp-error-summary
            role="alert"
            aria-live="assertive"
            tabindex="-1"
            hidden
            data-intro="<?php echo esc_attr__('Controlla i campi evidenziati:', 'fp-experiences'); ?>"
        ></div>
        <div class="fp-exp-checkout__grid">
            <section class="fp-exp-checkout__section fp-exp-checkout__section--contact">
                <h2><?php echo esc_html($strings['contact_details']); ?></h2>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-contact-first"><?php echo esc_html__('First name', 'fp-experiences'); ?></label>
                    <input type="text" id="fp-exp-contact-first" name="contact[first_name]" autocomplete="given-name" required>
                </div>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-contact-last"><?php echo esc_html__('Last name', 'fp-experiences'); ?></label>
                    <input type="text" id="fp-exp-contact-last" name="contact[last_name]" autocomplete="family-name" required>
                </div>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-contact-email"><?php echo esc_html__('Email', 'fp-experiences'); ?></label>
                    <input type="email" id="fp-exp-contact-email" name="contact[email]" autocomplete="email" required>
                </div>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-contact-phone"><?php echo esc_html__('Phone', 'fp-experiences'); ?></label>
                    <input type="tel" id="fp-exp-contact-phone" name="contact[phone]" autocomplete="tel">
                </div>
            </section>
            <section class="fp-exp-checkout__section fp-exp-checkout__section--billing">
                <h2><?php echo esc_html($strings['billing_details']); ?></h2>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-billing-country"><?php echo esc_html__('Country', 'fp-experiences'); ?></label>
                    <input type="text" id="fp-exp-billing-country" name="billing[country]" autocomplete="country" required>
                </div>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-billing-address"><?php echo esc_html__('Address', 'fp-experiences'); ?></label>
                    <input type="text" id="fp-exp-billing-address" name="billing[address]" autocomplete="address-line1" required>
                </div>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-billing-city"><?php echo esc_html__('City', 'fp-experiences'); ?></label>
                    <input type="text" id="fp-exp-billing-city" name="billing[city]" autocomplete="address-level2" required>
                </div>
                <div class="fp-exp-form-row">
                    <label for="fp-exp-billing-postcode"><?php echo esc_html__('Postal code', 'fp-experiences'); ?></label>
                    <input type="text" id="fp-exp-billing-postcode" name="billing[postcode]" autocomplete="postal-code" required>
                </div>
                <div class="fp-exp-form-row fp-exp-form-row--checkbox">
                    <label>
                        <input type="checkbox" name="consent[marketing]" value="1">
                        <span><?php echo esc_html__('I agree to receive marketing updates about future experiences.', 'fp-experiences'); ?></span>
                    </label>
                </div>
            </section>
            <section class="fp-exp-checkout__section fp-exp-checkout__section--payment">
                <h2><?php echo esc_html($strings['payment_details']); ?></h2>
                <div class="fp-exp-payment-placeholder" role="presentation">
                    <p><?php echo esc_html__('Payment methods will load here from WooCommerce gateways.', 'fp-experiences'); ?></p>
                </div>
            </section>
            <section class="fp-exp-checkout__section fp-exp-checkout__section--summary">
                <h2><?php echo esc_html($strings['order_review']); ?></h2>
                <div class="fp-exp-order-summary" aria-live="polite">
                    <?php if (! empty($cart_items)) : ?>
                        <ul class="fp-exp-order-summary__list">
                            <?php foreach ($cart_items as $item) : ?>
                                <li class="fp-exp-order-summary__item">
                                    <div class="fp-exp-order-summary__item-heading">
                                        <span class="fp-exp-order-summary__item-title"><?php echo esc_html($item['title']); ?></span>
                                        <?php if (! empty($item['slot'])) : ?>
                                            <span class="fp-exp-order-summary__item-slot"><?php echo esc_html($item['slot']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (! empty($item['tickets'])) : ?>
                                        <ul class="fp-exp-order-summary__tickets">
                                            <?php foreach ($item['tickets'] as $ticket_line) : ?>
                                                <li><?php echo esc_html($ticket_line); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (! empty($item['addons'])) : ?>
                                        <ul class="fp-exp-order-summary__addons">
                                            <?php foreach ($item['addons'] as $addon_line) : ?>
                                                <li><?php echo esc_html($addon_line); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <span class="fp-exp-order-summary__item-total"><?php echo wp_kses_post($item['total_formatted']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="fp-exp-order-summary__empty"><?php echo esc_html__('Your experience selection will appear here.', 'fp-experiences'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="fp-exp-order-total">
                    <span class="fp-exp-order-total__label"><?php echo esc_html__('Total', 'fp-experiences'); ?></span>
                    <span class="fp-exp-order-total__value" data-currency="<?php echo esc_attr($currency); ?>"><?php echo wp_kses_post($total_formatted); ?></span>
                </div>
                <button type="submit" class="fp-exp-checkout__submit" <?php disabled($cart_locked); ?>>
                    <?php echo esc_html($strings['submit']); ?>
                </button>
                <?php if ($cart_locked) : ?>
                    <p class="fp-exp-checkout__locked-notice">
                        <?php esc_html_e('Payment is currently being processed. If you have completed checkout, you can close this page.', 'fp-experiences'); ?>
                    </p>
                    <button type="button" class="fp-exp-checkout__unlock">
                        <?php echo esc_html__('Annulla e riprova', 'fp-experiences'); ?>
                    </button>
                <?php endif; ?>
            </section>
        </div>
    </form>
    <?php if (! empty($schema_json)) : ?>
        <script type="application/ld+json" class="fp-exp-schema">
            <?php echo wp_kses_post($schema_json); ?>
        </script>
    <?php endif; ?>
</section>
