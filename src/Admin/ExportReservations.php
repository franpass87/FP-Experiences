<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Booking\Reservations;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;

use function add_action;
use function esc_html;
use function get_option;
use function get_posts;
use function get_the_title;
use function header;
use function number_format;
use function sanitize_text_field;
use function strtotime;
use function wp_date;
use function wp_unslash;

/**
 * Handles CSV export of reservations for admin (date range, experience, status filters).
 */
final class ExportReservations implements HookableInterface
{
    public function register_hooks(): void
    {
        add_action('wp_ajax_fp_exp_export_reservations', [$this, 'handle_export']);
    }

    /**
     * Output CSV and exit. Called only for users who can manage FP.
     */
    public function handle_export(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non autorizzato.', 'fp-experiences'), '', ['response' => 403]);
        }

        check_ajax_referer('fp_exp_export_reservations', 'nonce');

        $date_from = isset($_GET['date_from']) ? sanitize_text_field((string) wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field((string) wp_unslash($_GET['date_to'])) : '';
        $experience_id = isset($_GET['experience_id']) ? absint($_GET['experience_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field((string) wp_unslash($_GET['status'])) : '';

        $statuses = [];
        if ('' !== $status) {
            $statuses = [$status];
        }

        $rows = Reservations::get_for_export([
            'date_from' => $date_from,
            'date_to' => $date_to,
            'experience_id' => $experience_id,
            'statuses' => $statuses,
            'limit' => 10000,
        ]);

        $filename = 'prenotazioni-' . (current_time('Y-m-d') ?: 'export') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if (! $out) {
            wp_die(esc_html__('Impossibile generare il file.', 'fp-experiences'), '', ['response' => 500]);
        }

        // BOM for Excel UTF-8
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');

        $headers = [
            'id',
            'data',
            'ora',
            'esperienza',
            'order_id',
            'stato',
            'pax',
            'cliente',
            'email',
            'telefono',
            'totale',
        ];
        fputcsv($out, $headers, ';');

        foreach ($rows as $r) {
            $contact = isset($r['meta']['contact']) && is_array($r['meta']['contact']) ? $r['meta']['contact'] : [];
            $name = isset($contact['name']) ? (string) $contact['name'] : '';
            $email = isset($contact['email']) ? (string) $contact['email'] : '';
            $phone = isset($contact['phone']) ? (string) $contact['phone'] : '';
            $pax = 0;
            if (isset($r['pax']) && is_array($r['pax'])) {
                $pax = (int) array_sum(array_map('absint', $r['pax']));
            }
            $start = isset($r['start_datetime']) ? (string) $r['start_datetime'] : '';
            $date_label = $start ? wp_date($date_format, strtotime($start . ' UTC')) : '';
            $time_label = $start ? wp_date($time_format, strtotime($start . ' UTC')) : '';
            $experience_title = isset($r['experience_title']) ? (string) $r['experience_title'] : '';
            $total = isset($r['total_gross']) ? (float) $r['total_gross'] : 0.0;
            $status_label = isset($r['status']) ? (string) $r['status'] : '';

            fputcsv($out, [
                (int) ($r['id'] ?? 0),
                $date_label,
                $time_label,
                $experience_title,
                (int) ($r['order_id'] ?? 0),
                $status_label,
                $pax,
                $name,
                $email,
                $phone,
                number_format((float) $total, 2, ',', ''),
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Return list of experiences for export form dropdown.
     *
     * @return array<int, array{id: int, title: string}>
     */
    public static function get_experience_options(): array
    {
        $posts = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'private'],
            'suppress_filters' => true,
        ]);
        $out = [];
        foreach ($posts as $post) {
            $out[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post->ID) ?: sprintf(__('#%d', 'fp-experiences'), $post->ID),
            ];
        }
        return $out;
    }
}
