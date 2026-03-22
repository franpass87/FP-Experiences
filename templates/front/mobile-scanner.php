<?php
/**
 * Mobile scanner template for plugin operators.
 *
 * @var array{id:int,username:string,display_name:string}|null $operator
 * @var string $notice
 * @var string $notice_type
 * @var array{id:int,experience:string,timestamp:int,guests:int,status:string,token?:string}|null $preview
 * @var string $scope_class
 * @var bool $https_ok
 * @var bool $access_granted
 * @var string $request_access_key
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<section class="fp-exp <?php echo esc_attr($scope_class); ?> fp-mobile-scanner" data-fp-shortcode="mobile-scanner">
    <div style="max-width:760px;margin:24px auto;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
        <h2 style="margin:0 0 8px;"><?php esc_html_e('Scanner check-in operatori', 'fp-experiences'); ?></h2>
        <p style="margin:0 0 12px;color:#4b5563;"><?php esc_html_e('Accesso riservato agli operatori mobile con credenziali dedicate.', 'fp-experiences'); ?></p>

        <?php if (! empty($notice)) : ?>
            <div style="margin:0 0 12px;padding:10px;border-radius:8px;background:<?php echo 'error' === $notice_type ? '#fef2f2' : ('success' === $notice_type ? '#ecfdf5' : '#eff6ff'); ?>;border:1px solid <?php echo 'error' === $notice_type ? '#fecaca' : ('success' === $notice_type ? '#bbf7d0' : '#bfdbfe'); ?>;">
                <?php echo esc_html($notice); ?>
            </div>
        <?php endif; ?>

        <?php if (! $access_granted) : ?>
            <p style="margin:0;"><?php esc_html_e('Accesso negato.', 'fp-experiences'); ?></p>
        <?php elseif (! $https_ok) : ?>
            <p style="margin:0;"><?php esc_html_e('Scanner disabilitato su connessioni non sicure. Apri la pagina in HTTPS.', 'fp-experiences'); ?></p>
        <?php elseif (! is_array($operator)) : ?>
            <form method="post" action="">
                <?php wp_nonce_field('fp_exp_mobile_login', 'fp_exp_mobile_login_nonce'); ?>
                <input type="hidden" name="fp_exp_mobile_action" value="login" />
                <input type="hidden" name="fp_exp_mobile_access_key" value="<?php echo esc_attr($request_access_key); ?>" />
                <p style="margin:0 0 10px;">
                    <label for="fp-mobile-username"><strong><?php esc_html_e('Username', 'fp-experiences'); ?></strong></label><br />
                    <input id="fp-mobile-username" type="text" name="username" required style="width:100%;max-width:420px;" />
                </p>
                <p style="margin:0 0 10px;">
                    <label for="fp-mobile-password"><strong><?php esc_html_e('Password', 'fp-experiences'); ?></strong></label><br />
                    <input id="fp-mobile-password" type="password" name="password" required style="width:100%;max-width:420px;" />
                </p>
                <button type="submit" class="fp-exp-btn fp-exp-btn--primary"><?php esc_html_e('Accedi allo scanner', 'fp-experiences'); ?></button>
            </form>
        <?php else : ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 12px;">
                <p style="margin:0;"><?php printf(esc_html__('Operatore: %s', 'fp-experiences'), esc_html((string) $operator['display_name'])); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('fp_exp_mobile_logout', 'fp_exp_mobile_logout_nonce'); ?>
                    <input type="hidden" name="fp_exp_mobile_action" value="logout" />
                    <input type="hidden" name="fp_exp_mobile_access_key" value="<?php echo esc_attr($request_access_key); ?>" />
                    <button type="submit" class="fp-exp-btn"><?php esc_html_e('Esci', 'fp-experiences'); ?></button>
                </form>
            </div>

            <div id="fp-exp-reader" style="width:100%;max-width:420px;margin:0 0 10px;border-radius:8px;overflow:hidden;background:#111827;"></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px;">
                <button type="button" id="fp-exp-start-camera" class="fp-exp-btn fp-exp-btn--secondary"><?php esc_html_e('Apri fotocamera', 'fp-experiences'); ?></button>
                <button type="button" id="fp-exp-stop-camera" class="fp-exp-btn"><?php esc_html_e('Ferma fotocamera', 'fp-experiences'); ?></button>
            </div>

            <form method="post" action="" id="fp-exp-mobile-scan-form">
                <?php wp_nonce_field('fp_exp_mobile_scan', 'fp_exp_mobile_scan_nonce'); ?>
                <input type="hidden" name="fp_exp_mobile_action" value="scan" />
                <input type="hidden" name="fp_exp_mobile_access_key" value="<?php echo esc_attr($request_access_key); ?>" />
                <p style="margin:0 0 10px;">
                    <label for="fp-exp-mobile-token"><strong><?php esc_html_e('Token QR', 'fp-experiences'); ?></strong></label><br />
                    <input id="fp-exp-mobile-token" type="text" name="checkin_token" required style="width:100%;" />
                </p>
                <button type="submit" class="fp-exp-btn fp-exp-btn--primary"><?php esc_html_e('Riconosci prenotazione', 'fp-experiences'); ?></button>
            </form>

            <?php if (is_array($preview) && ! empty($preview['id'])) : ?>
                <div style="margin-top:14px;padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">
                    <p style="margin:0 0 6px;"><strong><?php esc_html_e('Esperienza', 'fp-experiences'); ?>:</strong> <?php echo esc_html((string) $preview['experience']); ?></p>
                    <p style="margin:0 0 6px;"><strong><?php esc_html_e('Ospiti', 'fp-experiences'); ?>:</strong> <?php echo esc_html(number_format_i18n((int) $preview['guests'])); ?></p>
                    <p style="margin:0 0 10px;"><strong><?php esc_html_e('Stato', 'fp-experiences'); ?>:</strong> <?php echo esc_html((string) $preview['status']); ?></p>
                    <?php if (\FP_Exp\Booking\Reservations::STATUS_CHECKED_IN !== (string) $preview['status']) : ?>
                        <form method="post" action="">
                            <?php wp_nonce_field('fp_exp_mobile_confirm', 'fp_exp_mobile_confirm_nonce'); ?>
                            <input type="hidden" name="fp_exp_mobile_action" value="confirm" />
                            <input type="hidden" name="fp_exp_mobile_access_key" value="<?php echo esc_attr($request_access_key); ?>" />
                            <input type="hidden" name="reservation_id" value="<?php echo esc_attr((string) ((int) $preview['id'])); ?>" />
                            <input type="hidden" name="checkin_token" value="<?php echo esc_attr((string) ($preview['token'] ?? '')); ?>" />
                            <button type="submit" class="fp-exp-btn fp-exp-btn--primary"><?php esc_html_e('Conferma check-in', 'fp-experiences'); ?></button>
                        </form>
                    <?php else : ?>
                        <strong><?php esc_html_e('Check-in gia completato', 'fp-experiences'); ?></strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($access_granted && $https_ok && is_array($operator)) : ?>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    (function () {
        const startBtn = document.getElementById('fp-exp-start-camera');
        const stopBtn = document.getElementById('fp-exp-stop-camera');
        const tokenInput = document.getElementById('fp-exp-mobile-token');
        if (!startBtn || !stopBtn || !tokenInput || typeof Html5Qrcode === 'undefined') {
            return;
        }

        const scanner = new Html5Qrcode('fp-exp-reader');
        let running = false;

        startBtn.addEventListener('click', async function () {
            if (running) return;
            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    function (decodedText) {
                        tokenInput.value = decodedText || '';
                    }
                );
                running = true;
            } catch (e) {
                console.warn('Scanner start failed', e);
            }
        });

        stopBtn.addEventListener('click', async function () {
            if (!running) return;
            try {
                await scanner.stop();
            } catch (e) {
                console.warn('Scanner stop failed', e);
            }
            running = false;
        });
    })();
    </script>
<?php endif; ?>
