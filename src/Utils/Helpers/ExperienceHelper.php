<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function apply_filters;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function explode;
use function get_option;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function strcmp;
use function trim;
use function uasort;

/**
 * Helper for experience-related functions (badges, cognitive bias).
 */
final class ExperienceHelper
{
    public const COGNITIVE_BIAS_MAX_SELECTION = 6;

    /**
     * @var array<int, array{id: string, label: string, description: string, tagline: string, icon: string, priority: int, keywords: array<int, string>}>|null
     */
    private static ?array $cognitive_bias_choices_cache = null;

    /**
     * @var array<string, array{id: string, label: string, description: string, tagline: string, icon: string, priority: int, keywords: array<int, string>}>|null
     */
    private static ?array $cognitive_bias_choices_index = null;

    /**
     * @var array<string, string>|null
     */
    private static ?array $cognitive_bias_icon_cache = null;

    /**
     * @var array<string, array{id: string, label: string, description: string, icon: string}>|null
     */
    private static ?array $experience_badge_choices_cache = null;

    /**
     * @var array<string, string>|null
     */
    private static ?array $experience_badge_icon_cache = null;

    /**
     * Get cognitive bias max selection.
     */
    public static function cognitiveBiasMaxSelection(): int
    {
        $max = apply_filters('fp_exp_cognitive_bias_max_selection', self::COGNITIVE_BIAS_MAX_SELECTION);

        if (! is_numeric($max)) {
            $max = self::COGNITIVE_BIAS_MAX_SELECTION;
        }

        $max = (int) $max;

        if ($max <= 0) {
            $max = self::COGNITIVE_BIAS_MAX_SELECTION;
        }

        return $max;
    }

