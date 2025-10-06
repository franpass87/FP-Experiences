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

final class WidgetCheckout extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp-exp-checkout';
    }

    public function get_title(): string
    {
        return esc_html__('FP Experiences Checkout', 'fp-experiences');
    }

    public function get_icon(): string
    {
        return 'eicon-cart';
    }

    public function get_categories(): array
    {
        return ['fp-exp'];
    }

    public function get_keywords(): array
    {
        return ['experience', 'checkout', 'booking'];
    }

    protected function register_controls(): void
    {
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
        $atts = $this->collect_theme_atts($settings);

        echo do_shortcode('[fp_exp_checkout ' . $this->build_shortcode_atts($atts) . ']');
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
