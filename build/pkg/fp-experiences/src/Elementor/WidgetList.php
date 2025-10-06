<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Theme;

use function absint;
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

        $defaults = Helpers::listing_settings();

        $this->add_control(
            'archive_mode',
            [
                'label' => esc_html__('Archive mode', 'fp-experiences'),
                'type' => Controls_Manager::CHOOSE,
                'toggle' => false,
                'options' => [
                    'advanced' => [
                        'title' => esc_html__('Advanced', 'fp-experiences'),
                        'icon' => 'eicon-filter',
                    ],
                    'simple' => [
                        'title' => esc_html__('Simple', 'fp-experiences'),
                        'icon' => 'eicon-gallery-grid',
                    ],
                ],
                'default' => 'advanced',
            ]
        );

        $this->add_control(
            'filters',
            [
                'label' => esc_html__('Filters', 'fp-experiences'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'search' => esc_html__('Search', 'fp-experiences'),
                    'theme' => esc_html__('Theme', 'fp-experiences'),
                    'language' => esc_html__('Language', 'fp-experiences'),
                    'duration' => esc_html__('Duration', 'fp-experiences'),
                    'price' => esc_html__('Price', 'fp-experiences'),
                    'family' => esc_html__('Family-friendly', 'fp-experiences'),
                    'date' => esc_html__('Date', 'fp-experiences'),
                ],
                'default' => $defaults['filters'],
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label' => esc_html__('Experiences per page', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 24,
                'default' => $defaults['per_page'],
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => esc_html__('Order by', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'menu_order' => esc_html__('Manual order', 'fp-experiences'),
                    'date' => esc_html__('Publish date', 'fp-experiences'),
                    'title' => esc_html__('Title', 'fp-experiences'),
                    'price' => esc_html__('Price (lowest first)', 'fp-experiences'),
                ],
                'default' => $defaults['orderby'],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => esc_html__('Order direction', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'ASC' => esc_html__('Ascending', 'fp-experiences'),
                    'DESC' => esc_html__('Descending', 'fp-experiences'),
                ],
                'default' => $defaults['order'],
            ]
        );

        $this->add_control(
            'view',
            [
                'label' => esc_html__('Initial view', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'grid' => esc_html__('Grid', 'fp-experiences'),
                    'list' => esc_html__('List', 'fp-experiences'),
                ],
                'default' => 'grid',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'show_map',
            [
                'label' => esc_html__('Show map link', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'cta',
            [
                'label' => esc_html__('CTA behaviour', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'page' => esc_html__('Open experience page', 'fp-experiences'),
                    'widget' => esc_html__('Scroll to booking widget', 'fp-experiences'),
                ],
                'default' => 'page',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('Layout', 'fp-experiences'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__('Columns', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 4,
                'step' => 1,
                'default' => 3,
                'tablet_default' => 2,
                'mobile_default' => 1,
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'gap',
            [
                'label' => esc_html__('Card spacing', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'compact' => esc_html__('Compact', 'fp-experiences'),
                    'cozy' => esc_html__('Cozy', 'fp-experiences'),
                    'spacious' => esc_html__('Spacious', 'fp-experiences'),
                ],
                'default' => 'cozy',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'show_price_from',
            [
                'label' => esc_html__('Show “price from” badge', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'default' => $defaults['show_price_from'] ? 'yes' : 'no',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'show_language_badge',
            [
                'label' => esc_html__('Show language badge', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'show_duration_badge',
            [
                'label' => esc_html__('Show duration badge', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'show_family_badge',
            [
                'label' => esc_html__('Show family badge', 'fp-experiences'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'archive_mode' => 'advanced',
                ],
            ]
        );

        $this->add_control(
            'simple_view',
            [
                'label' => esc_html__('Layout', 'fp-experiences'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'grid' => esc_html__('Grid', 'fp-experiences'),
                    'list' => esc_html__('List', 'fp-experiences'),
                ],
                'default' => 'grid',
                'condition' => [
                    'archive_mode' => 'simple',
                ],
            ]
        );

        $this->add_control(
            'simple_columns',
            [
                'label' => esc_html__('Columns (desktop)', 'fp-experiences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 4,
                'default' => 3,
                'condition' => [
                    'archive_mode' => 'simple',
                    'simple_view' => 'grid',
                ],
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
        $defaults = Helpers::listing_settings();
        $mode = isset($settings['archive_mode']) && 'simple' === $settings['archive_mode'] ? 'simple' : 'advanced';

        if ('simple' === $mode) {
            $view = is_string($settings['simple_view'] ?? null) && in_array($settings['simple_view'], ['grid', 'list'], true)
                ? (string) $settings['simple_view']
                : 'grid';

            $columns = absint((string) ($settings['simple_columns'] ?? 3));
            $columns = max(1, min(4, $columns));

            $order_field = (string) ($settings['orderby'] ?? 'menu_order');
            if (! in_array($order_field, ['menu_order', 'date', 'title'], true)) {
                $order_field = 'menu_order';
            }

            $direction = (string) ($settings['order'] ?? ($defaults['order'] ?? 'ASC'));
            if (! in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }

            $atts = [
                'view' => $view,
                'columns' => (string) $columns,
                'order' => $order_field,
                'order_direction' => $direction,
            ];

            $atts = array_merge($atts, $this->collect_theme_atts($settings));

            echo do_shortcode('[fp_exp_simple_archive ' . $this->build_shortcode_atts($atts) . ']');

            return;
        }

        $filters = $settings['filters'] ?? [];
        if (is_array($filters)) {
            $filters = implode(',', array_filter($filters));
        }

        $filter_string = is_string($filters) ? trim($filters) : '';
        if ('' === $filter_string) {
            $filter_string = implode(',', $defaults['filters']);
        }

        $orderby = (string) ($settings['orderby'] ?? ($defaults['orderby'] ?? 'menu_order'));
        $order_direction = (string) ($settings['order'] ?? ($defaults['order'] ?? 'ASC'));

        $atts = [
            'filters' => $filter_string,
            'per_page' => (string) ($settings['per_page'] ?? ($defaults['per_page'] ?? 9)),
            'orderby' => $orderby,
            'order' => $order_direction,
            'view' => (string) ($settings['view'] ?? 'grid'),
            'show_map' => ('yes' === ($settings['show_map'] ?? 'no')) ? '1' : '0',
            'cta' => (string) ($settings['cta'] ?? 'page'),
            'show_price_from' => ('yes' === ($settings['show_price_from'] ?? ($defaults['show_price_from'] ? 'yes' : 'no')))
                ? '1'
                : '0',
            'badge_lang' => ('yes' === ($settings['show_language_badge'] ?? 'yes')) ? '1' : '0',
            'badge_duration' => ('yes' === ($settings['show_duration_badge'] ?? 'yes')) ? '1' : '0',
            'badge_family' => ('yes' === ($settings['show_family_badge'] ?? 'yes')) ? '1' : '0',
        ];

        if (! empty($settings['columns'])) {
            $atts['columns_desktop'] = (string) absint($settings['columns']);
        }

        if (! empty($settings['columns_tablet'])) {
            $atts['columns_tablet'] = (string) absint($settings['columns_tablet']);
        }

        if (! empty($settings['columns_mobile'])) {
            $atts['columns_mobile'] = (string) absint($settings['columns_mobile']);
        }

        if (! empty($settings['gap'])) {
            $atts['gap'] = (string) $settings['gap'];
        }

        $atts = array_merge($atts, $this->collect_theme_atts($settings));

        echo do_shortcode('[fp_exp_list ' . $this->build_shortcode_atts($atts) . ']');
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
