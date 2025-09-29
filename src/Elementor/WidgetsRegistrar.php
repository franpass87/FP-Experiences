<?php

declare(strict_types=1);

namespace FP_Exp\Elementor;

use Elementor\Elements_Manager;
use Elementor\Widgets_Manager;
use FP_Exp\Utils\Helpers;

use function __;
use function add_action;

final class WidgetsRegistrar
{
    private bool $hooks_registered = false;

    public function register(): void
    {
        if ($this->hooks_registered) {
            return;
        }

        $this->hooks_registered = true;

        add_action('elementor/init', function (): void {
            add_action('elementor/widgets/register', [$this, 'register_widgets']);
            add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        });
    }

    public function register_widgets(Widgets_Manager $widgets_manager): void
    {
        $widgets_manager->register(new WidgetList());
        $widgets_manager->register(new WidgetWidget());
        $widgets_manager->register(new WidgetCalendar());
        $widgets_manager->register(new WidgetCheckout());
        $widgets_manager->register(new WidgetExperiencePage());

        if (Helpers::meeting_points_enabled()) {
            $widgets_manager->register(new WidgetMeetingPoints());
        }
    }

    public function register_category(Elements_Manager $elements_manager): void
    {
        $elements_manager->add_category(
            'fp-exp',
            [
                'title' => __('FP Experiences', 'fp-experiences'),
                'icon' => 'fa fa-map',
            ]
        );
    }
}
