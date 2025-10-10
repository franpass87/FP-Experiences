<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use WP_REST_Request;

use function absint;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function current_user_can;
use function delete_transient;
use function explode;
use function do_action;
use function esc_url_raw;
use function filemtime;
use function function_exists;
use function get_current_user_id;
use function get_option;
use function get_post_meta;
use function get_transient;
use function home_url;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_readable;
use function is_string;
use function json_decode;
use function ltrim;
use function preg_split;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;
use function stripslashes;
use function time;
use function trim;
use function strtolower;
use function trailingslashit;
use function __;
use function wp_create_nonce;
use function wp_parse_args;
use function wp_unslash;
use function wp_verify_nonce;

use const FP_EXP_PLUGIN_DIR;
use const FP_EXP_VERSION;

final class Helpers
{
    /**
     * @var array<string, string>
     */
    private static array $asset_version_cache = [];

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

    public const COGNITIVE_BIAS_MAX_SELECTION = 6;

    public static function cognitive_bias_max_selection(): int
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

    public static function can_manage_fp(): bool
    {
        return current_user_can('fp_exp_manage')
            || current_user_can('manage_options')
            || current_user_can('manage_woocommerce');
    }

    public static function can_operate_fp(): bool
    {
        return current_user_can('fp_exp_operate')
            || current_user_can('manage_woocommerce')
            || current_user_can('edit_shop_orders')
            || self::can_manage_fp();
    }

    public static function can_access_guides(): bool
    {
        return current_user_can('fp_exp_guide') || self::can_operate_fp();
    }

    public static function management_capability(): string
    {
        return current_user_can('fp_exp_manage') ? 'fp_exp_manage' : 'manage_options';
    }

    public static function operations_capability(): string
    {
        if (current_user_can('fp_exp_operate')) {
            return 'fp_exp_operate';
        }

        if (current_user_can('fp_exp_manage')) {
            return 'fp_exp_manage';
        }

        if (current_user_can('manage_woocommerce')) {
            return 'manage_woocommerce';
        }

        return self::management_capability();
    }

    public static function guide_capability(): string
    {
        if (current_user_can('fp_exp_guide')) {
            return 'fp_exp_guide';
        }

        if (current_user_can('fp_exp_operate')) {
            return 'fp_exp_operate';
        }

        if (current_user_can('fp_exp_manage')) {
            return 'fp_exp_manage';
        }

        if (current_user_can('manage_woocommerce')) {
            return 'manage_woocommerce';
        }

        return self::management_capability();
    }

    /**
     * @return array<string, mixed>
     */
    public static function tracking_settings(): array
    {
        $settings = get_option('fp_exp_tracking', []);

        return is_array($settings) ? $settings : [];
    }

    public static function asset_version(string $relative_path): string
    {
        $relative_path = ltrim($relative_path, '/');

        if (isset(self::$asset_version_cache[$relative_path])) {
            return self::$asset_version_cache[$relative_path];
        }

        $absolute_path = trailingslashit(FP_EXP_PLUGIN_DIR) . $relative_path;

        if (is_readable($absolute_path)) {
            $mtime = filemtime($absolute_path);
            if (false !== $mtime) {
                self::$asset_version_cache[$relative_path] = (string) $mtime;

                return self::$asset_version_cache[$relative_path];
            }
        }

        self::$asset_version_cache[$relative_path] = FP_EXP_VERSION;

        return self::$asset_version_cache[$relative_path];
    }

    /**
     * Resolve the first existing readable asset path (relative to plugin dir), falling back to the last candidate.
     *
     * @param array<int, string> $candidates relative paths ordered by preference (minified first)
     */
    public static function resolve_asset_rel(array $candidates): string
    {
        $chosen = '';

        if (! empty($candidates)) {
            $chosen = (string) end($candidates);
            foreach ($candidates as $rel) {
                $rel = ltrim((string) $rel, '/');
                $abs = trailingslashit(FP_EXP_PLUGIN_DIR) . $rel;
                if (is_readable($abs)) {
                    $chosen = $rel;
                    break;
                }
            }
        }

        return $chosen;
    }

