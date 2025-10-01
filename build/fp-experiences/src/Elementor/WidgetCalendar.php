<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

use function do_shortcode;
use function esc_attr;
use function esc_html__;
use function implode;

final class WidgetCalendar extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp-exp-calendar';
    }

    public function get_title(): string
    {
        return esc_html__('FP Experiences Calendar', 'fp-experiences');
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    public function get_categories(): array
    {
        return ['fp-exp'];
    }

    public function get_keywords(): array
    {
        return ['experience', 'calendar', 'booking'];
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

        $this->add_control(
            'months',
            [
                'label' => esc_html__('Months to display', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 12,
                'default' => 2,
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_theme',
            [
                'label' => esc_html__('Theme', 'fp-experiences'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_theme_controls();

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $atts = [
            'id' => (string) ($settings['experience_id'] ?? ''),
            'months' => (string) ($settings['months'] ?? '2'),
        ];

        $atts = array_merge($atts, $this->collect_theme_atts($settings));

        echo do_shortcode('[fp_exp_calendar ' . $this->build_shortcode_atts($atts) . ']');
    }

    private function add_theme_controls(): void
    {
        $this->add_control(
            'preset',
            [
                'label' => esc_html__('Preset', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Default', 'fp-experiences'),
                    'light' => esc_html__('Light', 'fp-experiences'),
                    'dark' => esc_html__('Dark', 'fp-experiences'),
                    'natural' => esc_html__('Natural (green)', 'fp-experiences'),
                    'wine' => esc_html__('Wine / Burgundy', 'fp-experiences'),
                ],
            ]
        );

        $this->add_control(
            'mode',
            [
                'label' => esc_html__('Color mode', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Inherit', 'fp-experiences'),
                    'light' => esc_html__('Light', 'fp-experiences'),
                    'dark' => esc_html__('Dark (.fp-theme-dark)', 'fp-experiences'),
                    'auto' => esc_html__('Automatic (prefers-color-scheme)', 'fp-experiences'),
                ],
            ]
        );

        $color_labels = [
            'primary' => esc_html__('Primary', 'fp-experiences'),
            'secondary' => esc_html__('Secondary', 'fp-experiences'),
            'accent' => esc_html__('Accent', 'fp-experiences'),
            'background' => esc_html__('Background', 'fp-experiences'),
            'surface' => esc_html__('Surface', 'fp-experiences'),
            'text' => esc_html__('Text', 'fp-experiences'),
            'muted' => esc_html__('Muted', 'fp-experiences'),
            'success' => esc_html__('Success', 'fp-experiences'),
            'warning' => esc_html__('Warning', 'fp-experiences'),
            'danger' => esc_html__('Danger', 'fp-experiences'),
        ];

        foreach ($color_labels as $key => $label) {
            $this->add_control(
                $key,
                [
                    'label' => $label,
                    'type' => Controls_Manager::COLOR,
                ]
            );
        }

        $this->add_control(
            'radius',
            [
                'label' => esc_html__('Border radius', 'fp-experiences'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '12px',
            ]
        );

        $this->add_control(
            'shadow',
            [
                'label' => esc_html__('Shadow', 'fp-experiences'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '0 10px 30px rgba(0,0,0,0.08)',
            ]
        );

        $this->add_control(
            'font',
            [
                'label' => esc_html__('Font family', 'fp-experiences'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '"Red Hat Display", sans-serif',
            ]
        );
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, string>
     */
    private function collect_theme_atts(array $settings): array
    {
        $keys = ['preset', 'mode', 'primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'success', 'warning', 'danger', 'radius', 'shadow', 'font'];
        $atts = [];

        foreach ($keys as $key) {
            if (! empty($settings[$key])) {
                $atts[$key] = (string) $settings[$key];
            }
        }

        return $atts;
    }

    /**
     * @param array<string, string> $atts
     */
    private function build_shortcode_atts(array $atts): string
    {
        $parts = [];
        foreach ($atts as $key => $value) {
            if ('' === $value) {
                continue;
            }
            $parts[] = $key . '="' . esc_attr($value) . '"';
        }

        return implode(' ', $parts);
    }
}
