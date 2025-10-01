<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

use function array_filter;
use function array_map;
use function do_shortcode;
use function esc_attr;
use function esc_html__;
use function implode;
use function is_array;

final class WidgetExperiencePage extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp-exp-page';
    }

    public function get_title(): string
    {
        return esc_html__('FP Experience Page', 'fp-experiences');
    }

    public function get_icon(): string
    {
        return 'eicon-single-post';
    }

    public function get_categories(): array
    {
        return ['fp-exp'];
    }

    public function get_keywords(): array
    {
        return ['experience', 'page', 'detail'];
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
            'sections',
            [
                'label' => esc_html__('Sections', 'fp-experiences'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'default' => ['hero', 'highlights', 'inclusions', 'meeting', 'extras', 'faq', 'reviews'],
                'options' => [
                    'hero' => esc_html__('Hero', 'fp-experiences'),
                    'highlights' => esc_html__('Highlights', 'fp-experiences'),
                    'inclusions' => esc_html__('Inclusions/Exclusions', 'fp-experiences'),
                    'meeting' => esc_html__('Meeting point', 'fp-experiences'),
                    'extras' => esc_html__('Extras & Policy', 'fp-experiences'),
                    'faq' => esc_html__('FAQ', 'fp-experiences'),
                    'reviews' => esc_html__('Reviews', 'fp-experiences'),
                ],
            ]
        );

        $this->add_control(
            'sticky_widget',
            [
                'label' => esc_html__('Sticky availability button', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Enabled', 'fp-experiences'),
                'label_off' => esc_html__('Disabled', 'fp-experiences'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'container',
            [
                'label' => esc_html__('Container', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Default', 'fp-experiences'),
                    'boxed' => esc_html__('Boxed', 'fp-experiences'),
                    'full' => esc_html__('Full width', 'fp-experiences'),
                ],
                'default' => '',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'max_width',
            [
                'label' => esc_html__('Maximum width (px)', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'step' => 10,
                'default' => '',
            ]
        );

        $this->add_control(
            'gutter',
            [
                'label' => esc_html__('Side padding (px)', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'step' => 4,
                'default' => '',
            ]
        );

        $this->add_control(
            'sidebar',
            [
                'label' => esc_html__('Sidebar position', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Default', 'fp-experiences'),
                    'right' => esc_html__('Right column', 'fp-experiences'),
                    'left' => esc_html__('Left column', 'fp-experiences'),
                    'none' => esc_html__('No sidebar (single column)', 'fp-experiences'),
                ],
                'default' => '',
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
        $sections = $settings['sections'] ?? [];
        if (is_array($sections)) {
            $sections = implode(',', array_filter(array_map('strval', $sections)));
        }

        $atts = [
            'id' => (string) ($settings['experience_id'] ?? ''),
            'sections' => $sections ?: 'hero,highlights,inclusions,meeting,extras,faq,reviews',
            'sticky_widget' => ('yes' === ($settings['sticky_widget'] ?? 'yes')) ? '1' : '0',
            'container' => (string) ($settings['container'] ?? ''),
            'max_width' => (string) ($settings['max_width'] ?? ''),
            'gutter' => (string) ($settings['gutter'] ?? ''),
            'sidebar' => (string) ($settings['sidebar'] ?? ''),
        ];

        $atts = array_merge($atts, $this->collect_theme_atts($settings));

        echo do_shortcode('[fp_exp_page ' . $this->build_shortcode_atts($atts) . ']');
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
                'placeholder' => '16px',
            ]
        );

        $this->add_control(
            'shadow',
            [
                'label' => esc_html__('Shadow', 'fp-experiences'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '0 14px 40px rgba(0,0,0,0.08)',
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
