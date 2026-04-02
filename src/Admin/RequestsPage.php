<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Admin\Traits\EmptyStateRenderer;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Booking\Reservations;
use FP_Exp\Utils\Helpers;
use WP_Error;

use function absint;
use function add_action;
use function add_query_arg;
use function add_settings_error;
use function admin_url;
use function check_admin_referer;
use function current_time;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function get_option;
use function get_settings_errors;
use function get_transient;
use function number_format_i18n;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function settings_errors;
use function set_transient;
use function sprintf;
use function selected;
use function submit_button;
use function wp_date;
use function wp_enqueue_style;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function current_user_can;

final class RequestsPage implements HookableInterface
{
    use EmptyStateRenderer;

    private RequestToBook $request_to_book;

    public function __construct(RequestToBook $request_to_book)
    {
        $this->request_to_book = $request_to_book;
    }

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'maybe_handle_action']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        // Verifica anche il hook e il page parameter per maggiore sicurezza
        $is_requests_page = $screen && (
            'fp-exp-dashboard_page_fp_exp_requests' === $screen->id ||
            (isset($_GET['page']) && $_GET['page'] === 'fp_exp_requests')
        );
        
        if (! $is_requests_page) {
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
        wp_enqueue_style('dashicons');
    }

    public function maybe_handle_action(): void
    {
        if (! Helpers::can_operate_fp()) {
            return;
        }

        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $bulk_action = isset($_POST['fp_exp_rtb_bulk_action']) ? sanitize_key((string) wp_unslash($_POST['fp_exp_rtb_bulk_action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($bulk_action && in_array($bulk_action, ['approve', 'decline'], true)) {
            check_admin_referer('fp_exp_rtb_bulk', 'fp_exp_rtb_bulk_nonce');
            $ids = isset($_POST['reservation_ids']) && is_array($_POST['reservation_ids']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                ? array_map('absint', wp_unslash($_POST['reservation_ids'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                : [];
            $ids = array_filter($ids);
            if (empty($ids)) {
                add_settings_error('fp_exp_rtb_requests', 'fp_exp_rtb_bulk', esc_html__('Seleziona almeno una richiesta.', 'fp-experiences'), 'error');
                $stored = get_settings_errors('fp_exp_rtb_requests');
                set_transient('fp_exp_rtb_requests_notices', $stored, 30);
                wp_safe_redirect(add_query_arg(['page' => 'fp_exp_requests'], admin_url('admin.php')));
                exit;
            }
            $reason = isset($_POST['reason']) ? sanitize_textarea_field((string) wp_unslash($_POST['reason'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $messages = [];
            $has_error = false;
            foreach ($ids as $reservation_id) {
                if ($bulk_action === 'approve') {
                    $result = $this->request_to_book->approve($reservation_id);
                } else {
                    $result = $this->request_to_book->decline($reservation_id, $reason);
                }
                if ($result instanceof WP_Error) {
                    $messages[] = sprintf(/* translators: 1: reservation ID, 2: error message */ esc_html__('#%1$d: %2$s', 'fp-experiences'), $reservation_id, $result->get_error_message());
                    $has_error = true;
                }
            }
            if (! empty($messages)) {
                $message = implode(' ', $messages);
                $type = $has_error ? 'error' : 'updated';
            } else {
                $message = $bulk_action === 'approve'
                    ? esc_html__('Richieste selezionate approvate.', 'fp-experiences')
                    : esc_html__('Richieste selezionate rifiutate.', 'fp-experiences');
                $type = 'updated';
            }
            add_settings_error('fp_exp_rtb_requests', 'fp_exp_rtb_bulk', $message, $type);
            $stored = get_settings_errors('fp_exp_rtb_requests');
            set_transient('fp_exp_rtb_requests_notices', $stored, 30);
            wp_safe_redirect(add_query_arg(['page' => 'fp_exp_requests'], admin_url('admin.php')));
            exit;
        }

        if (! isset($_POST['fp_exp_rtb_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['fp_exp_rtb_action']));
        $reservation_id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($reservation_id <= 0) {
            return;
        }

        $nonce_field = 'fp_exp_rtb_nonce';
        check_admin_referer('fp_exp_rtb_' . $action . '_' . $reservation_id, $nonce_field);

        $result = null;
        $message = '';
        $type = 'updated';

        if ('approve' === $action) {
            $result = $this->request_to_book->approve($reservation_id);
            if ($result instanceof WP_Error) {
                $message = $result->get_error_message();
                $type = 'error';
            } else {
                $message = esc_html__('Richiesta approvata con successo.', 'fp-experiences');
            }
        } elseif ('decline' === $action) {
            $reason = isset($_POST['reason']) ? sanitize_textarea_field((string) wp_unslash($_POST['reason'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $result = $this->request_to_book->decline($reservation_id, $reason);
            if ($result instanceof WP_Error) {
                $message = $result->get_error_message();
                $type = 'error';
            } else {
                $message = esc_html__('Richiesta rifiutata.', 'fp-experiences');
            }
        } else {
            $message = esc_html__('Azione non supportata.', 'fp-experiences');
            $type = 'error';
        }

        add_settings_error('fp_exp_rtb_requests', 'fp_exp_rtb_notice', $message, $type);
        $stored = get_settings_errors('fp_exp_rtb_requests');
        set_transient('fp_exp_rtb_requests_notices', $stored, 30);

        $redirect = add_query_arg([
            'page' => 'fp_exp_requests',
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public function render_page(): void
    {
        if (! Helpers::can_operate_fp()) {
            return;
        }

        $this->request_to_book->process_rtb_past_slot_auto_declines(150);

        $stored = get_transient('fp_exp_rtb_requests_notices');
        if ($stored) {
            foreach ($stored as $notice) {
                if (! is_array($notice) || empty($notice['code'])) {
                    continue;
                }

                add_settings_error(
                    'fp_exp_rtb_requests',
                    $notice['code'],
                    $notice['message'] ?? '',
                    $notice['type'] ?? 'updated'
                );
            }
            delete_transient('fp_exp_rtb_requests_notices');
        }

        settings_errors('fp_exp_rtb_requests');

        $statuses = Reservations::request_statuses();
        $status_filter_options = $statuses + [
            Reservations::REQUEST_FILTER_HOLD_EXPIRED => esc_html__('Hold scaduto (automatico)', 'fp-experiences'),
        ];
        $status_filter = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ('all' !== $status_filter && ! isset($status_filter_options[$status_filter])) {
            $status_filter = 'all';
        }

        if (Reservations::REQUEST_FILTER_HOLD_EXPIRED === $status_filter) {
            $requests = Reservations::get_requests([
                'rtb_hold_expired_only' => true,
                'per_page' => 50,
            ]);
        } else {
            $query_statuses = 'all' === $status_filter ? array_keys($statuses) : [$status_filter];
            $requests = Reservations::get_requests([
                'statuses' => $query_statuses,
                'include_expired_rtb_holds' => 'all' === $status_filter,
                'per_page' => 50,
            ]);
        }

        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');

        echo '<div class="wrap fp-exp-requests fp-exp-admin-page">';
        echo '<h1 class="screen-reader-text">' . esc_html__('Request-to-Book', 'fp-experiences') . '</h1>';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout">';
        echo '<div class="fpexp-page-header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Richieste', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<div class="fpexp-page-header-content">';
        echo '<h2 class="fpexp-page-header-title" aria-hidden="true">' . esc_html__('Request-to-Book', 'fp-experiences') . '</h2>';
        echo '<p class="fpexp-page-header-desc">' . esc_html__('Gestisci le richieste di prenotazione in attesa di conferma.', 'fp-experiences') . '</p>';
        echo '</div>';
        echo '<span class="fpexp-page-header-badge">v' . esc_html( defined( 'FP_EXP_VERSION' ) ? FP_EXP_VERSION : '0' ) . '</span>';
        echo '</div>';
        $this->render_operator_navigation();

        echo '<form method="get" class="fp-exp-requests__filters">';
        echo '<input type="hidden" name="page" value="fp_exp_requests" />';
        echo '<label for="fp-exp-requests-status">' . esc_html__('Filtra per stato', 'fp-experiences') . '</label> ';
        echo '<select id="fp-exp-requests-status" name="status">';
        $options = ['all' => esc_html__('Tutti gli stati', 'fp-experiences')] + $status_filter_options;
        foreach ($options as $key => $label) {
            $selected = selected($status_filter, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        submit_button(esc_html__('Filtra', 'fp-experiences'), 'secondary', '', false);
        echo '</form>';

        if (! $requests) {
            self::render_empty_state(
                'email-alt',
                esc_html__('Nessuna richiesta in attesa', 'fp-experiences'),
                esc_html__('Le richieste di prenotazione con "Request to Book" attivato appariranno qui per l\'approvazione.', 'fp-experiences'),
                admin_url('admin.php?page=fp_exp_settings&tab=rtb'),
                esc_html__('Configura Request to Book', 'fp-experiences')
            );
        } else {
            echo '<form method="post" id="fp-exp-requests-bulk-form">';
            wp_nonce_field('fp_exp_rtb_bulk', 'fp_exp_rtb_bulk_nonce');
            echo '<p class="fp-exp-requests__bulk">';
            echo '<label for="fp-exp-rtb-bulk-action">' . esc_html__('Azioni di gruppo', 'fp-experiences') . '</label> ';
            echo '<select id="fp-exp-rtb-bulk-action" name="fp_exp_rtb_bulk_action">';
            echo '<option value="">' . esc_html__('— Seleziona —', 'fp-experiences') . '</option>';
            echo '<option value="approve">' . esc_html__('Approva selezionate', 'fp-experiences') . '</option>';
            echo '<option value="decline">' . esc_html__('Rifiuta selezionate', 'fp-experiences') . '</option>';
            echo '</select> ';
            echo '<label for="fp-exp-rtb-bulk-reason">' . esc_html__('Motivo (per rifiuto)', 'fp-experiences') . '</label> ';
            echo '<input type="text" id="fp-exp-rtb-bulk-reason" name="reason" class="regular-text" placeholder="' . esc_attr__('Opzionale', 'fp-experiences') . '" /> ';
            echo '<button type="submit" class="button">' . esc_html__('Applica', 'fp-experiences') . '</button>';
            echo '</p>';
            echo '<table class="widefat fixed striped fp-exp-requests__table">';
            echo '<thead><tr>';
            echo '<th class="check-column"><span class="screen-reader-text">' . esc_html__('Seleziona', 'fp-experiences') . '</span></th>';
            echo '<th>' . esc_html__('Esperienza', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Slot', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Cliente', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Ospiti', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Stato', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Azioni', 'fp-experiences') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($requests as $request) {
                $reservation_id = absint($request['id'] ?? 0);
                $experience_title = isset($request['experience_title']) ? (string) $request['experience_title'] : '';
                $contact = isset($request['meta']['contact']) && is_array($request['meta']['contact']) ? $request['meta']['contact'] : [];
                $customer_name = isset($contact['name']) ? (string) $contact['name'] : '';
                $customer_email = isset($contact['email']) ? (string) $contact['email'] : '';
                $customer_phone = isset($contact['phone']) ? (string) $contact['phone'] : '';
                $pax = 0;
                if (isset($request['pax']) && is_array($request['pax'])) {
                    $pax = array_sum(array_map('absint', $request['pax']));
                }

                $status = Reservations::normalize_status((string) ($request['status'] ?? ''));
                $meta_row = is_array($request['meta'] ?? null) ? $request['meta'] : [];
                $rtb_decision = isset($meta_row['rtb_decision']) && is_array($meta_row['rtb_decision'])
                    ? $meta_row['rtb_decision']
                    : [];
                $is_auto_past_declined = Reservations::STATUS_DECLINED === $status
                    && ! empty($rtb_decision['auto_slot_past']);
                $mode = $this->request_to_book->resolve_mode_for_reservation($request);
                $is_hold_expired_row = Reservations::is_rtb_hold_expired_cancellation($request);
                $status_label = $is_hold_expired_row
                    ? esc_html__('Hold scaduto (automatico)', 'fp-experiences')
                    : ($statuses[$status] ?? ucwords(str_replace('_', ' ', $status)));
                $mode_label = 'pay_later' === $mode
                    ? esc_html__('Richiesta pagamento', 'fp-experiences')
                    : esc_html__('Conferma prenotazione', 'fp-experiences');

                $start = isset($request['start_datetime']) ? (string) $request['start_datetime'] : '';
                $start_label = $start ? wp_date($date_format . ' ' . $time_format, strtotime($start . ' UTC')) : esc_html__('Sconosciuto', 'fp-experiences');
                $slot_ts = ('' !== $start) ? strtotime($start . ' UTC') : false;
                $slot_in_past = false !== $slot_ts && $slot_ts < current_time('timestamp', true);

                $hold_display = '';
                if (! empty($request['hold_expires_at'])) {
                    $expiry = strtotime((string) $request['hold_expires_at'] . ' UTC');
                    if ($expiry > current_time('timestamp', true)) {
                        $hold_display = sprintf(
                            /* translators: %s: remaining minutes. */
                            esc_html__('%s min remaining hold', 'fp-experiences'),
                            number_format_i18n(max(1, (int) (($expiry - current_time('timestamp', true)) / 60)))
                        );
                    }
                }

                $context = $this->request_to_book->get_request_context($reservation_id);
                $payment_url = '';
                if (is_array($context) && ! empty($context['payment_url'])) {
                    $payment_url = (string) $context['payment_url'];
                }

                $row_class = $slot_in_past ? 'fp-exp-requests__row--past' : '';
                $checkbox_disabled = Reservations::STATUS_DECLINED === $status;
                echo '<tr' . ($row_class ? ' class="' . esc_attr($row_class) . '"' : '') . '>';
                echo '<th scope="row" class="check-column"><input type="checkbox" name="reservation_ids[]" value="' . esc_attr((string) $reservation_id) . '" aria-label="' . esc_attr(sprintf(/* translators: %d: reservation ID */ __('Seleziona richiesta #%d', 'fp-experiences'), $reservation_id)) . '"' . ($checkbox_disabled ? ' disabled' : '') . ' /></th>';
                echo '<td>' . esc_html($experience_title ?: sprintf('#%d', absint($request['experience_id'] ?? 0))) . '</td>';
                if ($slot_in_past) {
                    echo '<td><span class="fp-exp-requests__slot-past-label">' . esc_html($start_label) . '</span> ';
                    echo '<span class="fp-exp-requests__slot-past-badge">' . esc_html__('Passata', 'fp-experiences') . '</span></td>';
                } else {
                    echo '<td>' . esc_html($start_label) . '</td>';
                }
                echo '<td>';
                if ($slot_in_past) {
                    $title_attr = trim($customer_name . ($customer_email !== '' ? ' · ' . $customer_email : '') . ($customer_phone !== '' ? ' · ' . $customer_phone : ''));
                    echo '<span class="fp-exp-requests__client-inline" title="' . esc_attr($title_attr) . '">';
                    $sep_inner = ' <span class="fp-exp-requests__client-sep" aria-hidden="true">·</span> ';
                    $client_out = false;
                    if ($customer_name !== '') {
                        echo esc_html($customer_name);
                        $client_out = true;
                    }
                    if ($customer_email !== '') {
                        if ($client_out) {
                            echo $sep_inner;
                        }
                        echo '<a href="mailto:' . esc_attr($customer_email) . '">' . esc_html($customer_email) . '</a>';
                        $client_out = true;
                    }
                    if ($customer_phone !== '') {
                        if ($client_out) {
                            echo $sep_inner;
                        }
                        echo esc_html($customer_phone);
                        $client_out = true;
                    }
                    if (! $client_out) {
                        echo esc_html__('Sconosciuto', 'fp-experiences');
                    }
                    echo '</span>';
                } else {
                    echo esc_html($customer_name ?: esc_html__('Sconosciuto', 'fp-experiences'));
                    if ($customer_email) {
                        echo '<br><a href="mailto:' . esc_attr($customer_email) . '">' . esc_html($customer_email) . '</a>';
                    }
                    if ($customer_phone) {
                        echo '<br>' . esc_html($customer_phone);
                    }
                }
                echo '</td>';
                echo '<td>' . esc_html((string) $pax) . '</td>';
                echo '<td>';
                if ($slot_in_past) {
                    echo '<span class="fp-exp-requests__status-compact">';
                    echo esc_html($status_label);
                    echo ' <span class="fp-exp-requests__status-sep" aria-hidden="true">·</span> ';
                    echo '<span class="description">' . esc_html($mode_label) . '</span>';
                    echo '</span>';
                    if ($hold_display) {
                        echo '<br><span class="description fp-exp-requests__hold-left">' . esc_html($hold_display) . '</span>';
                    }
                } else {
                    echo esc_html($status_label);
                    echo '<br><span class="description">' . esc_html($mode_label) . '</span>';
                    if ($hold_display) {
                        echo '<br><span class="description">' . esc_html($hold_display) . '</span>';
                    }
                }
                echo '</td>';
                echo '<td class="fp-exp-requests__td-actions' . ($slot_in_past ? ' fp-exp-requests__td-actions--past' : '') . '">';

                $hold_hint_text = esc_html__(
                    'Hold automatico scaduto: la capacità era stata liberata. Puoi comunque approvare (se c\'è ancora posto) o rifiutare; in approvazione il sistema ricontrolla la disponibilità.',
                    'fp-experiences'
                );

                if ($slot_in_past) {
                    if (Reservations::STATUS_DECLINED === $status) {
                        if ($is_auto_past_declined) {
                            $closed_note = esc_html__(
                                'Richiesta chiusa automaticamente: lo slot era già trascorso.',
                                'fp-experiences'
                            );
                        } else {
                            $closed_note = esc_html__(
                                'Richiesta rifiutata. Lo slot risultava già trascorso.',
                                'fp-experiences'
                            );
                        }
                        echo '<p class="description fp-exp-requests__past-note fp-exp-requests__past-note--closed"><span class="dashicons dashicons-yes" aria-hidden="true"></span><span>';
                        echo esc_html($closed_note);
                        echo '</span></p>';
                    } else {
                        $past_note = $is_hold_expired_row
                            ? esc_html__(
                                'Data esperienza passata e hold scaduto: non è possibile approvare. La richiesta verrà chiusa automaticamente oppure usa Rifiuta.',
                                'fp-experiences'
                            )
                            : esc_html__(
                                'Data esperienza passata: Approva e link pagamento non sono disponibili. La richiesta verrà chiusa automaticamente oppure usa Rifiuta o il calendario.',
                                'fp-experiences'
                            );
                        echo '<p class="description fp-exp-requests__past-note"><span class="dashicons dashicons-info" aria-hidden="true"></span><span>';
                        echo esc_html($past_note);
                        echo '</span></p>';

                        echo '<form method="post" class="fp-exp-requests__action">';
                        wp_nonce_field('fp_exp_rtb_decline_' . $reservation_id, 'fp_exp_rtb_nonce');
                        echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $reservation_id) . '" />';
                        echo '<input type="hidden" name="fp_exp_rtb_action" value="decline" />';
                        echo '<input type="text" name="reason" class="regular-text" placeholder="' . esc_attr__('Motivo (opzionale)', 'fp-experiences') . '" />';
                        echo '<button type="submit" class="button">' . esc_html__('Rifiuta', 'fp-experiences') . '</button>';
                        echo '</form>';
                    }
                } else {
                    if ($is_hold_expired_row) {
                        echo '<p class="description">' . esc_html($hold_hint_text) . '</p>';
                    }

                    echo '<form method="post" class="fp-exp-requests__action">';
                    wp_nonce_field('fp_exp_rtb_approve_' . $reservation_id, 'fp_exp_rtb_nonce');
                    echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $reservation_id) . '" />';
                    echo '<input type="hidden" name="fp_exp_rtb_action" value="approve" />';
                    echo '<button type="submit" class="button button-primary">' . esc_html__('Approva', 'fp-experiences') . '</button>';
                    echo '</form>';

                    echo '<form method="post" class="fp-exp-requests__action">';
                    wp_nonce_field('fp_exp_rtb_decline_' . $reservation_id, 'fp_exp_rtb_nonce');
                    echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $reservation_id) . '" />';
                    echo '<input type="hidden" name="fp_exp_rtb_action" value="decline" />';
                    echo '<input type="text" name="reason" class="regular-text" placeholder="' . esc_attr__('Motivo opzionale', 'fp-experiences') . '" />';
                    echo '<button type="submit" class="button">' . esc_html__('Rifiuta', 'fp-experiences') . '</button>';
                    echo '</form>';

                    if ($payment_url && Reservations::STATUS_APPROVED_PENDING_PAYMENT === $status) {
                        echo '<a class="button button-secondary" href="' . esc_url($payment_url) . '" target="_blank" rel="noopener">' . esc_html__('Apri link pagamento', 'fp-experiences') . '</a>';
                    }
                }

                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</form>';
        }

        echo '</div>'; // .fp-exp-admin__layout
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_operator_navigation(): void
    {
        echo '<nav class="fp-exp-operator-nav nav-tab-wrapper" aria-label="' . esc_attr__('Navigazione operatore', 'fp-experiences') . '">';
        echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar&view=calendar')) . '">' . esc_html__('Calendario & Prenotazioni', 'fp-experiences') . '</a>';
        echo '<a class="nav-tab nav-tab-active" href="' . esc_url(admin_url('admin.php?page=fp_exp_requests')) . '">' . esc_html__('Richieste RTB', 'fp-experiences') . '</a>';
        echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_checkin')) . '">' . esc_html__('Check-in', 'fp-experiences') . '</a>';

        if (current_user_can('manage_woocommerce') && Helpers::can_manage_fp()) {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_orders')) . '">' . esc_html__('Ordini', 'fp-experiences') . '</a>';
        }

        echo '</nav>';
    }
}
