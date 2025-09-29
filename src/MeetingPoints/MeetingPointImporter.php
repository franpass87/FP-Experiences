<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use FP_Exp\Utils\Helpers;
use WP_Error;

use function add_action;
use function add_query_arg;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function get_transient;
use function delete_transient;
use function is_array;
use function sanitize_email;
use function sanitize_text_field;
use function set_transient;
use function sprintf;
use function submit_button;
use function update_post_meta;
use function wp_insert_post;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;
use function wp_nonce_field;
use function wp_die;
use function wp_kses_post;
use function preg_split;
use function str_getcsv;
use function strtolower;

use const MINUTE_IN_SECONDS;

final class MeetingPointImporter
{
    private const TRANSIENT_KEY = 'fp_exp_meeting_point_import_notice';

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_fp_exp_import_meeting_points', [$this, 'handle_import']);
    }

    public function register_menu(): void
    {
        if (! Helpers::meeting_points_enabled()) {
            return;
        }

        add_submenu_page(
            'fp-exp-settings',
            esc_html__('Import Meeting Points', 'fp-experiences'),
            esc_html__('Import Meeting Points', 'fp-experiences'),
            'manage_options',
            'fp-exp-meeting-points-import',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fp-experiences'));
        }

        $notice = get_transient(self::TRANSIENT_KEY);
        if (is_array($notice) && ! empty($notice['message'])) {
            $class = 'notice';
            if (! empty($notice['type'])) {
                $class .= ' notice-' . sanitize_text_field((string) $notice['type']);
            }
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
            delete_transient(self::TRANSIENT_KEY);
        }

        $action = admin_url('admin-post.php');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import meeting points', 'fp-experiences'); ?></h1>
            <p><?php esc_html_e('Incolla qui un CSV con le colonne: title,address,lat,lng,notes,phone,email,opening_hours.', 'fp-experiences'); ?></p>
            <form method="post" action="<?php echo esc_attr($action); ?>">
                <?php wp_nonce_field('fp_exp_meeting_point_import', 'fp_exp_meeting_point_import_nonce'); ?>
                <input type="hidden" name="action" value="fp_exp_import_meeting_points" />
                <p>
                    <textarea name="fp_exp_meeting_point_csv" rows="12" class="large-text" placeholder="Titolo,Indirizzo,Lat,Lng,Note,Telefono,Email,Orari"></textarea>
                </p>
                <?php submit_button(esc_html__('Import CSV', 'fp-experiences')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_import(): void
    {
        if (! Helpers::meeting_points_enabled()) {
            wp_die(esc_html__('Meeting points are disabled.', 'fp-experiences'));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to import meeting points.', 'fp-experiences'));
        }

        if (! isset($_POST['fp_exp_meeting_point_import_nonce']) || ! wp_verify_nonce((string) $_POST['fp_exp_meeting_point_import_nonce'], 'fp_exp_meeting_point_import')) {
            wp_die(esc_html__('Security check failed.', 'fp-experiences'));
        }

        $raw = isset($_POST['fp_exp_meeting_point_csv']) ? (string) wp_unslash($_POST['fp_exp_meeting_point_csv']) : '';
        $rows = preg_split('/\r?\n/', trim($raw));

        $imported = 0;
        $skipped = 0;

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $row = trim($row);
                if ('' === $row) {
                    continue;
                }

                $columns = str_getcsv($row);
                if (count($columns) < 2) {
                    $skipped++;
                    continue;
                }

                $title = sanitize_text_field($columns[0] ?? '');
                $address = sanitize_text_field($columns[1] ?? '');
                $lat = sanitize_text_field($columns[2] ?? '');
                $lng = sanitize_text_field($columns[3] ?? '');
                $notes = $columns[4] ?? '';
                $phone = sanitize_text_field($columns[5] ?? '');
                $email = sanitize_email($columns[6] ?? '');
                $opening_hours = sanitize_text_field($columns[7] ?? '');

                if ('' === $title) {
                    $skipped++;
                    continue;
                }

                if (0 === $imported && strtolower($title) === 'title') {
                    $skipped++;
                    continue;
                }

                $post_id = wp_insert_post([
                    'post_type' => MeetingPointCPT::POST_TYPE,
                    'post_status' => 'publish',
                    'post_title' => $title,
                ]);

                if (! $post_id || $post_id instanceof WP_Error) {
                    $skipped++;
                    continue;
                }

                update_post_meta($post_id, '_fp_mp_address', $address);
                update_post_meta($post_id, '_fp_mp_lat', $lat);
                update_post_meta($post_id, '_fp_mp_lng', $lng);
                update_post_meta($post_id, '_fp_mp_notes', wp_kses_post($notes));
                update_post_meta($post_id, '_fp_mp_phone', $phone);
                update_post_meta($post_id, '_fp_mp_email', $email);
                update_post_meta($post_id, '_fp_mp_opening_hours', $opening_hours);

                $imported++;
            }
        }

        set_transient(
            self::TRANSIENT_KEY,
            [
                'type' => 'success',
                'message' => sprintf(
                    esc_html__('%1$d meeting points imported, %2$d skipped.', 'fp-experiences'),
                    $imported,
                    $skipped
                ),
            ],
            MINUTE_IN_SECONDS
        );

        wp_safe_redirect(add_query_arg(['page' => 'fp-exp-meeting-points-import'], admin_url('admin.php')));
        exit;
    }
}
