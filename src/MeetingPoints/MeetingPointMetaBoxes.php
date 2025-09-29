<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use FP_Exp\Utils\Helpers;
use WP_Post;

use function add_action;
use function add_meta_box;
use function current_user_can;
use function esc_attr;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function get_post_meta;
use function is_array;
use function sanitize_email;
use function sanitize_text_field;
use function update_post_meta;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_verify_nonce;
use function wp_kses_post;

final class MeetingPointMetaBoxes
{
    public function register_hooks(): void
    {
        add_action('add_meta_boxes_' . MeetingPointCPT::POST_TYPE, [$this, 'add_meta_boxes']);
        add_action('save_post_' . MeetingPointCPT::POST_TYPE, [$this, 'save_meta_box'], 10, 2);
    }

    public function add_meta_boxes(): void
    {
        if (! Helpers::meeting_points_enabled()) {
            return;
        }

        add_meta_box(
            'fp-exp-meeting-point-details',
            esc_html__('Dettagli meeting point', 'fp-experiences'),
            [$this, 'render_meta_box'],
            MeetingPointCPT::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        $address = sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_address', true));
        $lat = sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_lat', true));
        $lng = sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_lng', true));
        $notes = wp_kses_post((string) get_post_meta($post->ID, '_fp_mp_notes', true));
        $phone = sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_phone', true));
        $email = sanitize_email((string) get_post_meta($post->ID, '_fp_mp_email', true));
        $opening_hours = sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_opening_hours', true));

        wp_nonce_field('fp_exp_meeting_point_meta', 'fp_exp_meeting_point_meta_nonce');
        ?>
        <div class="fp-exp-meta-box fp-exp-meta-box--meeting-point">
            <p>
                <label for="fp-exp-mp-address"><?php esc_html_e('Indirizzo completo', 'fp-experiences'); ?></label><br />
                <input type="text" id="fp-exp-mp-address" name="fp_exp_mp[address]" class="widefat" value="<?php echo esc_attr($address); ?>" />
            </p>
            <p class="fp-exp-meta-box__inline">
                <label>
                    <?php esc_html_e('Latitudine', 'fp-experiences'); ?><br />
                    <input type="text" name="fp_exp_mp[lat]" value="<?php echo esc_attr($lat); ?>" placeholder="41.9028" />
                </label>
                <label>
                    <?php esc_html_e('Longitudine', 'fp-experiences'); ?><br />
                    <input type="text" name="fp_exp_mp[lng]" value="<?php echo esc_attr($lng); ?>" placeholder="12.4964" />
                </label>
            </p>
            <p>
                <label for="fp-exp-mp-notes"><?php esc_html_e('Note per i partecipanti', 'fp-experiences'); ?></label>
                <textarea id="fp-exp-mp-notes" name="fp_exp_mp[notes]" class="widefat" rows="4"><?php echo esc_textarea($notes); ?></textarea>
            </p>
            <p class="fp-exp-meta-box__inline">
                <label>
                    <?php esc_html_e('Telefono', 'fp-experiences'); ?><br />
                    <input type="text" name="fp_exp_mp[phone]" value="<?php echo esc_attr($phone); ?>" />
                </label>
                <label>
                    <?php esc_html_e('Email', 'fp-experiences'); ?><br />
                    <input type="email" name="fp_exp_mp[email]" value="<?php echo esc_attr($email); ?>" />
                </label>
            </p>
            <p>
                <label for="fp-exp-mp-opening-hours"><?php esc_html_e('Orari di apertura', 'fp-experiences'); ?></label>
                <input type="text" id="fp-exp-mp-opening-hours" name="fp_exp_mp[opening_hours]" class="widefat" value="<?php echo esc_attr($opening_hours); ?>" />
            </p>
        </div>
        <?php
    }

    public function save_meta_box(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! isset($_POST['fp_exp_meeting_point_meta_nonce']) || ! wp_verify_nonce((string) $_POST['fp_exp_meeting_point_meta_nonce'], 'fp_exp_meeting_point_meta')) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $data = $_POST['fp_exp_mp'] ?? [];
        if (! is_array($data)) {
            return;
        }

        $address = sanitize_text_field((string) ($data['address'] ?? ''));
        $lat = sanitize_text_field((string) ($data['lat'] ?? ''));
        $lng = sanitize_text_field((string) ($data['lng'] ?? ''));
        $notes = wp_kses_post((string) ($data['notes'] ?? ''));
        $phone = sanitize_text_field((string) ($data['phone'] ?? ''));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $opening_hours = sanitize_text_field((string) ($data['opening_hours'] ?? ''));

        update_post_meta($post_id, '_fp_mp_address', $address);
        update_post_meta($post_id, '_fp_mp_lat', $lat);
        update_post_meta($post_id, '_fp_mp_lng', $lng);
        update_post_meta($post_id, '_fp_mp_notes', $notes);
        update_post_meta($post_id, '_fp_mp_phone', $phone);
        update_post_meta($post_id, '_fp_mp_email', $email);
        update_post_meta($post_id, '_fp_mp_opening_hours', $opening_hours);
    }
}
