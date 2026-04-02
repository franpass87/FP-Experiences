<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use DateInterval;
use FP_Exp\Core\Hook\HookableInterface;
use DateTimeImmutable;
use DateTimeZone;
use FP_Exp\Admin\Traits\EmptyStateRenderer;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Checkin\OperatorAuthService;
use FP_Exp\Checkin\QrTokenService;
use FP_Exp\Utils\Helpers;
use function absint;
use function add_action;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_current_screen;
use function get_option;
use function get_transient;
use function is_array;
use function is_numeric;
use function maybe_unserialize;
use function number_format_i18n;
use function sanitize_key;
use function set_transient;
use function delete_transient;
use function wp_date;
use function wp_enqueue_style;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_die;
use function wp_timezone;
use function strtotime;
use function current_user_can;

final class CheckinPage implements HookableInterface
{
    use EmptyStateRenderer;

    private const NOTICE_KEY = 'fp_exp_checkin_notice';
    private const PREVIEW_TRANSIENT_PREFIX = 'fp_exp_checkin_preview_';

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'maybe_handle_action']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        // Verifica anche il hook e il page parameter per maggiore sicurezza
        $is_checkin_page = $screen && (
            'fp-exp-dashboard_page_fp_exp_checkin' === $screen->id ||
            (isset($_GET['page']) && $_GET['page'] === 'fp_exp_checkin')
        );
        
        if (! $is_checkin_page) {
            return;
        }

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );
    }

    public function maybe_handle_action(): void
    {
        if (! Helpers::can_operate_fp()) {
            return;
        }

        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (! isset($_POST['fp_exp_checkin_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['fp_exp_checkin_action']));

        if ('create_mobile_operator' === $action && Helpers::can_manage_fp()) {
            check_admin_referer('fp_exp_create_mobile_operator', 'fp_exp_create_mobile_operator_nonce');
            $username = isset($_POST['mobile_operator_username']) ? sanitize_text_field((string) wp_unslash($_POST['mobile_operator_username'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $display_name = isset($_POST['mobile_operator_display_name']) ? sanitize_text_field((string) wp_unslash($_POST['mobile_operator_display_name'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $password = isset($_POST['mobile_operator_password']) ? (string) wp_unslash($_POST['mobile_operator_password']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            $auth = new OperatorAuthService();
            $created = $auth->create_operator($username, $display_name, $password);
            if (! $created['ok']) {
                $message = 'username_exists' === $created['error']
                    ? esc_html__('Username operatore gia esistente.', 'fp-experiences')
                    : esc_html__('Impossibile creare operatore. Verifica i dati.', 'fp-experiences');
                $this->set_notice($message, 'error');
            } else {
                $this->set_notice(esc_html__('Operatore mobile creato con successo.', 'fp-experiences'), 'success');
            }

            wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
            exit;
        }

        if ('delete_mobile_operator' === $action && Helpers::can_manage_fp()) {
            $operator_id = isset($_POST['mobile_operator_id']) ? absint((int) $_POST['mobile_operator_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ($operator_id > 0) {
                check_admin_referer('fp_exp_delete_mobile_operator_' . $operator_id, 'fp_exp_delete_mobile_operator_nonce');
                $auth = new OperatorAuthService();
                $auth->delete_operator($operator_id);
                $this->set_notice(esc_html__('Operatore mobile eliminato.', 'fp-experiences'), 'success');
            }

            wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
            exit;
        }

        if ('reset_mobile_operator_lockout' === $action && Helpers::can_manage_fp()) {
            $operator_id = isset($_POST['mobile_operator_id']) ? absint((int) $_POST['mobile_operator_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ($operator_id > 0) {
                check_admin_referer('fp_exp_reset_mobile_operator_lockout_' . $operator_id, 'fp_exp_reset_mobile_operator_lockout_nonce');
                $auth = new OperatorAuthService();
                $reset = $auth->reset_lockout_for_operator($operator_id);
                if ($reset) {
                    $this->set_notice(esc_html__('Lockout operatore azzerato.', 'fp-experiences'), 'success');
                } else {
                    $this->set_notice(esc_html__('Impossibile azzerare il lockout.', 'fp-experiences'), 'error');
                }
            }

            wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
            exit;
        }

        if ('scan_event_qr' === $action) {
            check_admin_referer('fp_exp_checkin_scan', 'fp_exp_checkin_scan_nonce');
            $token = isset($_POST['checkin_token']) ? sanitize_text_field((string) wp_unslash($_POST['checkin_token'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $preview = $this->build_preview_from_token($token);

            if (! is_array($preview) || empty($preview['id'])) {
                $this->set_notice(esc_html__('QR non valido o scaduto. Verifica il codice e riprova.', 'fp-experiences'), 'error');
            } else {
                set_transient($this->preview_transient_key(), $preview, 120);
                $this->set_notice(esc_html__('Prenotazione evento riconosciuta. Conferma il check-in qui sotto.', 'fp-experiences'), 'success');
            }

            wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
            exit;
        }

        if ('confirm_qr_checkin' === $action) {
            $reservation_id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ($reservation_id <= 0) {
                return;
            }

            check_admin_referer('fp_exp_checkin_confirm_' . $reservation_id, 'fp_exp_checkin_confirm_nonce');
            $preview = get_transient($this->preview_transient_key());

            if (! is_array($preview) || (int) ($preview['id'] ?? 0) !== $reservation_id) {
                $this->set_notice(esc_html__('Sessione di scansione scaduta. Riesegui la scansione del QR.', 'fp-experiences'), 'error');
                wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
                exit;
            }

            $reservation = Reservations::get($reservation_id);
            $status = is_array($reservation) ? (string) ($reservation['status'] ?? '') : '';
            if (in_array($status, [Reservations::STATUS_CANCELLED, Reservations::STATUS_DECLINED], true)) {
                $this->set_notice(esc_html__('Questa prenotazione non puo essere messa in check-in dal QR.', 'fp-experiences'), 'error');
                wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
                exit;
            }

            $result = Reservations::update_status($reservation_id, Reservations::STATUS_CHECKED_IN);
            delete_transient($this->preview_transient_key());

            if (! $result) {
                $this->set_notice(esc_html__('Impossibile registrare il check-in, riprova più tardi.', 'fp-experiences'), 'error');
            } else {
                $this->set_notice(esc_html__('Check-in confermato via QR.', 'fp-experiences'), 'success');
            }

            wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
            exit;
        }

        if ('mark_checked_in' === $action) {
            $reservation_id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ($reservation_id <= 0) {
                return;
            }

            check_admin_referer('fp_exp_checkin_' . $reservation_id, 'fp_exp_checkin_nonce');
            $result = Reservations::update_status($reservation_id, Reservations::STATUS_CHECKED_IN);

            if (! $result) {
                $this->set_notice(esc_html__('Impossibile registrare il check-in, riprova più tardi.', 'fp-experiences'), 'error');
            } else {
                $this->set_notice(esc_html__('Check-in confermato.', 'fp-experiences'), 'success');
            }

            wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
            exit;
        }
    }

    public function render_page(): void
    {
        if (! Helpers::can_operate_fp()) {
            wp_die(esc_html__('Non hai i permessi per accedere alla console di check-in.', 'fp-experiences'));
        }

        $notice = get_transient(self::NOTICE_KEY);
        $notice_html = '';
        if (is_array($notice) && ! empty($notice['message'])) {
            $class = 'notice notice-' . sanitize_key($notice['type'] ?? 'success');
            $notice_html = '<div class="' . esc_attr($class) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
            delete_transient(self::NOTICE_KEY);
        }

        $rows = $this->get_upcoming_reservations();
        $scan_preview = get_transient($this->preview_transient_key());
        $scan_preview = is_array($scan_preview) ? $scan_preview : null;

        echo '<div class="wrap fp-exp-checkin fp-exp-admin-page">';
        echo '<h1 class="screen-reader-text">' . esc_html__('Console check-in', 'fp-experiences') . '</h1>';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-checkin">';
        echo '<div class="fpexp-page-header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Check-in', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<div class="fpexp-page-header-content">';
        echo '<h2 class="fpexp-page-header-title" aria-hidden="true">' . esc_html__('Console check-in', 'fp-experiences') . '</h2>';
        echo '<p class="fpexp-page-header-desc">' . esc_html__('Segna gli ospiti al loro arrivo e controlla le prenotazioni imminenti.', 'fp-experiences') . '</p>';
        echo '</div>';
        echo '<span class="fpexp-page-header-badge">v' . esc_html( defined( 'FP_EXP_VERSION' ) ? FP_EXP_VERSION : '0' ) . '</span>';
        echo '</div>';
        $this->render_operator_navigation();

        if ($notice_html) {
            echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if (Helpers::can_manage_fp()) {
            $this->render_mobile_operators_panel();
        }

        $this->render_qr_scan_panel($scan_preview);

        if (! $rows) {
            self::render_empty_state(
                'calendar-alt',
                esc_html__('Nessuna prenotazione imminente', 'fp-experiences'),
                esc_html__('Le prenotazioni dei prossimi 7 giorni appariranno qui per il check-in rapido.', 'fp-experiences'),
                admin_url('admin.php?page=fp_exp_calendar'),
                esc_html__('Vedi Calendario', 'fp-experiences')
            );
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            return;
        }

        echo '<table class="widefat striped fp-exp-checkin__table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Esperienza', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Orario', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Ospiti', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Stato', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Azione', 'fp-experiences') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $time_label = wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $row['timestamp']);
            $status_label = $this->format_status((string) $row['status']);
            echo '<tr>';
            echo '<td>' . esc_html($row['experience']) . '</td>';
            echo '<td>' . esc_html($time_label) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['guests'])) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td>';
            if (Reservations::STATUS_CHECKED_IN === $row['status']) {
                echo '<span class="fp-exp-checkin__badge">' . esc_html__('Completato', 'fp-experiences') . '</span>';
            } else {
                echo '<form method="post" action="" class="fp-exp-checkin__form">';
                wp_nonce_field('fp_exp_checkin_' . $row['id'], 'fp_exp_checkin_nonce');
                echo '<input type="hidden" name="fp_exp_checkin_action" value="mark_checked_in" />';
                echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $row['id']) . '" />';
                echo '<button type="submit" class="button button-primary">' . esc_html__('Segna check-in', 'fp-experiences') . '</button>';
                echo '</form>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<string,mixed>|null $preview
     */
    private function render_qr_scan_panel(?array $preview): void
    {
        echo '<section class="fp-exp-checkin__scanner" style="margin:16px 0 24px;padding:16px;border:1px solid #dcdcde;background:#fff;border-radius:8px;">';
        echo '<h3 style="margin:0 0 8px;">' . esc_html__('Check-in evento via QR', 'fp-experiences') . '</h3>';
        echo '<p style="margin:0 0 12px;color:#50575e;">' . esc_html__('Scansiona il QR oppure incolla il codice di backup ricevuto dal cliente.', 'fp-experiences') . '</p>';
        echo '<form method="post" action="" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        wp_nonce_field('fp_exp_checkin_scan', 'fp_exp_checkin_scan_nonce');
        echo '<input type="hidden" name="fp_exp_checkin_action" value="scan_event_qr" />';
        echo '<input type="text" name="checkin_token" class="regular-text" style="min-width:320px;" placeholder="' . esc_attr__('Incolla qui il token QR', 'fp-experiences') . '" required />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Riconosci prenotazione', 'fp-experiences') . '</button>';
        echo '</form>';

        if (! is_array($preview) || empty($preview['id'])) {
            echo '</section>';
            return;
        }

        $timestamp = (int) ($preview['timestamp'] ?? 0);
        $time_label = $timestamp > 0
            ? wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $timestamp)
            : esc_html__('Data non disponibile', 'fp-experiences');

        echo '<div style="margin-top:14px;padding:12px;border:1px solid #c3c4c7;border-radius:6px;background:#f6f7f7;">';
        echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Esperienza', 'fp-experiences') . ':</strong> ' . esc_html((string) ($preview['experience'] ?? '')) . '</p>';
        echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Orario', 'fp-experiences') . ':</strong> ' . esc_html($time_label) . '</p>';
        echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Ospiti riconosciuti', 'fp-experiences') . ':</strong> ' . esc_html(number_format_i18n((int) ($preview['guests'] ?? 0))) . '</p>';
        echo '<p style="margin:0 0 10px;"><strong>' . esc_html__('Stato', 'fp-experiences') . ':</strong> ' . esc_html($this->format_status((string) ($preview['status'] ?? ''))) . '</p>';

        if (Reservations::STATUS_CHECKED_IN === (string) ($preview['status'] ?? '')) {
            echo '<span class="fp-exp-checkin__badge">' . esc_html__('Check-in gia effettuato', 'fp-experiences') . '</span>';
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<form method="post" action="">';
        wp_nonce_field('fp_exp_checkin_confirm_' . (int) $preview['id'], 'fp_exp_checkin_confirm_nonce');
        echo '<input type="hidden" name="fp_exp_checkin_action" value="confirm_qr_checkin" />';
        echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) ((int) $preview['id'])) . '" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Conferma check-in (QR)', 'fp-experiences') . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</section>';
    }

    private function render_mobile_operators_panel(): void
    {
        $auth = new OperatorAuthService();
        $operators = $auth->get_operators();

        echo '<section class="fp-exp-checkin__mobile-operators" style="margin:0 0 16px;padding:16px;border:1px solid #dcdcde;background:#fff;border-radius:8px;">';
        echo '<h3 style="margin:0 0 8px;">' . esc_html__('Operatori mobile scanner', 'fp-experiences') . '</h3>';
        echo '<p style="margin:0 0 12px;color:#50575e;">' . esc_html__('Crea credenziali dedicate per accesso scanner da cellulare. Usa shortcode [fp_exp_mobile_scanner] in una pagina pubblica.', 'fp-experiences') . '</p>';
        echo '<div style="margin:0 0 12px;padding:12px;border:1px solid #dbeafe;background:#eff6ff;border-radius:8px;">';
        echo '<p style="margin:0 0 8px;"><strong>' . esc_html__('Procedura consigliata (chiara e sicura)', 'fp-experiences') . '</strong></p>';
        echo '<ol style="margin:0;padding-left:18px;">';
        echo '<li style="margin:0 0 6px;">' . esc_html__('Crea/aggiorna la pagina scanner con shortcode protetto, ad esempio: [fp_exp_mobile_scanner access_key="CHIAVE_FORTE"].', 'fp-experiences') . '</li>';
        echo '<li style="margin:0 0 6px;">' . esc_html__('Aggiungi qui sotto un operatore (username + password dedicata).', 'fp-experiences') . '</li>';
        echo '<li style="margin:0 0 6px;">' . esc_html__('Invia all’operatore il link completo con la chiave (?k=...), e comunica password in canale separato.', 'fp-experiences') . '</li>';
        echo '<li style="margin:0;">' . esc_html__('In caso di blocco tentativi usa “Reset lockout”.', 'fp-experiences') . '</li>';
        echo '</ol>';
        echo '</div>';

        $delivery_template = "Messaggio operatore scanner:\n\n"
            . "Link accesso scanner: [LINK_SCANNER_CON_K]\n"
            . "Username: [USERNAME]\n"
            . "Password: [PASSWORD]\n\n"
            . "Istruzioni rapide:\n"
            . "- Apri il link dal cellulare\n"
            . "- Accedi con username/password\n"
            . "- Premi \"Apri fotocamera\" per leggere il QR\n"
            . "- Se non funziona la camera, incolla il token e premi \"Riconosci prenotazione\"\n";
        echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Template pronto da inviare all’operatore', 'fp-experiences') . '</strong></p>';
        echo '<textarea id="fp-exp-mobile-template" readonly style="width:100%;min-height:165px;margin:0 0 8px;">' . esc_textarea($delivery_template) . '</textarea>';
        echo '<div style="margin:0 0 12px;">';
        echo '<button type="button" id="fp-exp-copy-template" class="button button-secondary">' . esc_html__('Copia template', 'fp-experiences') . '</button>';
        echo '<span id="fp-exp-copy-template-feedback" style="margin-left:8px;color:#065f46;font-weight:600;display:none;">' . esc_html__('Copiato!', 'fp-experiences') . '</span>';
        echo '</div>';
        echo '<script>';
        echo '(function(){';
        echo 'var btn=document.getElementById("fp-exp-copy-template");';
        echo 'var area=document.getElementById("fp-exp-mobile-template");';
        echo 'var feedback=document.getElementById("fp-exp-copy-template-feedback");';
        echo 'if(!btn||!area){return;}';
        echo 'btn.addEventListener("click",function(){';
        echo 'try{';
        echo 'if(navigator.clipboard&&navigator.clipboard.writeText){';
        echo 'navigator.clipboard.writeText(area.value).then(function(){';
        echo 'if(feedback){feedback.style.display="inline";setTimeout(function(){feedback.style.display="none";},1500);}';
        echo '});';
        echo '}else{';
        echo 'area.focus();area.select();document.execCommand("copy");';
        echo 'if(feedback){feedback.style.display="inline";setTimeout(function(){feedback.style.display="none";},1500);}';
        echo '}';
        echo '}catch(e){}';
        echo '});';
        echo '})();';
        echo '</script>';

        $security_logs_url = add_query_arg(
            [
                'page' => 'fp_exp_logs',
                'channel' => 'security',
            ],
            admin_url('admin.php')
        );
        $checkin_logs_url = add_query_arg(
            [
                'page' => 'fp_exp_logs',
                'channel' => 'checkin',
            ],
            admin_url('admin.php')
        );
        echo '<p style="margin:0 0 12px;">';
        echo '<a class="button button-secondary" style="margin-right:6px;" href="' . esc_url($security_logs_url) . '">' . esc_html__('Audit sicurezza', 'fp-experiences') . '</a>';
        echo '<a class="button button-secondary" href="' . esc_url($checkin_logs_url) . '">' . esc_html__('Audit check-in', 'fp-experiences') . '</a>';
        echo '</p>';

        echo '<form method="post" action="" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;align-items:end;">';
        wp_nonce_field('fp_exp_create_mobile_operator', 'fp_exp_create_mobile_operator_nonce');
        echo '<input type="hidden" name="fp_exp_checkin_action" value="create_mobile_operator" />';
        echo '<p style="margin:0;"><label><strong>' . esc_html__('Username', 'fp-experiences') . '</strong><br /><input type="text" name="mobile_operator_username" required style="width:100%;" /></label></p>';
        echo '<p style="margin:0;"><label><strong>' . esc_html__('Nome visualizzato', 'fp-experiences') . '</strong><br /><input type="text" name="mobile_operator_display_name" style="width:100%;" /></label></p>';
        echo '<p style="margin:0;"><label><strong>' . esc_html__('Password', 'fp-experiences') . '</strong><br /><input type="password" name="mobile_operator_password" required style="width:100%;" /></label></p>';
        echo '<p style="margin:0;"><button type="submit" class="button button-primary">' . esc_html__('Aggiungi operatore', 'fp-experiences') . '</button></p>';
        echo '</form>';

        if (! $operators) {
            echo '<p style="margin:12px 0 0;">' . esc_html__('Nessun operatore mobile configurato.', 'fp-experiences') . '</p>';
            echo '</section>';
            return;
        }

        echo '<table class="widefat striped" style="margin-top:12px;"><thead><tr>';
        echo '<th>' . esc_html__('Username', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Nome', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Lockout', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Azione', 'fp-experiences') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($operators as $operator) {
            $operator_id = (int) $operator['id'];
            $lockout_until = $auth->get_lockout_until_for_operator($operator_id);
            $is_locked = $lockout_until > time();
            echo '<tr>';
            echo '<td>' . esc_html((string) $operator['username']) . '</td>';
            echo '<td>' . esc_html((string) $operator['display_name']) . '</td>';
            echo '<td>';
            if ($is_locked) {
                $remaining_minutes = (int) max(1, ceil(($lockout_until - time()) / 60));
                echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;font-size:12px;font-weight:600;">';
                echo esc_html(sprintf(__('Attivo (%d min)', 'fp-experiences'), $remaining_minutes));
                echo '</span>';
            } else {
                echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-size:12px;font-weight:600;">';
                echo esc_html__('Non attivo', 'fp-experiences');
                echo '</span>';
            }
            echo '</td>';
            echo '<td>';
            echo '<form method="post" action="" style="display:inline-block;margin-right:6px;">';
            wp_nonce_field('fp_exp_reset_mobile_operator_lockout_' . $operator_id, 'fp_exp_reset_mobile_operator_lockout_nonce');
            echo '<input type="hidden" name="fp_exp_checkin_action" value="reset_mobile_operator_lockout" />';
            echo '<input type="hidden" name="mobile_operator_id" value="' . esc_attr((string) $operator_id) . '" />';
            echo '<button type="submit" class="button button-secondary">' . esc_html__('Reset lockout', 'fp-experiences') . '</button>';
            echo '</form>';

            echo '<form method="post" action="" style="display:inline;">';
            wp_nonce_field('fp_exp_delete_mobile_operator_' . $operator_id, 'fp_exp_delete_mobile_operator_nonce');
            echo '<input type="hidden" name="fp_exp_checkin_action" value="delete_mobile_operator" />';
            echo '<input type="hidden" name="mobile_operator_id" value="' . esc_attr((string) $operator_id) . '" />';
            echo '<button type="submit" class="button button-secondary">' . esc_html__('Elimina', 'fp-experiences') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</section>';
    }

    private function preview_transient_key(): string
    {
        return self::PREVIEW_TRANSIENT_PREFIX . get_current_user_id();
    }

    private function set_notice(string $message, string $type): void
    {
        set_transient(self::NOTICE_KEY, [
            'message' => $message,
            'type' => $type,
        ], 30);
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

    /**
     * @return array<int, array{id: int, experience: string, timestamp: int, guests: int, status: string}>
     */
    private function get_upcoming_reservations(): array
    {
        $reservations_table = Reservations::table_name();
        $slots_table = Slots::table_name();
        
        global $wpdb;

        // Try to get posts table name from DatabaseInterface if available
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        $posts_table = $wpdb->posts; // Default fallback
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(\FP_Exp\Services\Database\DatabaseInterface::class)) {
                try {
                    $database = $container->make(\FP_Exp\Services\Database\DatabaseInterface::class);
                    $posts_table = $database->getPrefix() . 'posts';
                } catch (\Throwable $e) {
                    // Fall through to $wpdb->posts
                }
            }
        }

        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $window_start = $now->sub(new DateInterval('PT6H'));
        $window_end = $now->add(new DateInterval('P2D'));

        $start_utc = $window_start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $end_utc = $window_end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $sql = $wpdb->prepare(
            "SELECT r.id, r.pax, r.status, s.start_datetime, p.post_title FROM {$reservations_table} r " .
            "INNER JOIN {$slots_table} s ON r.slot_id = s.id " .
            "INNER JOIN {$posts_table} p ON p.ID = r.experience_id " .
            "WHERE s.start_datetime BETWEEN %s AND %s AND r.status NOT IN (%s, %s) " .
            "ORDER BY s.start_datetime ASC LIMIT 30",
            $start_utc,
            $end_utc,
            Reservations::STATUS_CANCELLED,
            Reservations::STATUS_DECLINED
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        if (! $results) {
            return [];
        }

        $rows = [];
        foreach ($results as $row) {
            $guests = 0;
            $pax = maybe_unserialize($row['pax']);
            if (is_array($pax)) {
                foreach ($pax as $quantity) {
                    if (is_numeric($quantity)) {
                        $guests += (int) $quantity;
                    }
                }
            }

            $start = strtotime((string) $row['start_datetime']);
            if (! $start) {
                continue;
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'experience' => (string) $row['post_title'],
                'timestamp' => $start,
                'guests' => max(0, $guests),
                'status' => (string) $row['status'],
            ];
        }

        return $rows;
    }

    private function format_status(string $status): string
    {
        switch ($status) {
            case Reservations::STATUS_PAID:
            case Reservations::STATUS_APPROVED_CONFIRMED:
                return esc_html__('Confermato', 'fp-experiences');
            case Reservations::STATUS_APPROVED_PENDING_PAYMENT:
                return esc_html__('In attesa pagamento', 'fp-experiences');
            case Reservations::STATUS_PENDING:
            case Reservations::STATUS_PENDING_REQUEST:
                return esc_html__('In attesa', 'fp-experiences');
            case Reservations::STATUS_CHECKED_IN:
                return esc_html__('Check-in effettuato', 'fp-experiences');
            case Reservations::STATUS_CANCELLED:
                return esc_html__('Cancellato', 'fp-experiences');
            default:
                return esc_html__('Aggiornamento', 'fp-experiences');
        }
    }

    private function render_operator_navigation(): void
    {
        echo '<nav class="fp-exp-operator-nav nav-tab-wrapper" aria-label="' . esc_attr__('Navigazione operatore', 'fp-experiences') . '">';
        echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar&view=calendar')) . '">' . esc_html__('Calendario & Prenotazioni', 'fp-experiences') . '</a>';

        if (Helpers::rtb_mode() !== 'off') {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_requests')) . '">' . esc_html__('Richieste RTB', 'fp-experiences') . '</a>';
        }

        echo '<a class="nav-tab nav-tab-active" href="' . esc_url(admin_url('admin.php?page=fp_exp_checkin')) . '">' . esc_html__('Check-in', 'fp-experiences') . '</a>';

        if (current_user_can('manage_woocommerce') && Helpers::can_manage_fp()) {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_orders')) . '">' . esc_html__('Ordini', 'fp-experiences') . '</a>';
        }

        echo '</nav>';
    }
}
