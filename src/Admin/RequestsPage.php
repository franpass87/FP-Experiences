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
    }

    public function maybe_handle_action(): void
    {
        if (! Helpers::can_operate_fp()) {
            return;
        }

        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
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
        $status_filter = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_statuses = 'all' === $status_filter ? array_keys($statuses) : [$status_filter];
        if ('all' !== $status_filter && ! isset($statuses[$status_filter])) {
            $query_statuses = array_keys($statuses);
            $status_filter = 'all';
        }

        $requests = Reservations::get_requests([
            'statuses' => $query_statuses,
            'per_page' => 50,
        ]);

        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');

        echo '<div class="wrap fp-exp-requests">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Richieste', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Request-to-Book', 'fp-experiences') . '</h1>';
        echo '</header>';

        echo '<form method="get" class="fp-exp-requests__filters">';
        echo '<input type="hidden" name="page" value="fp_exp_requests" />';
        echo '<label for="fp-exp-requests-status">' . esc_html__('Filtra per stato', 'fp-experiences') . '</label> ';
        echo '<select id="fp-exp-requests-status" name="status">';
        $options = ['all' => esc_html__('Tutti gli stati', 'fp-experiences')] + $statuses;
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
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
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
                $mode = $this->request_to_book->resolve_mode_for_reservation($request);
                $status_label = $statuses[$status] ?? ucwords(str_replace('_', ' ', $status));
                $mode_label = 'pay_later' === $mode
                    ? esc_html__('Richiesta pagamento', 'fp-experiences')
                    : esc_html__('Conferma prenotazione', 'fp-experiences');

                $start = isset($request['start_datetime']) ? (string) $request['start_datetime'] : '';
                $start_label = $start ? wp_date($date_format . ' ' . $time_format, strtotime($start . ' UTC')) : esc_html__('Sconosciuto', 'fp-experiences');

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

                echo '<tr>';
                echo '<td>' . esc_html($experience_title ?: sprintf('#%d', absint($request['experience_id'] ?? 0))) . '</td>';
                echo '<td>' . esc_html($start_label) . '</td>';
                echo '<td>';
                echo esc_html($customer_name ?: esc_html__('Sconosciuto', 'fp-experiences'));
                if ($customer_email) {
                    echo '<br><a href="mailto:' . esc_attr($customer_email) . '">' . esc_html($customer_email) . '</a>';
                }
                if ($customer_phone) {
                    echo '<br>' . esc_html($customer_phone);
                }
                echo '</td>';
                echo '<td>' . esc_html((string) $pax) . '</td>';
                echo '<td>' . esc_html($status_label);
                echo '<br><span class="description">' . esc_html($mode_label) . '</span>';
                if ($hold_display) {
                    echo '<br><span class="description">' . esc_html($hold_display) . '</span>';
                }
                echo '</td>';
                echo '<td>';

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

                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>'; // .fp-exp-admin__layout
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
