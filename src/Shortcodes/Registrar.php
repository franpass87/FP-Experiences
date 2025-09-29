<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Utils\Helpers;

final class Registrar
{
    /**
     * @var array<int, BaseShortcode>
     */
    private array $shortcodes = [];

    public function __construct()
    {
        $this->shortcodes = [
            new ListShortcode(),
            new WidgetShortcode(),
            new CalendarShortcode(),
            new CheckoutShortcode(),
            new ExperienceShortcode(),
        ];

        if (Helpers::meeting_points_enabled()) {
            $this->shortcodes[] = new MeetingPointsShortcode();
        }
    }

    public function register(): void
    {
        foreach ($this->shortcodes as $shortcode) {
            $shortcode->register();
        }
    }
}
