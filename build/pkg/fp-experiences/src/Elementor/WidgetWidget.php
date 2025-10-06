<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use FP_Exp\Utils\Theme;

use function do_shortcode;
use function esc_attr;
use function esc_html__;
use function implode;
use function is_array;

final class WidgetWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp-exp-widget';
    }

    public function get_title(): string
    {
        return esc_html__('FP Experiences Widget', 'fp-experiences');
    }

    public function get_icon(): string
    {
        return 'eicon-price-table';
    }

    public function get_categories(): array
    {
        return ['fp-exp'];
    }

    public function get_keywords(): array
    {
        return ['experience', 'booking', 'widget'];
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
            'sticky',
            [
                'label' => esc_html__('Sticky summary', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'fp-experiences'),
                'label_off' => esc_html__('No', 'fp-experiences'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_calendar',
            [
                'label' => esc_html__('Show inline calendar', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Show', 'fp-experiences'),
                'label_off' => esc_html__('Hide', 'fp-experiences'),
                'return_value' => 'yes',
                'default' => 'yes',
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
            'sticky' => ('yes' === ($settings['sticky'] ?? '')) ? '1' : '0',
            'show_calendar' => ('yes' === ($settings['show_calendar'] ?? 'yes')) ? '1' : '0',
        ];

        $atts = array_merge($atts, $this->collect_theme_atts($settings));

        echo do_shortcode('[fp_exp_widget ' . $this->build_shortcode_atts($atts) . ']');
    }

    private function add_theme_controls(): void
    {
        $this->add_control(
            'branding_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('Brand colors use the default FP Experiences palette.', 'fp-experiences'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
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
        return ['preset' => Theme::default_preset()];
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
