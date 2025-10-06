<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use WP_Post;

use function absint;
use function get_post;
use function get_post_meta;
use function is_array;
use function array_map;
use function sanitize_email;
use function sanitize_text_field;
use function wp_kses_post;
use function is_finite;

final class Repository
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $cache = [];

    public static function get_meeting_point(int $id): ?array
    {
        $id = absint($id);
        if ($id <= 0) {
            return null;
        }

        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        $post = get_post($id);
        if (! $post instanceof WP_Post || MeetingPointCPT::POST_TYPE !== $post->post_type) {
            return null;
        }

        $data = [
            'id' => $post->ID,
            'title' => sanitize_text_field($post->post_title),
            'address' => sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_address', true)),
            'lat' => self::normalize_float_meta(get_post_meta($post->ID, '_fp_mp_lat', true)),
            'lng' => self::normalize_float_meta(get_post_meta($post->ID, '_fp_mp_lng', true)),
            'notes' => wp_kses_post((string) get_post_meta($post->ID, '_fp_mp_notes', true)),
            'phone' => sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_phone', true)),
            'email' => sanitize_email((string) get_post_meta($post->ID, '_fp_mp_email', true)),
            'opening_hours' => sanitize_text_field((string) get_post_meta($post->ID, '_fp_mp_opening_hours', true)),
        ];

        self::$cache[$id] = $data;

        return $data;
    }

    /**
     * @return array{primary: ?array<string, mixed>, alternatives: array<int, array<string, mixed>>}
     */
    public static function get_meeting_points_for_experience(int $experience_id): array
    {
        $primary_id = absint((string) get_post_meta($experience_id, '_fp_meeting_point_id', true));
        $alt_ids = get_post_meta($experience_id, '_fp_meeting_point_alt', true);
        $alt_ids = is_array($alt_ids) ? array_map('absint', $alt_ids) : [];

        $primary = $primary_id > 0 ? self::get_meeting_point($primary_id) : null;

        $alternatives = [];
        if (! empty($alt_ids)) {
            foreach ($alt_ids as $alt_id) {
                if ($alt_id <= 0 || ($primary && $alt_id === $primary['id'])) {
                    continue;
                }

                $point = self::get_meeting_point($alt_id);
                if ($point) {
                    $alternatives[] = $point;
                }
            }
        }

        if (! $primary) {
            $legacy = sanitize_text_field((string) get_post_meta($experience_id, '_fp_meeting_point', true));
            if ($legacy) {
                $primary = [
                    'id' => 0,
                    'title' => $legacy,
                    'address' => $legacy,
                    'lat' => null,
                    'lng' => null,
                    'notes' => '',
                    'phone' => '',
                    'email' => '',
                    'opening_hours' => '',
                ];
            }
        }

        return [
            'primary' => $primary,
            'alternatives' => $alternatives,
        ];
    }

    public static function clear_cache(?int $id = null): void
    {
        if (null === $id) {
            self::$cache = [];

            return;
        }

        $id = absint($id);
        if ($id > 0) {
            unset(self::$cache[$id]);
        }
    }

    public static function get_primary_summary_for_experience(int $experience_id, int $primary_id = 0): string
    {
        $primary = $primary_id > 0 ? self::get_meeting_point($primary_id) : null;

        if (! $primary) {
            $data = self::get_meeting_points_for_experience($experience_id);
            $primary = $data['primary'];
        }

        if (! $primary) {
            return '';
        }

        $title = trim((string) $primary['title']);
        $address = trim((string) $primary['address']);

        if ($title && $address && $title !== $address) {
            return $title . ' - ' . $address;
        }

        return $address ?: $title;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_float_meta($value): ?float
    {
        if ('' === $value || null === $value) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) ? $value : null;
    }
}