    /**
     * Get cognitive bias choices.
     *
     * @return array<int, array{id: string, label: string, description: string, tagline: string, icon: string, priority: int, keywords: array<int, string>}>
     */
    public static function cognitiveBiasChoices(): array
    {
        if (null !== self::$cognitive_bias_choices_cache) {
            return self::$cognitive_bias_choices_cache;
        }

        $icon_registry = self::cognitiveBiasIcons();

        $defaults = [
            [
                'id' => 'safe-booking',
                'label' => __('Prenotazione sicura', 'fp-experiences'),
                'description' => __('Pagamenti crittografati e protezione antifrode per ogni transazione.', 'fp-experiences'),
                'tagline' => __('Transazioni protette', 'fp-experiences'),
                'icon' => 'shield',
                'priority' => 10,
                'keywords' => [
                    __('sicurezza', 'fp-experiences'),
                    __('pagamento sicuro', 'fp-experiences'),
                    __('antifrode', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'protected-checkout',
                'label' => __('Checkout protetto', 'fp-experiences'),
                'description' => __('Carte e wallet digitali approvati con conferma in tempo reale.', 'fp-experiences'),
                'tagline' => __('Paghi come preferisci', 'fp-experiences'),
                'icon' => 'lock',
                'priority' => 20,
                'keywords' => [
                    __('checkout', 'fp-experiences'),
                    __('pagamenti digitali', 'fp-experiences'),
                    __('wallet', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'flexible-cancellation',
                'label' => __('Cancellazione flessibile', 'fp-experiences'),
                'description' => __('Puoi modificare o annullare entro i termini indicati senza penali.', 'fp-experiences'),
                'tagline' => __('Piani che cambiano? Nessun problema', 'fp-experiences'),
                'icon' => 'calendar',
                'priority' => 30,
                'keywords' => [
                    __('cambio data', 'fp-experiences'),
                    __('rimborso', 'fp-experiences'),
                    __('politica flessibile', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'instant-confirmation',
                'label' => __('Conferma immediata', 'fp-experiences'),
                'description' => __('Ricevi subito biglietti e dettagli via e-mail e nell\'area riservata.', 'fp-experiences'),
                'tagline' => __('Ricevi tutto in pochi secondi', 'fp-experiences'),
                'icon' => 'bolt',
                'priority' => 40,
                'keywords' => [
                    __('e-ticket', 'fp-experiences'),
                    __('conferma istantanea', 'fp-experiences'),
                    __('email immediata', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'best-price',
                'label' => __('Miglior prezzo garantito', 'fp-experiences'),
                'description' => __('Se trovi un prezzo più basso ti rimborsiamo la differenza.', 'fp-experiences'),
                'tagline' => __('Zero sorprese sul prezzo', 'fp-experiences'),
                'icon' => 'badge',
                'priority' => 50,
                'keywords' => [
                    __('garanzia prezzo', 'fp-experiences'),
                    __('risparmio', 'fp-experiences'),
                    __('rimborso differenza', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'verified-experience',
                'label' => __('Esperienza verificata', 'fp-experiences'),
                'description' => __('Operatori certificati e recensioni verificate da viaggiatori reali.', 'fp-experiences'),
                'tagline' => __('Partner selezionati', 'fp-experiences'),
                'icon' => 'star',
                'priority' => 60,
                'keywords' => [
                    __('certificata', 'fp-experiences'),
                    __('recensioni verificate', 'fp-experiences'),
                    __('qualità garantita', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'dedicated-support',
                'label' => __('Supporto dedicato', 'fp-experiences'),
                'description' => __('Assistenza multicanale prima, durante e dopo l\'esperienza.', 'fp-experiences'),
                'tagline' => __('Siamo con te in ogni fase', 'fp-experiences'),
                'icon' => 'headset',
                'priority' => 70,
                'keywords' => [
                    __('assistenza', 'fp-experiences'),
                    __('supporto 24/7', 'fp-experiences'),
                    __('contatto diretto', 'fp-experiences'),
                ],
            ],
            [
                'id' => 'gift-option',
                'label' => __('Perfetta come regalo', 'fp-experiences'),
                'description' => __('Voucher personalizzabili con messaggi e consegna immediata.', 'fp-experiences'),
                'tagline' => __('Biglietto regalo digitale', 'fp-experiences'),
                'icon' => 'gift',
                'priority' => 80,
                'keywords' => [
                    __('idea regalo', 'fp-experiences'),
                    __('voucher', 'fp-experiences'),
                    __('buono regalo', 'fp-experiences'),
                ],
            ],
        ];

        $maybe_filtered = apply_filters('fp_exp_cognitive_bias_choices', $defaults);
        $maybe_filtered = is_array($maybe_filtered) ? $maybe_filtered : $defaults;

        $normalized = [];
        foreach ($maybe_filtered as $position => $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $id = isset($choice['id']) ? sanitize_key((string) $choice['id']) : '';
            $label = isset($choice['label']) ? (string) $choice['label'] : '';
            $description = isset($choice['description']) ? (string) $choice['description'] : '';
            $tagline = isset($choice['tagline']) ? (string) $choice['tagline'] : '';
            $icon = isset($choice['icon']) ? sanitize_key((string) $choice['icon']) : '';
            if ('' === $icon || ! isset($icon_registry[$icon])) {
                $icon = 'shield';
            }
            $priority = isset($choice['priority']) ? (int) $choice['priority'] : (($position + 1) * 10);
            $keywords = [];

            if (isset($choice['keywords']) && is_array($choice['keywords'])) {
                foreach ($choice['keywords'] as $keyword) {
                    $keyword_value = sanitize_text_field((string) $keyword);
                    if ('' === $keyword_value) {
                        continue;
                    }

                    $keywords[] = $keyword_value;
                }
            }

            $keywords = array_values(array_unique($keywords));

            if ('' === $id || '' === $label) {
                continue;
            }

            $normalized[$id] = [
                'id' => $id,
                'label' => sanitize_text_field($label),
                'description' => sanitize_text_field($description),
                'tagline' => sanitize_text_field($tagline),
                'icon' => $icon,
                'priority' => $priority,
                'keywords' => $keywords,
            ];
        }

        uasort(
            $normalized,
            static function (array $a, array $b): int {
                if ($a['priority'] === $b['priority']) {
                    return strcmp($a['label'], $b['label']);
                }

                return $a['priority'] <=> $b['priority'];
            }
        );

        self::$cognitive_bias_choices_index = $normalized;
        self::$cognitive_bias_choices_cache = array_values($normalized);

        return self::$cognitive_bias_choices_cache;
    }

    /**
     * Get experience badge choices.
     *
     * @return array<string, array{id: string, label: string, description: string, icon: string}>
     */
    public static function experienceBadgeChoices(): array
    {
        if (null !== self::$experience_badge_choices_cache) {
            return self::$experience_badge_choices_cache;
        }

        $defaults = self::defaultExperienceBadgeChoices();

        $choices = apply_filters('fp_exp_experience_badges', $defaults);
        $choices = is_array($choices) ? $choices : $defaults;

        $normalized = [];

        foreach ($choices as $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $id = isset($choice['id']) ? sanitize_key((string) $choice['id']) : '';
            if ('' === $id) {
                continue;
            }

            $label = isset($choice['label']) ? sanitize_text_field((string) $choice['label']) : '';
            if ('' === $label) {
                continue;
            }

            $description = isset($choice['description'])
                ? sanitize_text_field((string) $choice['description'])
                : '';
            $icon = isset($choice['icon']) ? sanitize_key((string) $choice['icon']) : '';

            $normalized[$id] = [
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'icon' => $icon,
            ];
        }

        if (empty($normalized)) {
            $normalized['family-friendly'] = [
                'id' => 'family-friendly',
                'label' => __('Family friendly', 'fp-experiences'),
                'description' => __('Perfetta per bambini e genitori.', 'fp-experiences'),
                'icon' => 'family',
            ];
        }

        $listing_settings = get_option('fp_exp_listing', []);
        if (is_array($listing_settings) && isset($listing_settings['experience_badges']) && is_array($listing_settings['experience_badges'])) {
            $badge_settings = $listing_settings['experience_badges'];

            $overrides = isset($badge_settings['overrides']) && is_array($badge_settings['overrides'])
                ? $badge_settings['overrides']
                : [];

            foreach ($overrides as $id => $override) {
                $badge_id = sanitize_key((string) $id);
                if ('' === $badge_id || ! isset($normalized[$badge_id]) || ! is_array($override)) {
                    continue;
                }

                if (isset($override['label'])) {
                    $label = sanitize_text_field((string) $override['label']);
                    if ('' !== $label) {
                        $normalized[$badge_id]['label'] = $label;
                    }
                }

                if (array_key_exists('description', $override)) {
                    $normalized[$badge_id]['description'] = sanitize_text_field((string) $override['description']);
                }
            }

            $custom_badges = isset($badge_settings['custom']) && is_array($badge_settings['custom'])
                ? $badge_settings['custom']
                : [];

            foreach ($custom_badges as $custom_badge) {
                if (! is_array($custom_badge)) {
                    continue;
                }

                $badge_id = isset($custom_badge['id']) ? sanitize_key((string) $custom_badge['id']) : '';
                $label = isset($custom_badge['label']) ? sanitize_text_field((string) $custom_badge['label']) : '';

                if ('' === $badge_id || '' === $label) {
                    continue;
                }

                $description = isset($custom_badge['description'])
                    ? sanitize_text_field((string) $custom_badge['description'])
                    : '';
                $icon = isset($custom_badge['icon']) ? sanitize_key((string) $custom_badge['icon']) : '';
                if ('' === $icon) {
                    $icon = 'default';
                }

                $normalized[$badge_id] = [
                    'id' => $badge_id,
                    'label' => $label,
                    'description' => $description,
                    'icon' => $icon,
                ];
            }
        }

        self::$experience_badge_choices_cache = $normalized;

        return self::$experience_badge_choices_cache;
    }

    /**
     * Get cognitive bias icons registry.
     *
     * @return array<string, string>
     */
    private static function cognitiveBiasIcons(): array
    {
        if (null !== self::$cognitive_bias_icon_cache) {
            return self::$cognitive_bias_icon_cache;
        }

        $defaults = [
            'shield' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2 4 5v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V5Zm0 15c-2.21-.93-4-4-4-6.74V7.36l4-1.45 4 1.45v2.9C16 13 14.21 16.07 12 17Z"/></svg>',
            'lock' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-8a3 3 0 0 0-3-3Zm-5 9.5A1.5 1.5 0 1 1 13.5 16 1.5 1.5 0 0 1 12 17.5Zm3-9.5H9V6a3 3 0 0 1 6 0Z"/></svg>',
            'calendar' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 3h-1V1h-2v2H8V1H6v2H5a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3Zm1 17a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V10h16Zm0-12H4V6a1 1 0 0 1 1-1h1v2h2V5h8v2h2V5h1a1 1 0 0 1 1 1Z"/></svg>',
            'bolt' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.14 3 5 13.65h6l-2.14 7.35L19 9.35h-6Z"/></svg>',
            'badge' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 2H5v20l7-3 7 3Zm-3.21 7.79-4 4a1 1 0 0 1-1.42 0l-2-2 1.42-1.41L11 11.59l3.29-3.3Z"/></svg>',
            'star' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2 9.18 8.26 2 9.27l5.45 4.86L5.82 21 12 17.27 18.18 21l-1.63-6.87L22 9.27l-7.18-1.01Z"/></svg>',
            'headset' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a9 9 0 0 0-9 9v6a3 3 0 0 0 3 3h2v-8H6v-1a6 6 0 0 1 12 0v1h-2v8h2a3 3 0 0 0 3-3v-6a9 9 0 0 0-9-9Zm-1 18h2v2h-2Z"/></svg>',
            'gift' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 7h-1.17A3 3 0 0 0 20 5a3 3 0 0 0-5-2.24L12 5.2 9 2.76A3 3 0 0 0 4 5a3 3 0 0 0 1.17 2H4a2 2 0 0 0-2 2v3a1 1 0 0 0 1 1h1v7a2 2 0 0 0 2 2h4a1 1 0 0 0 1-1v-8h2v8a1 1 0 0 0 1 1h4a2 2 0 0 0 2-2v-7h1a1 1 0 0 0 1-1V9a2 2 0 0 0-2-2ZM6 5a1 1 0 0 1 1.62-.78L11 6H7a1 1 0 0 1-1-1Zm3 15H6v-6h3Zm9 0h-3v-6h3Zm2-8H4V9h16Z"/></svg>',
        ];

        $registry = apply_filters('fp_exp_cognitive_bias_icon_registry', $defaults);
        $registry = is_array($registry) ? $registry : $defaults;

        $normalized = [];

        foreach ($registry as $icon => $svg) {
            $key = sanitize_key((string) $icon);
            if ('' === $key) {
                continue;
            }

            if (! is_string($svg)) {
                continue;
            }

            $markup = trim($svg);
            if ('' === $markup) {
                continue;
            }

            $normalized[$key] = $markup;
        }

        if (! isset($normalized['shield'])) {
            $normalized['shield'] = $defaults['shield'];
        }

        self::$cognitive_bias_icon_cache = $normalized;

        return self::$cognitive_bias_icon_cache;
    }

    /**
     * Get default experience badge choices.
     *
     * @return array<int, array{id: string, label: string, description: string, icon: string}>
     */
    private static function defaultExperienceBadgeChoices(): array
    {
        return [
            [
                'id' => 'family-friendly',
                'label' => __('Family friendly', 'fp-experiences'),
                'description' => __('Perfetta per bambini e genitori.', 'fp-experiences'),
                'icon' => 'family',
            ],
            [
                'id' => 'gastronomy',
                'label' => __('Gastronomia', 'fp-experiences'),
                'description' => __('Degustazioni e sapori del territorio.', 'fp-experiences'),
                'icon' => 'taste',
            ],
            [
                'id' => 'wine',
                'label' => __('Vino', 'fp-experiences'),
                'description' => __('Cantine, vigneti e calici selezionati.', 'fp-experiences'),
                'icon' => 'wine',
            ],
            [
                'id' => 'olive-oil',
                'label' => __('Olio EVO', 'fp-experiences'),
                'description' => __('Frantoi e degustazioni di olio extravergine.', 'fp-experiences'),
                'icon' => 'olive',
            ],
            [
                'id' => 'outdoor',
                'label' => __('Outdoor', 'fp-experiences'),
                'description' => __('Attività nella natura e all\'aria aperta.', 'fp-experiences'),
                'icon' => 'outdoor',
            ],
            [
                'id' => 'craftsmanship',
                'label' => __('Artigianato', 'fp-experiences'),
                'description' => __('Laboratori e saper fare tradizionale.', 'fp-experiences'),
                'icon' => 'craft',
            ],
        ];
    }

    /**
     * Clear experience transients.
     */
    public static function clearExperienceTransients(int $experience_id): void
    {
        if ($experience_id <= 0) {
            return;
        }

        // Clear various transients related to experience
        $transients = [
            "fp_exp_experience_{$experience_id}",
            "fp_exp_experience_badges_{$experience_id}",
            "fp_exp_experience_cognitive_bias_{$experience_id}",
        ];

        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }
}















