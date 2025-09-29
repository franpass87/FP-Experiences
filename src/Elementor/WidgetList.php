<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

use function array_filter;
use function do_shortcode;
use function esc_attr;
use function esc_html__;
use function implode;
use function is_array;

final class WidgetList extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp-exp-list';
    }

    public function get_title(): string
    {
        return esc_html__('FP Experiences List', 'fp-experiences');
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    public function get_categories(): array
    {
        return ['fp-exp'];
    }

    public function get_keywords(): array
    {
        return ['experience', 'booking', 'list'];
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
            'filters',
            [
                'label' => esc_html__('Filters', 'fp-experiences'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'theme' => esc_html__('Theme', 'fp-experiences'),
                    'duration' => esc_html__('Duration', 'fp-experiences'),
                    'price' => esc_html__('Price', 'fp-experiences'),
                    'language' => esc_html__('Language', 'fp-experiences'),
                ],
                'default' => ['theme', 'duration', 'price'],
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label' => esc_html__('Experiences per page', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 24,
                'default' => 9,
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => esc_html__('Order by', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'menu_order' => esc_html__('Menu order', 'fp-experiences'),
                    'date' => esc_html__('Publish date', 'fp-experiences'),
                    'title' => esc_html__('Title', 'fp-experiences'),
                    'modified' => esc_html__('Last modified', 'fp-experiences'),
                ],
                'default' => 'menu_order',
            ]
        );

        $this->add_control(
            'order_direction',
            [
                'label' => esc_html__('Order direction', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'ASC' => esc_html__('Ascending', 'fp-experiences'),
                    'DESC' => esc_html__('Descending', 'fp-experiences'),
                ],
                'default' => 'ASC',
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
        $filters = $settings['filters'] ?? [];
        if (is_array($filters)) {
            $filters = implode(',', array_filter($filters));
        }

        $atts = [
            'filters' => $filters ?: 'theme,duration,price',
            'per_page' => (string) ($settings['per_page'] ?? '9'),
            'order' => (string) ($settings['order'] ?? 'menu_order'),
            'order_direction' => (string) ($settings['order_direction'] ?? 'ASC'),
        ];

        $atts = array_merge($atts, $this->collect_theme_atts($settings));

        echo do_shortcode('[fp_exp_list ' . $this->build_shortcode_atts($atts) . ']');
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
