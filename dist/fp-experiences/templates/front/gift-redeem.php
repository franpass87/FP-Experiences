<?php
/**
 * Gift redemption page template.
 *
 * @package FP_Experiences
 */

$initial_code = isset($initial_code) && is_string($initial_code) ? $initial_code : '';
$initial_code = $initial_code ? preg_replace('/[^a-z0-9\-]/', '', $initial_code) : '';
?>
<section
    class="fp-gift-redeem"
    data-fp-gift-redeem
    data-fp-initial-code="<?php echo esc_attr((string) $initial_code); ?>"
>
    <div class="fp-gift-redeem__inner">
        <h1 class="fp-gift-redeem__title"><?php esc_html_e('Utilizza il tuo voucher esperienza', 'fp-experiences'); ?></h1>
    <p class="fp-gift-redeem__intro"><?php esc_html_e('Inserisci il codice voucher per vedere le date disponibili e confermare la prenotazione.', 'fp-experiences'); ?></p>
        <div class="fp-gift__feedback" data-fp-gift-redeem-feedback aria-live="polite" hidden></div>
        <form class="fp-gift-redeem__lookup" data-fp-gift-redeem-lookup novalidate>
            <label class="fp-gift-redeem__field">
                <span class="fp-gift-redeem__label"><?php esc_html_e('Codice voucher', 'fp-experiences'); ?></span>
                <input
                    type="text"
                    name="code"
                    autocomplete="one-time-code"
                    inputmode="text"
                    required
                    data-fp-gift-code
                />
            </label>
            <button type="submit" class="fp-exp-button" data-fp-gift-redeem-lookup-submit>
                <?php esc_html_e('Verifica voucher', 'fp-experiences'); ?>
            </button>
        </form>
        <div class="fp-gift-redeem__details" data-fp-gift-redeem-details hidden>
            <article class="fp-gift-redeem__card">
                <div class="fp-gift-redeem__media">
                    <img src="" alt="" data-fp-gift-redeem-image hidden />
                </div>
                <div class="fp-gift-redeem__content">
                    <div class="fp-gift-redeem__code-row">
                        <span class="fp-gift-redeem__code-label"><?php esc_html_e('Codice', 'fp-experiences'); ?>:</span>
                        <strong class="fp-gift-redeem__code" data-fp-gift-redeem-code></strong>
                    </div>
                    <h2 class="fp-gift-redeem__experience" data-fp-gift-redeem-title></h2>
                    <p class="fp-gift-redeem__excerpt" data-fp-gift-redeem-excerpt></p>
                    <dl class="fp-gift-redeem__meta">
                        <div>
                            <dt><?php esc_html_e('Ospiti', 'fp-experiences'); ?></dt>
                            <dd data-fp-gift-redeem-quantity></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Valido fino al', 'fp-experiences'); ?></dt>
                            <dd data-fp-gift-redeem-validity></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Valore voucher', 'fp-experiences'); ?></dt>
                            <dd data-fp-gift-redeem-value></dd>
                        </div>
                    </dl>
                    <div class="fp-gift-redeem__addons-wrapper" data-fp-gift-redeem-addons-wrapper hidden>
                        <h3><?php esc_html_e('Extra inclusi', 'fp-experiences'); ?></h3>
                        <ul class="fp-gift-redeem__addons" data-fp-gift-redeem-addons></ul>
                    </div>
                </div>
            </article>
            <div class="fp-gift-redeem__actions">
                <div class="fp-gift__feedback" data-fp-gift-redeem-feedback-details aria-live="polite" hidden></div>
                <form class="fp-gift-redeem__form" data-fp-gift-redeem-form novalidate>
                    <label class="fp-gift-redeem__field">
                        <span class="fp-gift-redeem__label"><?php esc_html_e('Scegli data e ora', 'fp-experiences'); ?></span>
                        <select data-fp-gift-redeem-slot required disabled></select>
                    </label>
                    <button type="submit" class="fp-exp-button" data-fp-gift-redeem-submit disabled>
                        <?php esc_html_e('Conferma utilizzo', 'fp-experiences'); ?>
                    </button>
                </form>
                <div class="fp-gift__success" data-fp-gift-redeem-success aria-live="polite" hidden></div>
            </div>
        </div>
    </div>
</section>
