<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use function __;
use function array_merge;
use function esc_attr;
use function get_option;
use function is_array;
use function in_array;
use function sanitize_key;
use function sprintf;
use function uniqid;

final class Theme
{
    private const DEFAULT_PRESET = 'default';
    /**
     * Generate a unique CSS scope class for shortcode instances.
     */
    public static function generate_scope(): string
    {
        return 'fp-exp-scope-' . sanitize_key(uniqid('', false));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            self::DEFAULT_PRESET => [
                'label' => __('Default', 'fp-experiences'),
                'values' => array_merge(self::base_palette(), [
                    'mode' => 'light',
                ]),
            ],
        ];
    }

    public static function default_preset(): string
    {
        return self::DEFAULT_PRESET;
    }

    /**
     * @return array<string, string>
     */
    public static function default_palette(): array
    {
        $presets = self::presets();
        $preset_key = self::default_preset();

        return $presets[$preset_key]['values'];
    }

    /**
     * @return array<int, string>
     */
    public static function palette_tokens(): array
    {
        return array_keys(self::base_palette());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, string>
     */
    public static function resolve_palette(array $overrides = []): array
    {
        $presets = self::presets();
        $branding = get_option('fp_exp_branding', []);
        $branding = is_array($branding) ? $branding : [];

        $preset_key = isset($branding['preset']) && isset($presets[$branding['preset']])
            ? (string) $branding['preset']
            : self::DEFAULT_PRESET;

        if (isset($overrides['preset']) && isset($presets[$overrides['preset']])) {
            $preset_key = (string) $overrides['preset'];
        }

        $palette = $presets[$preset_key]['values'];
        $palette['mode'] = $palette['mode'] ?? 'light';
        $palette['preset'] = $preset_key;

        $tokens = self::palette_tokens();

        foreach ($tokens as $token) {
            if (isset($branding[$token]) && '' !== $branding[$token]) {
                $palette[$token] = (string) $branding[$token];
            }
        }

        if (isset($branding['mode']) && in_array($branding['mode'], ['light', 'dark', 'auto'], true)) {
            $palette['mode'] = (string) $branding['mode'];
        }

        foreach ($tokens as $token) {
            if (isset($overrides[$token]) && '' !== $overrides[$token]) {
                $palette[$token] = (string) $overrides[$token];
            }
        }

        if (isset($overrides['mode']) && in_array($overrides['mode'], ['light', 'dark', 'auto'], true)) {
            $palette['mode'] = (string) $overrides['mode'];
        }

        return $palette;
    }

    /**
     * @param array<string, string> $palette
     */
    public static function build_scope_css(array $palette, string $scope_class): string
    {
        $mode = $palette['mode'] ?? 'light';
        $vars = self::variable_map($palette);
        $css = '';

        if ('dark' === $mode) {
            $css .= '.' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}';

            return $css;
        }

        $css .= '.' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['base']) . '}';

        if ('auto' === $mode) {
            $css .= '@media (prefers-color-scheme: dark){.' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}}';
            $css .= '.fp-theme-dark .' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}';

            return $css;
        }

        $css .= '.fp-theme-dark .' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}';

        return $css;
    }

    /**
     * @param array<string, string> $palette
     *
     * @return array<int, string>
     */
    public static function contrast_report(array $palette): array
    {
        $messages = [];
        $primary_contrast = self::contrast_ratio($palette['primary'] ?? '#0B6EFD', $palette['background'] ?? '#F7F8FA');
        if ($primary_contrast < 4.5) {
            $messages[] = sprintf(
                /* translators: %s: contrast ratio. */
                __('Primary color contrast against the background is %.2f:1. Consider adjusting colors for AA compliance.', 'fp-experiences'),
                $primary_contrast
            );
        }

        $text_contrast = self::contrast_ratio($palette['text'] ?? '#0F172A', $palette['background'] ?? '#F7F8FA');
        if ($text_contrast < 4.5) {
            $messages[] = sprintf(
                /* translators: %s: contrast ratio. */
                __('Body text contrast is %.2f:1. Increase contrast for readability.', 'fp-experiences'),
                $text_contrast
            );
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    private static function base_palette(): array
    {
        return [
            'primary' => '#0B6EFD',
            'secondary' => '#1857C4',
            'accent' => '#00A37A',
            'section_icon_background' => '#0B6EFD',
            'section_icon_color' => '#FFFFFF',
            'hero_card_gradient_start' => '#8B1E3F',
            'hero_card_gradient_end' => '#0F172A',
            'hero_card_gradient_opacity_start' => '0.08',
            'hero_card_gradient_opacity_end' => '0.02',
            'background' => '#F7F8FA',
            'surface' => '#FFFFFF',
            'text' => '#0F172A',
            'muted' => '#64748B',
            'success' => '#1B998B',
            'warning' => '#F4A261',
            'danger' => '#C44536',
            'radius' => '16px',
            'shadow' => '0 8px 24px rgba(15,23,42,0.08)',
            'font' => '',
            'gap' => '24px',
        ];
    }

    /**
     * @param array<string, string> $palette
     *
     * @return array{base: array<string, string>, dark: array<string, string>}
     */
    private static function variable_map(array $palette): array
    {
        $base = self::variables_from_palette($palette);
        $dark_palette = self::derive_dark_palette($palette);
        $dark = self::variables_from_palette($dark_palette);

        return [
            'base' => $base,
            'dark' => $dark,
        ];
    }

    /**
     * @param array<string, string> $palette
     *
     * @return array<string, string>
     */
    private static function variables_from_palette(array $palette): array
    {
        $defaults = self::base_palette();
        $primary = $palette['primary'] ?? $defaults['primary'];
        $secondary = $palette['secondary'] ?? $defaults['secondary'];
        $accent = $palette['accent'] ?? $defaults['accent'];
        $section_icon_background = $palette['section_icon_background'] ?? $defaults['section_icon_background'];
        $section_icon_color = $palette['section_icon_color'] ?? $defaults['section_icon_color'];
        $hero_card_gradient_start = $palette['hero_card_gradient_start'] ?? $defaults['hero_card_gradient_start'];
        $hero_card_gradient_end = $palette['hero_card_gradient_end'] ?? $defaults['hero_card_gradient_end'];
        $hero_card_gradient_opacity_start = $palette['hero_card_gradient_opacity_start'] ?? $defaults['hero_card_gradient_opacity_start'];
        $hero_card_gradient_opacity_end = $palette['hero_card_gradient_opacity_end'] ?? $defaults['hero_card_gradient_opacity_end'];
        $background = $palette['background'] ?? $defaults['background'];
        $surface = $palette['surface'] ?? $defaults['surface'];
        $text = $palette['text'] ?? $defaults['text'];
        $muted = $palette['muted'] ?? $defaults['muted'];
        $success = $palette['success'] ?? $defaults['success'];
        $warning = $palette['warning'] ?? $defaults['warning'];
        $danger = $palette['danger'] ?? $defaults['danger'];
        $radius = $palette['radius'] ?? $defaults['radius'];
        $shadow = $palette['shadow'] ?? $defaults['shadow'];
        $font = $palette['font'] ? $palette['font'] : ($defaults['font'] ?: 'inherit');
        $gap = $palette['gap'] ?? $defaults['gap'];
        $focus_ring = sprintf('color-mix(in srgb, %s 70%%, #ffffff)', $primary);
        $focus_ring_soft = sprintf('color-mix(in srgb, %s 32%%, #ffffff)', $primary);
        
        // Generate hero card gradient
        $gradient_start_rgba = self::hex_to_rgba($hero_card_gradient_start, (float) $hero_card_gradient_opacity_start);
        $gradient_end_rgba = self::hex_to_rgba($hero_card_gradient_end, (float) $hero_card_gradient_opacity_end);
        $hero_card_gradient = sprintf('linear-gradient(135deg, %s, %s)', $gradient_start_rgba, $gradient_end_rgba);

        return [
            '--fp-exp-color-primary' => $primary,
            '--fp-exp-color-secondary' => $secondary,
            '--fp-exp-color-accent' => $accent,
            '--fp-exp-color-section-icon-background' => $section_icon_background,
            '--fp-exp-color-section-icon' => $section_icon_color,
            '--fp-exp-hero-card-gradient-start' => $hero_card_gradient_start,
            '--fp-exp-hero-card-gradient-end' => $hero_card_gradient_end,
            '--fp-exp-hero-card-gradient-opacity-start' => $hero_card_gradient_opacity_start,
            '--fp-exp-hero-card-gradient-opacity-end' => $hero_card_gradient_opacity_end,
            '--fp-exp-hero-card-gradient' => $hero_card_gradient,
            '--fp-exp-color-background' => $background,
            '--fp-exp-color-surface' => $surface,
            '--fp-exp-color-text' => $text,
            '--fp-exp-color-muted' => $muted,
            '--fp-exp-color-success' => $success,
            '--fp-exp-color-warning' => $warning,
            '--fp-exp-color-danger' => $danger,
            '--fp-exp-radius-base' => $radius,
            '--fp-exp-shadow-base' => $shadow,
            '--fp-exp-font-family' => $font,
            '--fp-exp-gap' => $gap,
            '--fp-color-primary' => $primary,
            '--fp-color-secondary' => $secondary,
            '--fp-color-accent' => $accent,
            '--fp-color-section-icon-background' => $section_icon_background,
            '--fp-color-section-icon' => $section_icon_color,
            '--fp-hero-card-gradient-start' => $hero_card_gradient_start,
            '--fp-hero-card-gradient-end' => $hero_card_gradient_end,
            '--fp-hero-card-gradient-opacity-start' => $hero_card_gradient_opacity_start,
            '--fp-hero-card-gradient-opacity-end' => $hero_card_gradient_opacity_end,
            '--fp-hero-card-gradient' => $hero_card_gradient,
            '--fp-color-bg' => $background,
            '--fp-color-surface' => $surface,
            '--fp-color-text' => $text,
            '--fp-color-muted' => $muted,
            '--fp-color-success' => $success,
            '--fp-color-warning' => $warning,
            '--fp-color-danger' => $danger,
            '--fp-radius' => $radius,
            '--fp-shadow' => $shadow,
            '--fp-font-family' => $font,
            '--fp-gap' => $gap,
            '--fp-focus-ring' => $focus_ring,
            '--fp-focus-ring-soft' => $focus_ring_soft,
        ];
    }

    public static function design_tokens_css(): string
    {
        $tokens = [
            '--fp-color-primary' => '#0B6EFD',
            '--fp-color-secondary' => '#1857C4',
            '--fp-color-accent' => '#00A37A',
            '--fp-color-section-icon-background' => '#0B6EFD',
            '--fp-color-section-icon' => '#FFFFFF',
            '--fp-color-bg' => '#F7F8FA',
            '--fp-color-surface' => '#FFFFFF',
            '--fp-color-text' => '#0F172A',
            '--fp-color-muted' => '#64748B',
            '--fp-color-success' => '#1B998B',
            '--fp-color-warning' => '#F4A261',
            '--fp-color-danger' => '#C44536',
            '--fp-radius' => '16px',
            '--fp-shadow' => '0 8px 24px rgba(15,23,42,0.08)',
            '--fp-font-family' => 'inherit',
            '--fp-gap' => '24px',
        ];

        return ':root{' . self::stringify_variables($tokens) . '}';
    }

    /**
     * @param array<string, string> $variables
     */
    private static function stringify_variables(array $variables): string
    {
        $css = '';
        foreach ($variables as $var => $value) {
            $css .= $var . ':' . $value . ';';
        }

        return $css;
    }

    /**
     * Convert hex color to rgba string
     */
    private static function hex_to_rgba(string $hex, float $alpha): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');
        
        // Handle 3-digit hex
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }

    /**
     * @param array<string, string> $palette
     *
     * @return array<string, string>
     */
    private static function derive_dark_palette(array $palette): array
    {
        $dark_defaults = self::dark_defaults();
        $dark = $palette;
        $dark['background'] = $dark_defaults['background'];
        $dark['surface'] = $dark_defaults['surface'];
        $dark['text'] = $dark_defaults['text'];
        $dark['muted'] = $dark_defaults['muted'];
        $dark['shadow'] = $dark_defaults['shadow'];

        $dark['primary'] = self::mix_with_color($palette['primary'] ?? '#8B1E3F', '#FFFFFF', 0.18);
        $dark['secondary'] = self::mix_with_color($palette['secondary'] ?? '#405F3B', '#FFFFFF', 0.12);
        $dark['accent'] = self::mix_with_color($palette['accent'] ?? '#5B8C5A', '#FFFFFF', 0.15);
        $dark['section_icon_background'] = self::mix_with_color($palette['section_icon_background'] ?? '#0B6EFD', '#FFFFFF', 0.18);
        $dark['section_icon_color'] = '#FFFFFF';

        return $dark;
    }

    private static function contrast_ratio(string $color_a, string $color_b): float
    {
        $l1 = self::relative_luminance($color_a);
        $l2 = self::relative_luminance($color_b);

        if ($l1 < $l2) {
            [$l1, $l2] = [$l2, $l1];
        }

        return ($l1 + 0.05) / ($l2 + 0.05);
    }

    private static function relative_luminance(string $color): float
    {
        $rgb = self::hex_to_rgb($color);
        if (! $rgb) {
            return 0.0;
        }

        [$r, $g, $b] = $rgb;
        $components = [$r / 255, $g / 255, $b / 255];

        foreach ($components as &$component) {
            $component = $component <= 0.03928
                ? $component / 12.92
                : (($component + 0.055) / 1.055) ** 2.4;
        }
        unset($component);

        return 0.2126 * $components[0] + 0.7152 * $components[1] + 0.0722 * $components[2];
    }

    /**
     * @return array<int, int>|null
     */
    private static function hex_to_rgb(string $color): ?array
    {
        $color = ltrim($color, '#');
        if (3 === strlen($color)) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        if (6 !== strlen($color)) {
            return null;
        }

        return [
            hexdec(substr($color, 0, 2)),
            hexdec(substr($color, 2, 2)),
            hexdec(substr($color, 4, 2)),
        ];
    }

    private static function mix_with_color(string $color, string $target, float $ratio): string
    {
        $base = self::hex_to_rgb($color);
        $mix = self::hex_to_rgb($target);

        if (! $base || ! $mix) {
            return $color;
        }

        $r = (int) round($base[0] * (1 - $ratio) + $mix[0] * $ratio);
        $g = (int) round($base[1] * (1 - $ratio) + $mix[1] * $ratio);
        $b = (int) round($base[2] * (1 - $ratio) + $mix[2] * $ratio);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * @return array<string, string>
     */
    private static function dark_defaults(): array
    {
        return [
            'background' => '#101418',
            'surface' => '#151B21',
            'text' => '#F5F7FA',
            'muted' => '#A0AEC0',
            'shadow' => '0 14px 40px rgba(0,0,0,0.55)',
        ];
    }
}
