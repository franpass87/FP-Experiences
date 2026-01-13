<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

if (! defined('ABSPATH')) {
    exit;
}

use function esc_html__;
use function in_array;
use function preg_replace;
use function str_replace;
use function sprintf;
use function strtolower;
use function substr;
use function trailingslashit;
use function trim;

final class LanguageHelper
{
    private const SPRITE_PATH = 'assets/svg/flags.svg';

    /**
     * @var array<string, array{label: string, display: string, aliases: array<int, string>}>
     */
    private const LANGUAGE_MAP = [
        'it' => [
            'label' => 'Italiano',
            'display' => 'IT',
            'aliases' => ['it', 'it-it', 'italian', 'italiano', 'ita'],
        ],
        'en' => [
            'label' => 'English',
            'display' => 'EN',
            'aliases' => ['en', 'en-gb', 'en-us', 'english', 'inglese', 'eng'],
        ],
        'fr' => [
            'label' => 'Français',
            'display' => 'FR',
            'aliases' => ['fr', 'fr-fr', 'french', 'francese', 'fra', 'francais'],
        ],
        'de' => [
            'label' => 'Deutsch',
            'display' => 'DE',
            'aliases' => ['de', 'de-de', 'german', 'tedesco', 'ger'],
        ],
        'es' => [
            'label' => 'Español',
            'display' => 'ES',
            'aliases' => ['es', 'es-es', 'spanish', 'spagnolo', 'spa', 'espanol'],
        ],
        'pt' => [
            'label' => 'Português',
            'display' => 'PT',
            'aliases' => ['pt', 'pt-pt', 'pt-br', 'portuguese', 'portoghese', 'por', 'portugues'],
        ],
        'nl' => [
            'label' => 'Nederlands',
            'display' => 'NL',
            'aliases' => ['nl', 'nl-nl', 'dutch', 'olandese'],
        ],
        'ru' => [
            'label' => 'Русский',
            'display' => 'RU',
            'aliases' => ['ru', 'ru-ru', 'russian', 'russo'],
        ],
        'ja' => [
            'label' => '日本語',
            'display' => 'JA',
            'aliases' => ['ja', 'ja-jp', 'japanese', 'giapponese'],
        ],
        'zh' => [
            'label' => '中文',
            'display' => 'ZH',
            'aliases' => ['zh', 'zh-cn', 'zh-tw', 'chinese', 'cinese', 'mandarin'],
        ],
        'ar' => [
            'label' => 'العربية',
            'display' => 'AR',
            'aliases' => ['ar', 'ar-sa', 'arabic', 'arabo'],
        ],
        'pl' => [
            'label' => 'Polski',
            'display' => 'PL',
            'aliases' => ['pl', 'pl-pl', 'polish', 'polacco'],
        ],
    ];

    private function __construct()
    {
    }

    public static function get_sprite_url(): string
    {
        return trailingslashit(FP_EXP_PLUGIN_URL) . self::SPRITE_PATH;
    }

    /**
     * @param array<int, string> $languages
     *
     * @return array<int, array<string, string>>
     */
    public static function build_language_badges(array $languages): array
    {
        $badges = [];

        foreach ($languages as $language) {
            $language = trim((string) $language);
            if ('' === $language) {
                continue;
            }

            $badges[] = self::build_single_badge($language);
        }

        return $badges;
    }

    /**
     * @return array<string, string>
     */
    public static function build_single_badge(string $language): array
    {
        $original = trim($language);
        $normalized = self::normalize($original);

        foreach (self::LANGUAGE_MAP as $code => $config) {
            if ($normalized === $code || in_array($normalized, $config['aliases'], true)) {
                $label = $config['label'];

                return [
                    'code' => strtoupper($config['display']),
                    'label' => $label,
                    'sprite' => 'fp-exp-flag-' . $code,
                    'aria_label' => sprintf('%s (%s)', $label, strtoupper($config['display'])),
                ];
            }
        }

        $fallback_code = strtoupper(substr($normalized, 0, 2));
        if ('' === $fallback_code) {
            $fallback_code = '??';
        }

        $label = '' !== $original ? $original : esc_html__('Unknown language', 'fp-experiences');

        return [
            'code' => $fallback_code,
            'label' => $label,
            'sprite' => 'fp-exp-flag-globe',
            'aria_label' => sprintf('%s (%s)', $label, $fallback_code),
        ];
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        
        // Converti caratteri accentati in equivalenti ASCII
        $value = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'd', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'th', 'y'],
            $value
        );
        
        $value = str_replace([' ', '_'], '-', $value);
        $value = preg_replace('/[^a-z-]/', '', $value);

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }
}
