<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

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
        ];
    }

    public function register(): void
    {
        foreach ($this->shortcodes as $shortcode) {
            $shortcode->register();
        }
    }
}
