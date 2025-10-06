<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use function absint;
use function add_action;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
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
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_die;
use function wp_timezone;
use function strtotime;

final class CheckinPage
{
    private const NOTICE_KEY = 'fp_exp_checkin_notice';

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'maybe_handle_action']);
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
        if ('mark_checked_in' !== $action) {
            return;
        }

        $reservation_id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($reservation_id <= 0) {
            return;
        }

        check_admin_referer('fp_exp_checkin_' . $reservation_id, 'fp_exp_checkin_nonce');

        $result = Reservations::update_status($reservation_id, Reservations::STATUS_CHECKED_IN);
        if (! $result) {
            set_transient(self::NOTICE_KEY, [
                'message' => esc_html__('Impossibile registrare il check-in, riprova più tardi.', 'fp-experiences'),
                'type' => 'error',
            ], 30);
        } else {
            set_transient(self::NOTICE_KEY, [
                'message' => esc_html__('Check-in confermato.', 'fp-experiences'),
                'type' => 'success',
            ], 30);
        }

        wp_safe_redirect(add_query_arg('page', 'fp_exp_checkin', admin_url('admin.php')));
        exit;
    }

    public function render_page(): void
    {
        if (! Helpers::can_operate_fp()) {
            wp_die(esc_html__('You do not have permission to access the check-in console.', 'fp-experiences'));
        }

        $notice = get_transient(self::NOTICE_KEY);
        $notice_html = '';
        if (is_array($notice) && ! empty($notice['message'])) {
            $class = 'notice notice-' . sanitize_key($notice['type'] ?? 'success');
            $notice_html = '<div class="' . esc_attr($class) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
            delete_transient(self::NOTICE_KEY);
        }

        $rows = $this->get_upcoming_reservations();

        echo '<div class="wrap fp-exp-checkin">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Check-in', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Console check-in', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Segna gli ospiti al loro arrivo e controlla le prenotazioni imminenti.', 'fp-experiences') . '</p>';
        echo '</header>';

        if ($notice_html) {
            echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if (! $rows) {
            echo '<p>' . esc_html__('Nessuna prenotazione in arrivo nelle prossime 48 ore.', 'fp-experiences') . '</p>';
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
     * @return array<int, array{id: int, experience: string, timestamp: int, guests: int, status: string}>
     */
    private function get_upcoming_reservations(): array
    {
        global $wpdb;

        $reservations_table = Reservations::table_name();
        $slots_table = Slots::table_name();
        $posts_table = $wpdb->posts;

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
}
