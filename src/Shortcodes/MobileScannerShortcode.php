<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Checkin\OperatorAuthService;
use FP_Exp\Checkin\QrTokenService;
use FP_Exp\Utils\Logger;

/**
 * Frontend mobile scanner for plugin-managed operators.
 */
final class MobileScannerShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_mobile_scanner';

    protected string $template = 'front/mobile-scanner.php';

    protected array $defaults = [
        'access_key' => '',
    ];

    /**
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $auth = new OperatorAuthService();
        $notice = '';
        $notice_type = 'info';
        $preview = null;
        $operator = $auth->get_authenticated_operator();
        $access_key_required = sanitize_text_field((string) ($attributes['access_key'] ?? ''));
        $request_access_key = isset($_POST['fp_exp_mobile_access_key']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_text_field((string) wp_unslash($_POST['fp_exp_mobile_access_key'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            : (isset($_GET['k']) ? sanitize_text_field((string) wp_unslash($_GET['k'])) : ''); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $access_granted = '' === $access_key_required || hash_equals($access_key_required, $request_access_key);
        $https_ok = is_ssl();

        if (! $access_granted) {
            Logger::log('security', 'Mobile scanner access denied: invalid access key', []);
            return [
                'theme' => [],
                'operator' => null,
                'notice' => esc_html__('Accesso non autorizzato alla console scanner.', 'fp-experiences'),
                'notice_type' => 'error',
                'preview' => null,
                'https_ok' => $https_ok,
                'access_granted' => false,
                'request_access_key' => '',
            ];
        }

        if (! $https_ok) {
            $notice = esc_html__('Connessione non sicura. Usa HTTPS per abilitare scanner e check-in.', 'fp-experiences');
            $notice_type = 'error';
        }

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = isset($_POST['fp_exp_mobile_action']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                ? sanitize_key((string) wp_unslash($_POST['fp_exp_mobile_action'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                : '';

            if ('login' === $action) {
                if (! $https_ok) {
                    return [
                        'theme' => [],
                        'operator' => null,
                        'notice' => $notice,
                        'notice_type' => $notice_type,
                        'preview' => null,
                        'https_ok' => false,
                        'access_granted' => true,
                        'request_access_key' => $request_access_key,
                    ];
                }
                check_admin_referer('fp_exp_mobile_login', 'fp_exp_mobile_login_nonce');
                $username = isset($_POST['username']) ? sanitize_text_field((string) wp_unslash($_POST['username'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $login_result = $auth->authenticate($username, $password);
                if (! $login_result['ok']) {
                    if ('locked' === $login_result['error']) {
                        $lockout_until = (int) ($login_result['lockout_until'] ?? 0);
                        $wait = max(0, $lockout_until - time());
                        $minutes = (int) ceil($wait / 60);
                        $notice = sprintf(
                            esc_html__('Troppi tentativi. Riprova tra circa %d minuti.', 'fp-experiences'),
                            $minutes
                        );
                    } else {
                        $remaining = (int) ($login_result['remaining_attempts'] ?? 0);
                        $notice = sprintf(
                            esc_html__('Credenziali non valide. Tentativi rimasti: %d.', 'fp-experiences'),
                            $remaining
                        );
                    }
                    $notice_type = 'error';
                } else {
                    $operator = is_array($login_result['operator']) ? $login_result['operator'] : null;
                    if (is_array($operator)) {
                        $auth->create_session($operator);
                        $operator = $auth->get_authenticated_operator();
                        $notice = esc_html__('Accesso eseguito. Scanner pronto.', 'fp-experiences');
                        $notice_type = 'success';
                    } else {
                        $notice = esc_html__('Errore interno durante il login operatore.', 'fp-experiences');
                        $notice_type = 'error';
                    }
                }
            } elseif ('logout' === $action) {
                check_admin_referer('fp_exp_mobile_logout', 'fp_exp_mobile_logout_nonce');
                $auth->destroy_session();
                $operator = null;
                $notice = esc_html__('Disconnesso.', 'fp-experiences');
                $notice_type = 'success';
            } elseif ('scan' === $action) {
                if (! $https_ok) {
                    return [
                        'theme' => [],
                        'operator' => $operator,
                        'notice' => $notice,
                        'notice_type' => $notice_type,
                        'preview' => null,
                        'https_ok' => false,
                        'access_granted' => true,
                        'request_access_key' => $request_access_key,
                    ];
                }
                check_admin_referer('fp_exp_mobile_scan', 'fp_exp_mobile_scan_nonce');
                $token = isset($_POST['checkin_token']) ? sanitize_text_field((string) wp_unslash($_POST['checkin_token'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $preview = $this->build_preview_from_token($token);
                if (! is_array($preview) || empty($preview['id'])) {
                    $notice = esc_html__('QR non valido o scaduto.', 'fp-experiences');
                    $notice_type = 'error';
                    $preview = null;
                } else {
                    $notice = esc_html__('Prenotazione riconosciuta. Conferma il check-in.', 'fp-experiences');
                    $notice_type = 'success';
                    $preview['token'] = $token;
                }
            } elseif ('confirm' === $action) {
                if (! $https_ok) {
                    return [
                        'theme' => [],
                        'operator' => $operator,
                        'notice' => $notice,
                        'notice_type' => $notice_type,
                        'preview' => null,
                        'https_ok' => false,
                        'access_granted' => true,
                        'request_access_key' => $request_access_key,
                    ];
                }
                check_admin_referer('fp_exp_mobile_confirm', 'fp_exp_mobile_confirm_nonce');
                $token = isset($_POST['checkin_token']) ? sanitize_text_field((string) wp_unslash($_POST['checkin_token'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $reservation_id = isset($_POST['reservation_id']) ? absint((int) $_POST['reservation_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $preview = $this->build_preview_from_token($token);
                if (! is_array($preview) || (int) ($preview['id'] ?? 0) !== $reservation_id) {
                    $notice = esc_html__('Sessione non valida. Esegui una nuova scansione.', 'fp-experiences');
                    $notice_type = 'error';
                    $preview = null;
                } else {
                    $updated = Reservations::update_status($reservation_id, Reservations::STATUS_CHECKED_IN);
                    if ($updated) {
                        $notice = esc_html__('Check-in confermato.', 'fp-experiences');
                        $notice_type = 'success';
                        $preview['status'] = Reservations::STATUS_CHECKED_IN;
                        Logger::log('checkin', 'Mobile scanner check-in confirmed', [
                            'reservation_id' => $reservation_id,
                            'operator_id' => is_array($operator) ? (int) ($operator['id'] ?? 0) : 0,
                            'operator_username' => is_array($operator) ? (string) ($operator['username'] ?? '') : '',
                        ]);
                    } else {
                        $notice = esc_html__('Impossibile confermare il check-in.', 'fp-experiences');
                        $notice_type = 'error';
                        Logger::log('checkin', 'Mobile scanner check-in failed', [
                            'reservation_id' => $reservation_id,
                            'operator_id' => is_array($operator) ? (int) ($operator['id'] ?? 0) : 0,
                        ]);
                    }
                }
            }
        }

        return [
            'theme' => [],
            'operator' => $operator,
            'notice' => $notice,
            'notice_type' => $notice_type,
            'preview' => $preview,
            'https_ok' => $https_ok,
            'access_granted' => true,
            'request_access_key' => $request_access_key,
        ];
    }

    protected function should_disable_cache(): bool
    {
        return true;
    }

    /**
     * @return array{id:int,experience:string,timestamp:int,guests:int,status:string}|null
     */
    private function build_preview_from_token(string $token): ?array
    {
        $verification = QrTokenService::verify($token);
        if (empty($verification['valid'])) {
            return null;
        }

        $payload = is_array($verification['payload'] ?? null) ? $verification['payload'] : [];
        $reservation_id = absint((int) ($payload['reservation_id'] ?? 0));
        $order_id = absint((int) ($payload['order_id'] ?? 0));
        if ($reservation_id <= 0 || $order_id <= 0) {
            return null;
        }

        $reservation = Reservations::get($reservation_id);
        if (! is_array($reservation)) {
            return null;
        }

        if (absint((int) ($reservation['order_id'] ?? 0)) !== $order_id) {
            return null;
        }

        $experience_id = absint((int) ($reservation['experience_id'] ?? 0));
        if ($experience_id <= 0 || '1' !== (string) get_post_meta($experience_id, '_fp_is_event', true)) {
            return null;
        }

        $slot = Slots::get_slot(absint((int) ($reservation['slot_id'] ?? 0)));
        if (! is_array($slot)) {
            return null;
        }

        $guests = 0;
        $pax = is_array($reservation['pax'] ?? null) ? $reservation['pax'] : [];
        foreach ($pax as $quantity) {
            if (is_numeric($quantity)) {
                $guests += (int) $quantity;
            }
        }

        $start = strtotime((string) ($slot['start_datetime'] ?? ''));
        if (! $start) {
            $start = 0;
        }

        return [
            'id' => $reservation_id,
            'experience' => get_the_title($experience_id),
            'timestamp' => $start,
            'guests' => max(0, $guests),
            'status' => (string) ($reservation['status'] ?? ''),
        ];
    }
}
