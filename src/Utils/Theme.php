<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use const ARRAY_FILTER_USE_BOTH;
use function __;
use function array_filter;
use function esc_attr;
use function get_option;
use function in_array;
use function is_array;
use function is_string;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function uniqid;
use function wp_parse_args;

final class Theme
{
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
            'light' => [
                'label' => __('Light', 'fp-experiences'),
                'values' => self::base_palette(),
            ],
            'dark' => [
                'label' => __('Dark', 'fp-experiences'),
                'values' => array_merge(self::base_palette(), [
                    'background' => '#101418',
                    'surface' => '#151B21',
                    'text' => '#F5F7FA',
                    'muted' => '#A0AEC0',
                    'shadow' => '0 14px 40px rgba(0,0,0,0.55)',
                ]),
            ],
            'natural' => [
                'label' => __('Natural (green)', 'fp-experiences'),
                'values' => array_merge(self::base_palette(), [
                    'primary' => '#2E7D32',
                    'secondary' => '#558B2F',
                    'accent' => '#8BC34A',
                    'surface' => '#F1F8E9',
                ]),
            ],
            'wine' => [
                'label' => __('Wine / Burgundy', 'fp-experiences'),
                'values' => array_merge(self::base_palette(), [
                    'primary' => '#7A1E3A',
                    'secondary' => '#512031',
                    'accent' => '#C67E7D',
                    'surface' => '#F8F1F3',
                ]),
            ],
        ];
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
            : 'light';

        if (isset($overrides['preset']) && isset($presets[$overrides['preset']])) {
            $preset_key = (string) $overrides['preset'];
        }

        $palette = $presets[$preset_key]['values'];

        $mode = isset($branding['mode']) ? (string) $branding['mode'] : 'light';
        if (isset($overrides['mode']) && is_string($overrides['mode'])) {
            $mode = (string) $overrides['mode'];
        }
        if (! in_array($mode, ['light', 'dark', 'auto'], true)) {
            $mode = 'light';
        }

        $clean_branding = array_filter($branding, static function ($value, $key): bool {
            if (in_array($key, ['preset', 'mode'], true)) {
                return false;
            }

            return '' !== $value && null !== $value;
        }, ARRAY_FILTER_USE_BOTH);

        $palette = wp_parse_args($clean_branding, $palette);

        $override_colors = array_filter($overrides, static fn ($value, $key) => ! in_array($key, ['preset', 'mode'], true) && '' !== $value && null !== $value, ARRAY_FILTER_USE_BOTH);
        $palette = wp_parse_args($override_colors, $palette);

        foreach ($palette as $key => $value) {
            if ('radius' === $key || 'shadow' === $key || 'font' === $key) {
                $palette[$key] = is_string($value) ? sanitize_text_field($value) : self::base_palette()[$key];
                continue;
            }

            if (! is_string($value)) {
                $palette[$key] = self::base_palette()[$key];
                continue;
            }

            $color = sanitize_hex_color($value);
            $palette[$key] = $color ?: self::base_palette()[$key];
        }

        $palette['mode'] = $mode;
        $palette['preset'] = $preset_key;

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
            $css .= '.fp-theme-dark .' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}';
        } else {
            $css .= '.' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['base']) . '}';

            if ('auto' === $mode) {
                $css .= '@media (prefers-color-scheme: dark){.' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}}';
                $css .= '.fp-theme-dark .' . esc_attr($scope_class) . '{' . self::stringify_variables($vars['dark']) . '}';
            }
        }

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
        $primary_contrast = self::contrast_ratio($palette['primary'] ?? '#8B1E3F', $palette['background'] ?? '#FFFFFF');
        if ($primary_contrast < 4.5) {
            $messages[] = sprintf(
                /* translators: %s: contrast ratio. */
                __('Primary color contrast against the background is %.2f:1. Consider adjusting colors for AA compliance.', 'fp-experiences'),
                $primary_contrast
            );
        }

        $text_contrast = self::contrast_ratio($palette['text'] ?? '#1F1F1F', $palette['background'] ?? '#FFFFFF');
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
            'primary' => '#8B1E3F',
            'secondary' => '#405F3B',
            'accent' => '#5B8C5A',
            'background' => '#FFFFFF',
            'surface' => '#F7F4F0',
            'text' => '#1F1F1F',
            'muted' => '#666666',
            'success' => '#1B998B',
            'warning' => '#F4A261',
            'danger' => '#C44536',
            'radius' => '12px',
            'shadow' => '0 10px 30px rgba(0,0,0,0.08)',
            'font' => '',
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
        return [
            '--fp-exp-color-primary' => $palette['primary'] ?? '#8B1E3F',
            '--fp-exp-color-secondary' => $palette['secondary'] ?? '#405F3B',
            '--fp-exp-color-accent' => $palette['accent'] ?? '#5B8C5A',
            '--fp-exp-color-background' => $palette['background'] ?? '#FFFFFF',
            '--fp-exp-color-surface' => $palette['surface'] ?? '#F7F4F0',
            '--fp-exp-color-text' => $palette['text'] ?? '#1F1F1F',
            '--fp-exp-color-muted' => $palette['muted'] ?? '#666666',
            '--fp-exp-color-success' => $palette['success'] ?? '#1B998B',
            '--fp-exp-color-warning' => $palette['warning'] ?? '#F4A261',
            '--fp-exp-color-danger' => $palette['danger'] ?? '#C44536',
            '--fp-exp-radius-base' => $palette['radius'] ?? '12px',
            '--fp-exp-shadow-base' => $palette['shadow'] ?? '0 10px 30px rgba(0,0,0,0.08)',
            '--fp-exp-font-family' => $palette['font'] ? $palette['font'] : 'inherit',
        ];
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
     * @param array<string, string> $palette
     *
     * @return array<string, string>
     */
    private static function derive_dark_palette(array $palette): array
    {
        $dark_defaults = self::presets()['dark']['values'];
        $dark = $palette;
        $dark['background'] = $dark_defaults['background'];
        $dark['surface'] = $dark_defaults['surface'];
        $dark['text'] = $dark_defaults['text'];
        $dark['muted'] = $dark_defaults['muted'];
        $dark['shadow'] = $dark_defaults['shadow'];

        $dark['primary'] = self::mix_with_color($palette['primary'] ?? '#8B1E3F', '#FFFFFF', 0.18);
        $dark['secondary'] = self::mix_with_color($palette['secondary'] ?? '#405F3B', '#FFFFFF', 0.12);
        $dark['accent'] = self::mix_with_color($palette['accent'] ?? '#5B8C5A', '#FFFFFF', 0.15);

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
}
