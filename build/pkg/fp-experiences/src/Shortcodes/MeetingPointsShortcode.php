<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Theme;
use WP_Error;

use function absint;
use function esc_html__;

final class MeetingPointsShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_meeting_points';

    protected string $template = 'front/meeting-points.php';

    protected array $defaults = [
        'id' => '',
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
            return new WP_Error('fp_exp_meeting_points_invalid', esc_html__('Missing experience ID.', 'fp-experiences'));
        }

        if (! Helpers::meeting_points_enabled()) {
            return [
                'experience_id' => $experience_id,
                'primary' => null,
                'alternatives' => [],
                'theme' => Theme::resolve_palette([]),
            ];
        }

        $data = Repository::get_meeting_points_for_experience($experience_id);

        if (! $data['primary']) {
            return new WP_Error('fp_exp_meeting_points_empty', esc_html__('No meeting point configured for this experience.', 'fp-experiences'));
        }

        return [
            'experience_id' => $experience_id,
            'primary' => $data['primary'],
            'alternatives' => $data['alternatives'],
            'theme' => Theme::resolve_palette([]),
        ];
    }
}
