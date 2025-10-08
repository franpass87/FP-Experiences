<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Utils\Theme;
use WP_Post;
use WP_Query;

use function __;
use function _n;
use function absint;
use function get_permalink;
use function get_post_field;
use function get_post_meta;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function is_array;
use function is_string;
use function number_format_i18n;
use function sanitize_text_field;
use function sprintf;
use function strpos;
use function strtolower;
use function strtoupper;

final class SimpleArchiveShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_simple_archive';

    protected string $template = 'front/simple-archive.php';

    /**
     * @var array<string, string>
     */
    protected array $defaults = [
        'view' => 'grid',
        'columns' => '3',
        'order' => 'menu_order',
        'order_direction' => 'ASC',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        unset($content);

        $view = $this->normalize_view((string) ($attributes['view'] ?? 'grid'));
        $columns = $this->normalize_columns((string) ($attributes['columns'] ?? '3'));
        $order_by = $this->normalize_order((string) ($attributes['order'] ?? 'menu_order'));
        $order_direction = $this->normalize_direction((string) ($attributes['order_direction'] ?? 'ASC'));

        $posts = $this->query_experiences($order_by, $order_direction);

        $experiences = array_map(function (WP_Post $post): array {
            return $this->map_experience($post);
        }, $posts);

        return [
            'view' => $view,
            'columns' => $columns,
            'experiences' => $experiences,
            'scope_class' => Theme::generate_scope(),
        ];
    }

    private function normalize_view(string $value): string
    {
        $value = strtolower($value);

        return in_array($value, ['grid', 'list'], true) ? $value : 'grid';
    }

    private function normalize_columns(string $value): int
    {
        $columns = absint($value);
        if ($columns < 1) {
            return 1;
        }

        if ($columns > 4) {
            return 4;
        }

        return $columns;
    }

    private function normalize_order(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = strtolower($value);

        if (! in_array($value, ['menu_order', 'date', 'title'], true)) {
            return 'menu_order';
        }

        return $value;
    }

    private function normalize_direction(string $value): string
    {
        $value = strtoupper(sanitize_text_field($value));
        return in_array($value, ['ASC', 'DESC'], true) ? $value : 'ASC';
    }

    /**
     * @return array<int, WP_Post>
     */
    private function query_experiences(string $order_by, string $direction): array
    {
        $query = new WP_Query([
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => $order_by,
            'order' => $direction,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]);

        $posts = [];

        foreach ($query->posts as $post) {
            if ($post instanceof WP_Post) {
                $posts[] = $post;
            }
        }

        return $posts;
    }

    /**
     * @return array<string, mixed>
     */
    private function map_experience(WP_Post $post): array
    {
        $id = $post->ID;
        $title = get_the_title($post);
        $page_links = $this->resolve_page_links($id);
        $thumbnail = get_the_post_thumbnail_url($id, 'large') ?: '';
        $duration_minutes = absint((string) get_post_meta($id, '_fp_duration_minutes', true));
        $price_from = $this->calculate_price_from_meta($id);
        $price_display = null !== $price_from ? number_format_i18n($price_from, 0) : '';

        return [
            'id' => $id,
            'title' => $title,
            'details_url' => $page_links['details'],
            'booking_url' => $page_links['booking'],
            'thumbnail' => $thumbnail,
            'duration' => $this->format_duration($duration_minutes),
            'price_from' => $price_from,
            'price_from_display' => $price_display,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolve_page_links(int $experience_id): array
    {
        $page_id = absint((string) get_post_meta($experience_id, '_fp_exp_page_id', true));
        $fallback = get_permalink($experience_id) ?: '';

        $details = '';
        $booking = '';

        if ($page_id > 0) {
            $details = get_permalink($page_id) ?: '';
        }

        if ('' === $details) {
            $details = $fallback;
        }

        if ('' !== $details) {
            $booking = $this->maybe_append_widget_anchor($details, $page_id);
        }

        if ('' === $booking) {
            if ($fallback) {
                $booking = false === strpos($fallback, '#') ? $fallback . '#fp-widget' : $fallback;
            }
        }

        return [
            'details' => $details,
            'booking' => $booking,
        ];
    }

    private function maybe_append_widget_anchor(string $details_url, int $page_id): string
    {
        if (false !== strpos($details_url, '#')) {
            return $details_url;
        }

        if ($page_id <= 0) {
            return $details_url . '#fp-widget';
        }

        $content = get_post_field('post_content', $page_id);
        if (! is_string($content) || '' === $content) {
            return $details_url . '#fp-widget';
        }

        if (false === strpos($content, '[fp_exp_page')) {
            return $details_url;
        }

        return $details_url . '#fp-widget';
    }

    private function format_duration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0 && $remaining > 0) {
            return sprintf(__('%1$dh %2$dmin', 'fp-experiences'), $hours, $remaining);
        }

        if ($hours > 0) {
            return sprintf(_n('%dh', '%dh', $hours, 'fp-experiences'), $hours);
        }

        return sprintf(_n('%d minute', '%d minutes', $minutes, 'fp-experiences'), $minutes);
    }

    private function calculate_price_from_meta(int $experience_id): ?float
    {
        $tickets = get_post_meta($experience_id, '_fp_ticket_types', true);
        if (! is_array($tickets) || empty($tickets)) {
            return null;
        }

        // First, look for a ticket marked as "use_as_price_from"
        foreach ($tickets as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            if (! empty($ticket['use_as_price_from'])) {
                $price = (float) $ticket['price'];
                if ($price > 0) {
                    return $price;
                }
            }
        }

        // If no ticket is marked, fall back to the lowest price
        $min_price = null;
        foreach ($tickets as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            $price = (float) $ticket['price'];
            if ($price > 0 && (null === $min_price || $price < $min_price)) {
                $min_price = $price;
            }
        }

        return $min_price;
    }
}
