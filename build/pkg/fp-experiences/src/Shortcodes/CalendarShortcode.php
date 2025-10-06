<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Utils\Theme;
use WP_Error;
use WP_Post;

use function absint;
use function esc_html__;
use function get_post;
use function wp_json_encode;

final class CalendarShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_calendar';

    protected string $template = 'front/calendar.php';

    protected array $defaults = [
        'id' => '',
        'months' => '2',
        'preset' => '',
        'mode' => '',
        'primary' => '',
        'secondary' => '',
        'accent' => '',
        'background' => '',
        'surface' => '',
        'text' => '',
        'muted' => '',
        'success' => '',
        'warning' => '',
        'danger' => '',
        'radius' => '',
        'shadow' => '',
        'font' => '',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $experience_id = absint($attributes['id']);
        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_calendar_invalid', esc_html__('Missing experience ID.', 'fp-experiences'));
        }

        $post = get_post($experience_id);
        if (! $post instanceof WP_Post || 'fp_experience' !== $post->post_type) {
            return new WP_Error('fp_exp_calendar_not_found', esc_html__('Experience not found.', 'fp-experiences'));
        }

        // Non pre-carichiamo slot: il front-end li recupera via REST on-the-fly
        $theme = Theme::resolve_palette([
            'preset' => (string) $attributes['preset'],
            'mode' => (string) $attributes['mode'],
            'primary' => (string) $attributes['primary'],
            'secondary' => (string) $attributes['secondary'],
            'accent' => (string) $attributes['accent'],
            'background' => (string) $attributes['background'],
            'surface' => (string) $attributes['surface'],
            'text' => (string) $attributes['text'],
            'muted' => (string) $attributes['muted'],
            'success' => (string) $attributes['success'],
            'warning' => (string) $attributes['warning'],
            'danger' => (string) $attributes['danger'],
            'radius' => (string) $attributes['radius'],
            'shadow' => (string) $attributes['shadow'],
            'font' => (string) $attributes['font'],
        ]);

        // Schema disabilitato per il calendario on-the-fly
        $schema = [];

        return [
            'theme' => $theme,
            'experience' => [
                'id' => $experience_id,
                'title' => $post->post_title,
            ],
            'months' => [],
            'schema_json' => $schema ? wp_json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'TouristTrip',
                'name' => $post->post_title,
                'offers' => $schema,
            ]) : '',
        ];
    }
}