    /**
     * Build a serialisable config array for front-end scripts.
     *
     * @return array<string, mixed>
     */
    public static function tracking_config(): array
    {
        $settings = self::tracking_settings();

        $channels = [
            'ga4' => isset($settings['ga4']) && is_array($settings['ga4']) ? $settings['ga4'] : [],
            'google_ads' => isset($settings['google_ads']) && is_array($settings['google_ads']) ? $settings['google_ads'] : [],
            'meta_pixel' => isset($settings['meta_pixel']) && is_array($settings['meta_pixel']) ? $settings['meta_pixel'] : [],
            'clarity' => isset($settings['clarity']) && is_array($settings['clarity']) ? $settings['clarity'] : [],
        ];

        $enabled = [
            'ga4' => ! empty($channels['ga4']['enabled']) && (! empty($channels['ga4']['gtm_id']) || ! empty($channels['ga4']['measurement_id'])) && Consent::granted(Consent::CHANNEL_GA4),
            'google_ads' => ! empty($channels['google_ads']['enabled']) && ! empty($channels['google_ads']['conversion_id']) && Consent::granted(Consent::CHANNEL_GOOGLE_ADS),
            'meta_pixel' => ! empty($channels['meta_pixel']['enabled']) && ! empty($channels['meta_pixel']['pixel_id']) && Consent::granted(Consent::CHANNEL_META),
            'clarity' => ! empty($channels['clarity']['enabled']) && ! empty($channels['clarity']['project_id']) && Consent::granted(Consent::CHANNEL_CLARITY),
        ];

        $consent_defaults = isset($settings['consent_defaults']) && is_array($settings['consent_defaults'])
            ? array_map(static fn ($value) => ! empty($value), $settings['consent_defaults'])
            : [];

        return [
            'enabled' => $enabled,
            'ga4' => $channels['ga4'],
            'googleAds' => $channels['google_ads'],
            'metaPixel' => $channels['meta_pixel'],
            'clarity' => $channels['clarity'],
            'consentDefaults' => $consent_defaults,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listing_settings(): array
    {
        $defaults = [
            // Rimuoviamo il filtro "theme" di default per eliminare i temi esperienza dall'interfaccia pubblica
            'filters' => ['search', 'language', 'duration', 'price', 'family', 'date'],
            'per_page' => 9,
            'order' => 'ASC',
            'orderby' => 'menu_order',
            'show_price_from' => true,
            'experience_badges' => [
                'overrides' => [],
                'custom' => [],
            ],
        ];

        $settings = get_option('fp_exp_listing', []);
        $settings = is_array($settings) ? $settings : [];

        $filters = $settings['filters'] ?? $defaults['filters'];
        if (is_string($filters)) {
            $filters = array_map('trim', explode(',', $filters));
        }

        $filters = is_array($filters) ? $filters : [];
        $filters = array_values(array_filter(array_map(static function ($value): string {
            if (! is_string($value)) {
                return '';
            }

            return sanitize_key($value);
        }, $filters)));

        if (empty($filters)) {
            $filters = $defaults['filters'];
        }

        $per_page = absint((int) ($settings['per_page'] ?? $defaults['per_page']));
        if ($per_page <= 0) {
            $per_page = $defaults['per_page'];
        }

        $order = isset($settings['order']) ? strtoupper(sanitize_key((string) $settings['order'])) : $defaults['order'];
        if (! in_array($order, ['ASC', 'DESC'], true)) {
            $order = $defaults['order'];
        }

        $orderby = isset($settings['orderby']) ? sanitize_key((string) $settings['orderby']) : $defaults['orderby'];
        if (! in_array($orderby, ['menu_order', 'date', 'title', 'price'], true)) {
            $orderby = $defaults['orderby'];
        }

        $show_price_from = self::normalize_bool_option($settings['show_price_from'] ?? $defaults['show_price_from'], $defaults['show_price_from']);

        $badge_settings_raw = isset($settings['experience_badges']) && is_array($settings['experience_badges'])
            ? $settings['experience_badges']
            : [];

        $badge_overrides = [];
        if (isset($badge_settings_raw['overrides']) && is_array($badge_settings_raw['overrides'])) {
            foreach ($badge_settings_raw['overrides'] as $id => $override) {
                $badge_id = sanitize_key((string) $id);
                if ('' === $badge_id || ! is_array($override)) {
                    continue;
                }

                $entry = [];

                if (isset($override['label'])) {
                    $label_value = sanitize_text_field((string) $override['label']);
                    if ('' !== $label_value) {
                        $entry['label'] = $label_value;
                    }
                }

                if (array_key_exists('description', $override)) {
                    $entry['description'] = sanitize_text_field((string) $override['description']);
                }

                if ([] !== $entry) {
                    $badge_overrides[$badge_id] = $entry;
                }
            }
        }

        $badge_custom = [];
        $seen_custom = [];
        if (isset($badge_settings_raw['custom']) && is_array($badge_settings_raw['custom'])) {
            foreach ($badge_settings_raw['custom'] as $custom_badge) {
                if (! is_array($custom_badge)) {
                    continue;
                }

                $badge_id = isset($custom_badge['id']) ? sanitize_key((string) $custom_badge['id']) : '';
                $label_value = isset($custom_badge['label']) ? sanitize_text_field((string) $custom_badge['label']) : '';

                if ('' === $badge_id || '' === $label_value) {
                    continue;
                }

                if (isset($seen_custom[$badge_id])) {
                    continue;
                }

                $seen_custom[$badge_id] = true;

                $description_value = isset($custom_badge['description'])
                    ? sanitize_text_field((string) $custom_badge['description'])
                    : '';

                $badge_custom[] = [
                    'id' => $badge_id,
                    'label' => $label_value,
                    'description' => $description_value,
                ];
            }
        }

        $payload = [
            'filters' => $filters,
            'per_page' => $per_page,
            'order' => $order,
            'orderby' => $orderby,
            'show_price_from' => $show_price_from,
            'experience_badges' => [
                'overrides' => $badge_overrides,
                'custom' => $badge_custom,
            ],
        ];

        /**
         * Allow third parties to filter the listing defaults.
         *
         * @param array<string, mixed> $payload
         */
        return (array) apply_filters('fp_exp_listing_settings', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function gift_settings(): array
    {
        $defaults = [
            'enabled' => false,
            'validity_days' => 365,
            'reminders' => [30, 7, 1],
            'reminder_time' => '09:00',
            'redeem_page' => '',
        ];

        $settings = get_option('fp_exp_gift', []);
        $settings = is_array($settings) ? $settings : [];

        $enabled = self::normalize_bool_option($settings['enabled'] ?? $defaults['enabled'], false);

        $validity = absint((int) ($settings['validity_days'] ?? $defaults['validity_days']));
        if ($validity <= 0) {
            $validity = $defaults['validity_days'];
        }

        $reminders = $settings['reminders'] ?? $defaults['reminders'];
        if (is_string($reminders)) {
            $reminders = array_map('trim', explode(',', $reminders));
        }

        $reminders = is_array($reminders) ? $reminders : [];
        $reminders = array_values(array_unique(array_filter(array_map(static function ($value) {
            if ('' === $value) {
                return null;
            }

            if (is_numeric($value)) {
                $number = absint((string) $value);

                return $number > 0 ? $number : null;
            }

            return null;
        }, $reminders))));

        if (empty($reminders)) {
            $reminders = $defaults['reminders'];
        }

        sort($reminders);

        $time = isset($settings['reminder_time']) ? sanitize_text_field((string) $settings['reminder_time']) : $defaults['reminder_time'];
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = $defaults['reminder_time'];
        }

        $redeem_page = isset($settings['redeem_page']) ? esc_url_raw((string) $settings['redeem_page']) : '';

        return [
            'enabled' => $enabled,
            'validity_days' => $validity,
            'reminders' => $reminders,
            'reminder_time' => $time,
            'redeem_page' => $redeem_page,
        ];
    }

    public static function gift_enabled(): bool
    {
        $settings = self::gift_settings();

        return ! empty($settings['enabled']);
    }

    public static function gift_validity_days(): int
    {
        $settings = self::gift_settings();

        return (int) ($settings['validity_days'] ?? 365);
    }

    /**
     * @return array<int>
     */
    public static function gift_reminder_offsets(): array
    {
        $settings = self::gift_settings();
        $reminders = $settings['reminders'] ?? [];

        return array_map('absint', is_array($reminders) ? $reminders : []);
    }

    public static function gift_reminder_time(): string
    {
        $settings = self::gift_settings();

        return isset($settings['reminder_time']) ? (string) $settings['reminder_time'] : '09:00';
    }

    public static function gift_redeem_page(): string
    {
        $settings = self::gift_settings();
        $redeem_page = isset($settings['redeem_page']) ? (string) $settings['redeem_page'] : '';

        if ($redeem_page) {
            return $redeem_page;
        }

        $default = trailingslashit(home_url('/gift-redeem/'));

        /**
         * Allow third parties to change the fallback redemption page URL.
         */
        return (string) apply_filters('fp_exp_gift_redeem_page', $default);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rtb_settings(): array
    {
        $settings = get_option('fp_exp_rtb', []);
        $settings = is_array($settings) ? $settings : [];

        $defaults = [
            'mode' => 'off',
            'timeout' => 30,
            'block_capacity' => false,
            'templates' => [],
            'fallback' => [],
        ];

        $settings = wp_parse_args($settings, $defaults);

        $mode = is_string($settings['mode']) ? strtolower(sanitize_text_field($settings['mode'])) : 'off';
        if (! in_array($mode, ['off', 'confirm', 'pay_later'], true)) {
            $mode = 'off';
        }

        $settings['mode'] = $mode;
        $settings['timeout'] = max(5, absint($settings['timeout']));
        $settings['block_capacity'] = ! empty($settings['block_capacity']);
        $settings['templates'] = is_array($settings['templates']) ? $settings['templates'] : [];
        $settings['fallback'] = is_array($settings['fallback']) ? $settings['fallback'] : [];

        return $settings;
    }

    /**
     * @return array<int, array{id: string, label: string, description: string, tagline: string, icon: string, priority: int, keywords: array<int, string>}>
     */
    public static function cognitive_bias_choices(): array
    {
        if (null !== self::$cognitive_bias_choices_cache) {
            return self::$cognitive_bias_choices_cache;
        }

        $icon_registry = self::cognitive_bias_icons();

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
                'description' => __('Ricevi subito biglietti e dettagli via e-mail e nell’area riservata.', 'fp-experiences'),
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
                'description' => __('Assistenza multicanale prima, durante e dopo l’esperienza.', 'fp-experiences'),
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
     * @param array<int, string> $slugs
     * @return array<int, array{id: string, label: string, description: string, tagline: string, icon: string, priority: int, keywords: array<int, string>}>
     */
    public static function cognitive_bias_badges(array $slugs): array
    {
        $indexed = self::cognitive_bias_index();

        $normalized_slugs = array_values(array_unique(array_map(
            static fn ($slug): string => sanitize_key((string) $slug),
            $slugs
        )));

        $badges = [];
        foreach ($normalized_slugs as $slug) {
            if (isset($indexed[$slug])) {
                $badges[] = $indexed[$slug];
            }
        }

        return array_slice($badges, 0, self::cognitive_bias_max_selection());
    }

    /**
     * @param array<int, string> $slugs
     * @return array<int, string>
     */
    public static function cognitive_bias_labels(array $slugs): array
    {
        $badges = self::cognitive_bias_badges($slugs);

        return array_map(
            static fn (array $badge): string => $badge['label'],
            $badges
        );
    }

    /**
     * @return array<string, array{id: string, label: string, description: string, tagline: string, icon: string, priority: int, keywords: array<int, string>}>
     */
    public static function cognitive_bias_index(): array
    {
        if (null === self::$cognitive_bias_choices_index) {
            self::cognitive_bias_choices();
        }

        return self::$cognitive_bias_choices_index ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function cognitive_bias_icons(): array
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

    public static function cognitive_bias_icon_svg(string $icon): string
    {
        $icon = sanitize_key($icon);

        $icons = self::cognitive_bias_icons();

        if (! isset($icons[$icon])) {
            $icon = 'shield';
        }

        return $icons[$icon];
    }

    /**
     * @return array<string, array{id: string, label: string, description: string, icon: string}>
     */
    /**
     * @return array<int, array<string, string>>
     */
    public static function default_experience_badge_choices(): array
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
                'description' => __('Attività nella natura e all’aria aperta.', 'fp-experiences'),
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

    public static function experience_badge_choices(): array
    {
        if (null !== self::$experience_badge_choices_cache) {
            return self::$experience_badge_choices_cache;
        }

        $defaults = self::default_experience_badge_choices();

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
     * @param array<int, string> $slugs
     *
     * @return array<int, array{id: string, label: string, description: string, icon: string}>
     */
    public static function experience_badge_payload(array $slugs): array
    {
        $choices = self::experience_badge_choices();

        $slugs = array_map(static fn ($slug): string => sanitize_key((string) $slug), $slugs);
        $slugs = array_values(array_unique(array_filter($slugs)));

        $payload = [];

        foreach ($slugs as $slug) {
            if (! isset($choices[$slug])) {
                continue;
            }

            $payload[] = $choices[$slug];
        }

        return $payload;
    }

    /**
     * @param array<int, string> $slugs
     *
     * @return array<int, string>
     */
    public static function experience_badge_labels(array $slugs): array
    {
        $payload = self::experience_badge_payload($slugs);

        $labels = [];

        foreach ($payload as $badge) {
            $label = isset($badge['label']) ? (string) $badge['label'] : '';
            if ('' === $label) {
                continue;
            }

            $labels[] = $label;
        }

        return array_values(array_unique($labels));
    }

    public static function experience_badge_icon_svg(string $icon): string
    {
        $icon = sanitize_key($icon);

        if (null === self::$experience_badge_icon_cache) {
            $defaults = [
                'family' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 12.88 9.17 10H5a3 3 0 0 0-3 3v7h6v-4h2v4h6v-7a3 3 0 0 0-3-3h-1.17ZM4 6a3 3 0 1 1 3 3 3 3 0 0 1-3-3Zm10 3a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm7 2h-2.17l-2.4 2.4a4.81 4.81 0 0 1 1.57.93A3 3 0 0 1 22 18v2h-3v2h5v-4a3 3 0 0 0-3-3ZM3 22v-6h3v6Zm10-6v6h3v-6Z"/></svg>',
                'taste' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M7 2a3 3 0 0 0-3 3v6a5 5 0 0 0 4 4.9V22h2v-6.1a5 5 0 0 0 4-4.9V5a3 3 0 0 0-3-3Zm0 2h4a1 1 0 0 1 1 1v3H6V5a1 1 0 0 1 1-1Zm-1 6h6a3 3 0 0 1-6 0Zm12-4h-2v8a4 4 0 0 0 4 4v2h2V6a2 2 0 0 0-2-2Z"/></svg>',
                'wine' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M8 2h8l1 9a5 5 0 0 1-3.5 5V20h2v2h-6v-2h2v-4A5 5 0 0 1 7 11Zm2.08 7a3 3 0 0 0 5.84 0Z"/></svg>',
                'olive' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M19 3a5 5 0 0 0-5 5v1h-1a7 7 0 0 0-7 7 6 6 0 0 0 6 6 7 7 0 0 0 7-7v-1h1a5 5 0 0 0 0-10Zm-7 18a4 4 0 0 1-4-4 5 5 0 0 1 5-5h1v1a5 5 0 0 0 4 4 4 4 0 0 1-4 4Zm7-10h-3V8a3 3 0 0 1 6 0 3 3 0 0 1-3 3Z"/></svg>',
                'outdoor' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 2 3 20h6l3-6 3 6h6Z"/></svg>',
                'craft' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M21.71 6.29 17.71 2.3a1 1 0 0 0-1.41 0L14.59 4H10l-1 2H5a3 3 0 0 0-3 3v4h2v8h2v-8h2v8h2v-8h3.59l1.71 1.71a1 1 0 0 0 1.41 0l4-4a1 1 0 0 0 0-1.42ZM18 10.17 15.83 12 12 8.17 14.17 6ZM4 11a1 1 0 0 1 1-1h3v2H4Z"/></svg>',
                'default' => '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path fill="currentColor" d="M12 2a5 5 0 0 0-5 5v5a5 5 0 0 0 4 4.9V22h2v-5.1a5 5 0 0 0 4-4.9V7a5 5 0 0 0-5-5Zm0 2a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z"/></svg>',
            ];

            $registry = apply_filters('fp_exp_experience_badge_icon_registry', $defaults);
            $registry = is_array($registry) ? $registry : $defaults;

            $normalized = [];

            foreach ($registry as $key => $svg) {
                $icon_key = sanitize_key((string) $key);
                if ('' === $icon_key) {
                    continue;
                }

                if (! is_string($svg)) {
                    continue;
                }

                $markup = trim($svg);
                if ('' === $markup) {
                    continue;
                }

                $normalized[$icon_key] = $markup;
            }

            if (! isset($normalized['default'])) {
                $normalized['default'] = $defaults['default'];
            }

            self::$experience_badge_icon_cache = $normalized;
        }

        $icons = self::$experience_badge_icon_cache;

        if (! isset($icons[$icon])) {
            $icon = 'default';
        }

        return $icons[$icon];
    }

    public static function rtb_mode(): string
    {
        $settings = self::rtb_settings();

        return (string) ($settings['mode'] ?? 'off');
    }

    public static function rtb_mode_for_experience(int $experience_id): string
    {
        if ($experience_id > 0 && ! self::experience_uses_rtb($experience_id)) {
            return 'off';
        }

        return self::rtb_mode();
    }

    public static function rest_nonce(): string
    {
        return wp_create_nonce('wp_rest');
    }

    public static function verify_rest_nonce(WP_REST_Request $request, string $action, array $param_keys = ['nonce', '_wpnonce']): bool
    {
        $header_nonce = $request->get_header('x-wp-nonce');

        if (is_string($header_nonce) && $header_nonce) {
            $header_nonce = sanitize_text_field($header_nonce);
            if (wp_verify_nonce($header_nonce, $action)) {
                return true;
            }
        }

        foreach ($param_keys as $key) {
            $value = $request->get_param($key);
            if (! is_string($value) || '' === $value) {
                continue;
            }

            $value = sanitize_text_field($value);

            if (wp_verify_nonce($value, $action)) {
                return true;
            }
        }

        return false;
    }

    public static function verify_public_rest_request(WP_REST_Request $request): bool
    {
        if (self::verify_rest_nonce($request, 'wp_rest', ['_wpnonce'])) {
            return true;
        }

        $referer = sanitize_text_field((string) $request->get_header('referer'));
        if ($referer) {
            $home = home_url();
            if ($home && strpos($referer, $home) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function rtb_hold_timeout(): int
    {
        $settings = self::rtb_settings();

        return max(5, absint($settings['timeout'] ?? 30));
    }

    public static function experience_uses_rtb(int $experience_id): bool
    {
        if ($experience_id <= 0) {
            return false;
        }

        $value = get_post_meta($experience_id, '_fp_use_rtb', true);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        $normalized = strtolower((string) $value);

        return in_array($normalized, ['1', 'yes', 'true', 'on'], true);
    }

    public static function rtb_block_capacity(int $experience_id): bool
    {
        $settings = self::rtb_settings();

        if ('off' === $settings['mode']) {
            return false;
        }

        if ($experience_id > 0 && ! self::experience_uses_rtb($experience_id)) {
            return false;
        }

        return ! empty($settings['block_capacity']);
    }

    public static function meeting_points_enabled(): bool
    {
        return self::normalize_yes_no_option(get_option('fp_exp_enable_meeting_points', 'yes'), true);
    }

    public static function meeting_points_import_enabled(): bool
    {
        return self::normalize_yes_no_option(get_option('fp_exp_enable_meeting_point_import', 'no'), false);
    }

    /**
     * Normalise array meta to a sanitised list of strings.
     *
     * @param array<int|string, mixed> $default
     *
     * @return array<int, string>
     */
    public static function get_meta_array(int $post_id, string $key, array $default = []): array
    {
        $raw = get_post_meta($post_id, $key, true);

        if (empty($raw)) {
            $raw = $default;
        }

        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw)) {
            $parts = preg_split('/\r\n|\r|\n/', $raw);
            $values = false !== $parts ? $parts : [$raw];
        } else {
            return [];
        }

        $values = array_map(static function ($value): string {
            // Gestisce array nidificati o elementi non validi
            if (is_array($value)) {
                return '';
            }
            return sanitize_text_field((string) $value);
        }, $values);

        $values = array_filter($values, static function (string $value): bool {
            return '' !== $value;
        });

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $value
     */
    private static function normalize_yes_no_option($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ('' === $normalized) {
                return $default;
            }

            return in_array($normalized, ['1', 'yes', 'true', 'on'], true);
        }

        if (null === $value) {
            return $default;
        }

        return (bool) $value;
    }

    /**
     * @return array<string, string>
     */
    public static function read_utm_cookie(): array
    {
        if (empty($_COOKIE['fp_exp_utm'])) {
            return [];
        }

        $decoded = json_decode(stripslashes((string) $_COOKIE['fp_exp_utm']), true);

        if (! is_array($decoded)) {
            return [];
        }

        $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid'];
        $sanitised = [];

        foreach ($allowed as $key) {
            if (! empty($decoded[$key])) {
                $sanitised[$key] = sanitize_text_field((string) $decoded[$key]);
            }
        }

        return $sanitised;
    }

    public static function hit_rate_limit(string $key, int $limit, int $window): bool
    {
        $limit = max(1, $limit);
        $window = max(1, $window);

        $bucket_key = 'fp_exp_rl_' . md5($key);
        $bucket = get_transient($bucket_key);
        $now = time();

        if (! is_array($bucket) || empty($bucket['expires']) || $bucket['expires'] <= $now) {
            set_transient($bucket_key, [
                'count' => 1,
                'expires' => $now + $window,
            ], $window);

            return false;
        }

        if (($bucket['count'] ?? 0) >= $limit) {
            return true;
        }

        $bucket['count'] = ($bucket['count'] ?? 0) + 1;
        $ttl = max(1, (int) $bucket['expires'] - $now);
        set_transient($bucket_key, $bucket, $ttl);

        return false;
    }

    public static function client_fingerprint(): string
    {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return 'user_' . $user_id;
        }

        $candidates = [];

        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
            $forwarded = sanitize_text_field((string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
            foreach (explode(',', $forwarded) as $ip) {
                $ip = trim($ip);
                if ($ip) {
                    $candidates[] = $ip;
                }
            }
        }

        if (! empty($_SERVER['REMOTE_ADDR'])) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
            $candidates[] = sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.ServerVariable
        }

        $identifier = '';
        foreach ($candidates as $candidate) {
            if ($candidate) {
                $identifier = $candidate;
                break;
            }
        }

        if ('' === $identifier) {
            return 'guest_anon';
        }

        return 'ip_' . hash('sha256', $identifier);
    }

    public static function debug_logging_enabled(): bool
    {
        $option = get_option('fp_exp_debug_logging', 'yes');

        if (is_bool($option)) {
            $enabled = $option;
        } elseif (is_string($option)) {
            $normalized = strtolower(trim($option));
            $enabled = ! in_array($normalized, ['0', 'no', 'off', 'false'], true);
        } elseif (is_numeric($option)) {
            $enabled = (int) $option > 0;
        } else {
            $enabled = (bool) $option;
        }

        return (bool) apply_filters('fp_exp_debug_logging_enabled', $enabled);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function log_debug(string $channel, string $message, array $context = []): void
    {
        if (! self::debug_logging_enabled()) {
            return;
        }

        Logger::log($channel, $message, $context);
    }

    public static function clear_experience_transients(int $experience_id): void
    {
        delete_transient('fp_exp_pricing_notice_' . $experience_id);
        delete_transient('fp_exp_calendar_choices');
        delete_transient('fp_exp_price_from_' . $experience_id);

        do_action('fp_exp_experience_transients_cleared', $experience_id);
    }

    public static function currency_code(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            $currency = (string) \get_woocommerce_currency();
            if ($currency) {
                return $currency;
            }
        }

        $option = get_option('woocommerce_currency');
        if (is_string($option) && '' !== $option) {
            return $option;
        }

        return 'EUR';
    }

    /**
     * @param mixed $value
     */
    private static function normalize_bool_option($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ('' === $normalized) {
                return $default;
            }

            return in_array($normalized, ['1', 'yes', 'true', 'on'], true);
        }

        return $default;
    }
}
