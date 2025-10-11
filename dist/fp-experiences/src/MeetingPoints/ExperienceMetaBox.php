<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use FP_Exp\Utils\Helpers;
use FP_Exp\MeetingPoints\Repository;
use WP_Post;

use function absint;
use function array_unique;
use function array_values;
use function add_action;
use function add_meta_box;
use function current_user_can;
use function esc_attr;
use function esc_html__;
use function esc_html_e;
use function delete_post_meta;
use function get_post_meta;
use function get_posts;
use function in_array;
use function is_array;
use function selected;
use function update_post_meta;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_verify_nonce;

final class ExperienceMetaBox
{
    public function register_hooks(): void
    {
        add_action('add_meta_boxes_fp_experience', [$this, 'add_meta_box']);
        add_action('save_post_fp_experience', [$this, 'save_meta_box'], 10, 3);
    }

    public function add_meta_box(): void
    {
        if (! Helpers::meeting_points_enabled()) {
            return;
        }

        add_meta_box(
            'fp-exp-experience-meeting-point',
            esc_html__('Meeting Point', 'fp-experiences'),
            [$this, 'render_meta_box'],
            'fp_experience',
            'side',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        $primary_id = absint((string) get_post_meta($post->ID, '_fp_meeting_point_id', true));
        $alt_ids = get_post_meta($post->ID, '_fp_meeting_point_alt', true);
        $alt_ids = is_array($alt_ids) ? array_map('absint', $alt_ids) : [];

        $meeting_points = $this->get_meeting_points();

        wp_nonce_field('fp_exp_experience_meeting_point', 'fp_exp_experience_meeting_point_nonce');
        ?>
        <p>
            <label for="fp-exp-meeting-point-primary"><?php esc_html_e('Meeting point principale', 'fp-experiences'); ?></label>
            <select id="fp-exp-meeting-point-primary" name="fp_exp_meeting_point_primary" class="widefat">
                <option value="0">&mdash; <?php esc_html_e('Nessuno', 'fp-experiences'); ?> &mdash;</option>
                <?php foreach ($meeting_points as $point) : ?>
                    <option value="<?php echo esc_attr((string) $point['id']); ?>" <?php selected($primary_id, $point['id']); ?>><?php echo esc_html($point['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php if (! empty($meeting_points)) : ?>
            <p>
                <label for="fp-exp-meeting-point-alt"><?php esc_html_e('Meeting point alternativi', 'fp-experiences'); ?></label>
                <select id="fp-exp-meeting-point-alt" name="fp_exp_meeting_point_alt[]" multiple size="5" class="widefat">
                    <?php foreach ($meeting_points as $point) : ?>
                        <option value="<?php echo esc_attr((string) $point['id']); ?>" <?php selected(in_array($point['id'], $alt_ids, true), true); ?>><?php echo esc_html($point['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
        <?php endif; ?>
        <?php
    }

    public function save_meta_box(int $post_id, WP_Post $post, bool $update): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! isset($_POST['fp_exp_experience_meeting_point_nonce']) || ! wp_verify_nonce((string) $_POST['fp_exp_experience_meeting_point_nonce'], 'fp_exp_experience_meeting_point')) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $primary_id = isset($_POST['fp_exp_meeting_point_primary']) ? absint((string) $_POST['fp_exp_meeting_point_primary']) : 0;
        $alt_raw = $_POST['fp_exp_meeting_point_alt'] ?? [];
        $alt_ids = [];

        if (is_array($alt_raw)) {
            foreach ($alt_raw as $value) {
                $alt_id = absint((string) $value);
                if ($alt_id > 0 && $alt_id !== $primary_id) {
                    $alt_ids[] = $alt_id;
                }
            }
        }

        $alt_ids = array_values(array_unique($alt_ids));

        if ($primary_id > 0) {
            update_post_meta($post_id, '_fp_meeting_point_id', $primary_id);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point_id');
        }

        if (! empty($alt_ids)) {
            update_post_meta($post_id, '_fp_meeting_point_alt', $alt_ids);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point_alt');
        }

        $summary = Repository::get_primary_summary_for_experience($post_id, $primary_id);
        if ($summary) {
            update_post_meta($post_id, '_fp_meeting_point', $summary);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point');
        }
    }

    /**
     * @return array<int, array{id:int,title:string}>
     */
    private function get_meeting_points(): array
    {
        $posts = get_posts([
            'post_type' => MeetingPointCPT::POST_TYPE,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish'],
            'fields' => 'ids',
        ]);

        $points = [];
        foreach ($posts as $post_id) {
            $point = Repository::get_meeting_point((int) $post_id);
            if (! $point) {
                continue;
            }

            $points[] = [
                'id' => $point['id'],
                'title' => $point['title'],
            ];
        }

        return $points;
    }
}
