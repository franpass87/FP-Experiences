<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use FP_Exp\Utils\Helpers;

use function do_shortcode;
use function esc_html__;

final class WidgetMeetingPoints extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp-exp-meeting-points';
    }

    public function get_title(): string
    {
        return esc_html__('FP Meeting Points', 'fp-experiences');
    }

    public function get_icon(): string
    {
        return 'eicon-pin';
    }

    public function get_categories(): array
    {
        return ['fp-exp'];
    }

    public function get_keywords(): array
    {
        return ['experience', 'meeting point', 'location'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'fp-experiences'),
            ]
        );

        $this->add_control(
            'experience_id',
            [
                'label' => esc_html__('Experience ID', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => 0,
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        if (! Helpers::meeting_points_enabled()) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $experience_id = (int) ($settings['experience_id'] ?? 0);

        if ($experience_id <= 0) {
            return;
        }

        echo do_shortcode('[fp_exp_meeting_points id="' . $experience_id . '"]');
    }
}
