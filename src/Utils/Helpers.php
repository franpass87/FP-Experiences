<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

if (! defined('ABSPATH')) {
    exit;
}

use FP_Exp\Core\Bootstrap\Bootstrap;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Services\Cache\CacheInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Utils\Helpers\AssetHelper;
use FP_Exp\Utils\Helpers\ExperienceHelper;
use FP_Exp\Utils\Helpers\GiftHelper;
use FP_Exp\Utils\Helpers\PermissionHelper;
use FP_Exp\Utils\Helpers\RTBHelper;
use FP_Exp\Utils\Helpers\TrackingHelper;
use FP_Exp\Utils\Helpers\UtilityHelper;
use DateTimeImmutable;
use DateTimeZone;
use WP_Error;
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
use function esc_attr;
use function esc_url_raw;
use function filemtime;
use function function_exists;
use function get_current_user_id;
use function get_role;
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
use function wp_get_current_user;
use function is_user_logged_in;
use function wp_timezone;

use const FP_EXP_PLUGIN_DIR;
use const FP_EXP_VERSION;

final class Helpers
{
    /**
     * @var array<string, string>
     */
    private static array $asset_version_cache = [];

    /**
     * Clear the asset version cache.
     */
    /**
     * Clear asset version cache (delegated to AssetHelper).
     *
     * @deprecated Use AssetHelper::clearCache() instead
     */
    public static function clear_asset_version_cache(): void
    {
        AssetHelper::clearCache();
    }

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

    /**
     * Get cognitive bias max selection (delegated to ExperienceHelper).
     *
     * @deprecated Use ExperienceHelper::cognitiveBiasMaxSelection() instead
     */
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

    /**
     * Get OptionsInterface instance.
     * Tries container first, falls back to direct instantiation for backward compatibility.
     *
     * @return OptionsInterface
     */
    private static function getOptions(): OptionsInterface
    {
        static $options = null;
        
        if ($options !== null) {
            return $options;
        }

        // Try to get from container
        $kernel = Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                    return $options;
                } catch (\Throwable $e) {
                    // Fall through to direct instantiation
                }
            }
        }

        // Fallback to direct instantiation
        $options = new \FP_Exp\Services\Options\Options();
        return $options;
    }

    /**
     * Check if user can manage FP Experiences (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::canManage() instead
     */
    public static function can_manage_fp(): bool
    {
        return PermissionHelper::canManage();
    }

    /**
     * Ensure that administrators (role and current user) always retain the FP Experiences capabilities.
     *
     * Called on admin_init to repair installations where role propagation did not run correctly.
     */
    /**
     * Ensure admin capabilities (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::ensureAdminCapabilities() instead
     */
    public static function ensure_admin_capabilities(): void
    {
        PermissionHelper::ensureAdminCapabilities();
    }

    /**
     * Check if user can operate FP Experiences (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::canOperate() instead
     */
    public static function can_operate_fp(): bool
    {
        return PermissionHelper::canOperate();
    }

    /**
     * Check if user can access guides (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::canAccessGuides() instead
     */
    public static function can_access_guides(): bool
    {
        return PermissionHelper::canAccessGuides();
    }

    /**
     * Get management capability (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::managementCapability() instead
     */
    public static function management_capability(): string
    {
        return PermissionHelper::managementCapability();
    }

    /**
     * Get operations capability (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::operationsCapability() instead
     */
    public static function operations_capability(): string
    {
        return PermissionHelper::operationsCapability();
    }

    /**
     * Get guide capability (delegated to PermissionHelper).
     *
     * @deprecated Use PermissionHelper::guideCapability() instead
     */
    public static function guide_capability(): string
    {
        return PermissionHelper::guideCapability();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Get tracking settings (delegated to TrackingHelper).
     *
     * @deprecated Use TrackingHelper::getSettings() instead
     *
     * @return array<string, mixed>
     */
    public static function tracking_settings(): array
    {
        return TrackingHelper::getSettings();
    }

    /**
     * Get asset version (delegated to AssetHelper).
     *
     * @deprecated Use AssetHelper::getVersion() instead
     */
    public static function asset_version(string $relative_path): string
    {
        return AssetHelper::getVersion($relative_path);
    }

    /**
     * Dipendenze per `wp_enqueue_style( 'fp-exp-admin', ... )`: dopo `colors` (scheme admin WP).
     *
     * @return list<string>
     */
    public static function admin_style_dependencies(): array
    {
        return AssetHelper::adminStyleDependencies();
    }

    /**
     * Resolve the first existing readable asset path (relative to plugin dir), falling back to the last candidate.
     *
     * If a preferred (typically minified) asset is older than a later fallback candidate, the fallback is used to avoid stale bundles.
     *
     * @param array<int, string> $candidates relative paths ordered by preference (minified first)
     */
    /**
     * Resolve asset path (delegated to AssetHelper).
     *
     * @deprecated Use AssetHelper::resolveAssetPath() instead
     *
     * @param array<int, string> $candidates
     */
    public static function resolve_asset_rel(array $candidates): string
    {
        if (empty($candidates)) {
            return '';
        }

        $resolved = [];
        $fallback = '';

        foreach ($candidates as $index => $candidate) {
            if (! is_string($candidate) || '' === $candidate) {
                continue;
            }

            $rel = ltrim($candidate, '/');
            $fallback = $rel;
            $abs = trailingslashit(FP_EXP_PLUGIN_DIR) . $rel;

            if (! is_readable($abs)) {
                continue;
            }

            $mtime = filemtime($abs);
            $resolved[] = [
                'index' => (int) $index,
                'rel' => $rel,
                'mtime' => false !== $mtime ? (int) $mtime : 0,
            ];
        }

        if (empty($resolved)) {
            return $fallback;
        }

        usort($resolved, static fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        foreach ($resolved as $candidate) {
            $is_stale = false;

            foreach ($resolved as $other) {
                if ($other['index'] <= $candidate['index']) {
                    continue;
                }

                if ($other['mtime'] > $candidate['mtime']) {
                    $is_stale = true;
                    break;
                }
            }

            if (! $is_stale) {
                return $candidate['rel'];
            }
        }

        $last = end($resolved);

        return is_array($last) && isset($last['rel']) ? (string) $last['rel'] : $fallback;
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

        $settings = self::getOptions()->get('fp_exp_listing', []);
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
    /**
     * Get gift settings (delegated to GiftHelper).
     *
     * @deprecated Use GiftHelper::getSettings() instead
     *
     * @return array<string, mixed>
     */
    public static function gift_settings(): array
    {
        return GiftHelper::getSettings();
    }

    public static function gift_enabled(): bool
    {
        $settings = self::gift_settings();

        return ! empty($settings['enabled']);
    }

    /**
     * Whether gift vouchers are allowed for experiences marked as single-date events (_fp_is_event).
     */
    public static function gift_allow_single_date_events(): bool
    {
        $settings = self::gift_settings();
        $raw = $settings['allow_gift_single_date'] ?? 'yes';

        return in_array($raw, ['yes', '1', 1, true, 'true'], true);
    }

    /**
     * Whether gift purchase/UI is allowed for a given experience (global gift + single-date policy).
     */
    public static function gift_enabled_for_experience(int $experience_id): bool
    {
        if (! self::gift_enabled() || $experience_id <= 0) {
            return false;
        }

        $is_event = (bool) get_post_meta($experience_id, '_fp_is_event', true);
        if (! $is_event) {
            return true;
        }

        return self::gift_allow_single_date_events();
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
     * Get RTB settings (delegated to RTBHelper).
     *
     * @deprecated Use RTBHelper::getSettings() instead
     *
     * @return array<string, mixed>
     */
    public static function rtb_settings(): array
    {
        return RTBHelper::getSettings();
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
            'shield' => self::experience_badge_fa_icon_markup('fa-solid fa-shield-halved'),
            'lock' => self::experience_badge_fa_icon_markup('fa-solid fa-lock'),
            'calendar' => self::experience_badge_fa_icon_markup('fa-solid fa-calendar-days'),
            'bolt' => self::experience_badge_fa_icon_markup('fa-solid fa-bolt'),
            'badge' => self::experience_badge_fa_icon_markup('fa-solid fa-award'),
            'star' => self::experience_badge_fa_icon_markup('fa-solid fa-circle-check'),
            'headset' => self::experience_badge_fa_icon_markup('fa-solid fa-headset'),
            'gift' => self::experience_badge_fa_icon_markup('fa-solid fa-gift'),
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
     * Markup icona badge di fiducia (cognitive bias): predefinito Font Awesome 6 Solid; filtro `fp_exp_cognitive_bias_icon_registry` può restituire SVG/HTML.
     */
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

        $listing_settings = self::getOptions()->get('fp_exp_listing', []);
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

    /**
     * Markup icona Font Awesome per badge esperienza (classi definite dal plugin).
     *
     * @param string $fa_classes Es. `fa-solid fa-users`.
     */
    private static function experience_badge_fa_icon_markup(string $fa_classes): string
    {
        $fa_classes = trim(preg_replace('/\s+/', ' ', $fa_classes) ?? '');
        if ('' === $fa_classes) {
            $fa_classes = 'fa-solid fa-tag';
        }

        return '<i class="' . esc_attr($fa_classes) . '" aria-hidden="true"></i>';
    }

    /**
     * Mappa slug icona → classi Font Awesome 6 Solid (set predefinito + icone extra per badge custom).
     *
     * Filtro: `fp_exp_experience_badge_icon_fa_class_map`.
     *
     * @return array<string, string>
     */
    public static function experience_badge_icon_fa_class_map(): array
    {
        $map = [
            'default' => 'fa-solid fa-tag',
            'family' => 'fa-solid fa-users',
            'taste' => 'fa-solid fa-utensils',
            'wine' => 'fa-solid fa-wine-glass-empty',
            'olive' => 'fa-solid fa-droplet',
            'outdoor' => 'fa-solid fa-mountain-sun',
            'craft' => 'fa-solid fa-hammer',
            'star' => 'fa-solid fa-star',
            'clock' => 'fa-solid fa-clock',
            'location' => 'fa-solid fa-location-dot',
            'heart' => 'fa-solid fa-heart',
            'certificate' => 'fa-solid fa-certificate',
            'camera' => 'fa-solid fa-camera',
            'music' => 'fa-solid fa-music',
            'bus' => 'fa-solid fa-bus',
            'ticket' => 'fa-solid fa-ticket',
            'gift' => 'fa-solid fa-gift',
            'calendar' => 'fa-solid fa-calendar-days',
            'info' => 'fa-solid fa-circle-info',
            'phone' => 'fa-solid fa-phone',
            'envelope' => 'fa-solid fa-envelope',
        ];

        /**
         * Estende le icone Font Awesome disponibili per badge (slug => `fa-solid fa-...`).
         *
         * @param array<string, string> $map
         */
        $filtered = apply_filters('fp_exp_experience_badge_icon_fa_class_map', $map);

        return is_array($filtered) ? $filtered : $map;
    }

    /**
     * Markup icona badge esperienza (predefinito: Font Awesome 6 Solid, come sul frontend).
     *
     * Il nome storico del metodo resta `experience_badge_icon_svg`; il filtro può ancora restituire SVG o altro HTML.
     *
     * @param string $icon Chiave registry (es. family, wine).
     */
    public static function experience_badge_icon_svg(string $icon): string
    {
        $icon = sanitize_key($icon);

        if (null === self::$experience_badge_icon_cache) {
            $defaults = [];
            foreach (self::experience_badge_icon_fa_class_map() as $slug => $classes) {
                $slug = sanitize_key((string) $slug);
                if ('' === $slug || ! is_string($classes) || '' === trim($classes)) {
                    continue;
                }
                $defaults[$slug] = self::experience_badge_fa_icon_markup($classes);
            }

            if (! isset($defaults['default'])) {
                $defaults['default'] = self::experience_badge_fa_icon_markup('fa-solid fa-tag');
            }

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

    /**
     * Markup SVG (Font Awesome 6 Free Solid, path ufficiali) per le icone intestazione sezioni `[fp_exp_page]`.
     * Evita il font-icon: il glifo è nel viewBox e si centra in modo affidabile nel quadrato colorato.
     *
     * Filtro: `fp_exp_experience_page_section_icon_html` — passare stringa HTML non vuota per sostituire il markup.
     *
     * @param string $section Chiave sezione: overview, gallery, gift, participation_info, highlights, inclusions, meeting, extras, faq, reviews.
     */
    public static function experience_page_section_icon_html(string $section): string
    {
        $section = sanitize_key($section);

        $custom = apply_filters('fp_exp_experience_page_section_icon_html', null, $section);
        if (is_string($custom)) {
            $custom = trim($custom);
            if ('' !== $custom) {
                return $custom;
            }
        }

        $paths = self::experience_page_section_icon_paths();

        if (! isset($paths[$section])) {
            $section = 'faq';
        }

        $def = $paths[$section];
        $viewBox = esc_attr((string) ($def['viewBox'] ?? '0 0 512 512'));
        $d = esc_attr((string) ($def['d'] ?? ''));

        if ('' === $d) {
            return '';
        }

        return sprintf(
            '<svg class="fp-exp-section-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="%s" fill="currentColor" aria-hidden="true" focusable="false"><path d="%s"/></svg>',
            $viewBox,
            $d
        );
    }

    /**
     * Path SVG Font Awesome 6.5.2 solid (licenza Free: icone CC BY 4.0).
     *
     * @return array<string, array{viewBox: string, d: string}>
     */
    private static function experience_page_section_icon_paths(): array
    {
        return [
            'overview' => ['viewBox' => '0 0 640 512', 'd' => 'M323.4 85.2l-96.8 78.4c-16.1 13-19.2 36.4-7 53.1c12.9 17.8 38 21.3 55.3 7.8l99.3-77.2c7-5.4 17-4.2 22.5 2.8s4.2 17-2.8 22.5l-20.9 16.2L512 316.8V128h-.7l-3.9-2.5L434.8 79c-15.3-9.8-33.2-15-51.4-15c-21.8 0-43 7.5-60 21.2zm22.8 124.4l-51.7 40.2C263 274.4 217.3 268 193.7 235.6c-22.2-30.5-16.6-73.1 12.7-96.8l83.2-67.3c-11.6-4.9-24.1-7.4-36.8-7.4C234 64 215.7 69.6 200 80l-72 48V352h28.2l91.4 83.4c19.6 17.9 49.9 16.5 67.8-3.1c5.5-6.1 9.2-13.2 11.1-20.6l17 15.6c19.5 17.9 49.9 16.6 67.8-2.9c4.5-4.9 7.8-10.6 9.9-16.5c19.4 13 45.8 10.3 62.1-7.5c17.9-19.5 16.6-49.9-2.9-67.8l-134.2-123zM16 128c-8.8 0-16 7.2-16 16V352c0 17.7 14.3 32 32 32H64c17.7 0 32-14.3 32-32V128H16zM48 320a16 16 0 1 1 0 32 16 16 0 1 1 0-32zM544 128V352c0 17.7 14.3 32 32 32h32c17.7 0 32-14.3 32-32V144c0-8.8-7.2-16-16-16H544zm32 208a16 16 0 1 1 32 0 16 16 0 1 1 -32 0z'],
            'gallery' => ['viewBox' => '0 0 576 512', 'd' => 'M160 32c-35.3 0-64 28.7-64 64V320c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H160zM396 138.7l96 144c4.9 7.4 5.4 16.8 1.2 24.6S480.9 320 472 320H328 280 200c-9.2 0-17.6-5.3-21.6-13.6s-2.9-18.2 2.9-25.4l64-80c4.6-5.7 11.4-9 18.7-9s14.2 3.3 18.7 9l17.3 21.6 56-84C360.5 132 368 128 376 128s15.5 4 20 10.7zM192 128a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zM48 120c0-13.3-10.7-24-24-24S0 106.7 0 120V344c0 75.1 60.9 136 136 136H456c13.3 0 24-10.7 24-24s-10.7-24-24-24H136c-48.6 0-88-39.4-88-88V120z'],
            'gift' => ['viewBox' => '0 0 512 512', 'd' => 'M190.5 68.8L225.3 128H224 152c-22.1 0-40-17.9-40-40s17.9-40 40-40h2.2c14.9 0 28.8 7.9 36.3 20.8zM64 88c0 14.4 3.5 28 9.6 40H32c-17.7 0-32 14.3-32 32v64c0 17.7 14.3 32 32 32H480c17.7 0 32-14.3 32-32V160c0-17.7-14.3-32-32-32H438.4c6.1-12 9.6-25.6 9.6-40c0-48.6-39.4-88-88-88h-2.2c-31.9 0-61.5 16.9-77.7 44.4L256 85.5l-24.1-41C215.7 16.9 186.1 0 154.2 0H152C103.4 0 64 39.4 64 88zm336 0c0 22.1-17.9 40-40 40H288h-1.3l34.8-59.2C329.1 55.9 342.9 48 357.8 48H360c22.1 0 40 17.9 40 40zM32 288V464c0 26.5 21.5 48 48 48H224V288H32zM288 512H432c26.5 0 48-21.5 48-48V288H288V512z'],
            'participation_info' => ['viewBox' => '0 0 512 512', 'd' => 'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z'],
            'highlights' => ['viewBox' => '0 0 576 512', 'd' => 'M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z'],
            'inclusions' => ['viewBox' => '0 0 384 512', 'd' => 'M192 0c-41.8 0-77.4 26.7-90.5 64H64C28.7 64 0 92.7 0 128V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H282.5C269.4 26.7 233.8 0 192 0zm0 64a32 32 0 1 1 0 64 32 32 0 1 1 0-64zM72 272a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm104-16H304c8.8 0 16 7.2 16 16s-7.2 16-16 16H176c-8.8 0-16-7.2-16-16s7.2-16 16-16zM72 368a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm88 0c0-8.8 7.2-16 16-16H304c8.8 0 16 7.2 16 16s-7.2 16-16 16H176c-8.8 0-16-7.2-16-16z'],
            'meeting' => ['viewBox' => '0 0 320 512', 'd' => 'M16 144a144 144 0 1 1 288 0A144 144 0 1 1 16 144zM160 80c8.8 0 16-7.2 16-16s-7.2-16-16-16c-53 0-96 43-96 96c0 8.8 7.2 16 16 16s16-7.2 16-16c0-35.3 28.7-64 64-64zM128 480V317.1c10.4 1.9 21.1 2.9 32 2.9s21.6-1 32-2.9V480c0 17.7-14.3 32-32 32s-32-14.3-32-32z'],
            'extras' => ['viewBox' => '0 0 512 512', 'd' => 'M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-69.9-50.5-129.5-119.4-141C347 36.5 300.6 51.4 268 84L256 96 244 84c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1v5.8c0 41.5 17.2 81.2 47.6 109.5z'],
            'faq' => ['viewBox' => '0 0 512 512', 'd' => 'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM169.8 165.3c7.9-22.3 29.1-37.3 52.8-37.3h58.3c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24V250.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1H222.6c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z'],
            'reviews' => ['viewBox' => '0 0 640 512', 'd' => 'M208 352c114.9 0 208-78.8 208-176S322.9 0 208 0S0 78.8 0 176c0 38.6 14.7 74.3 39.6 103.4c-3.5 9.4-8.7 17.7-14.2 24.7c-4.8 6.2-9.7 11-13.3 14.3c-1.8 1.6-3.3 2.9-4.3 3.7c-.5 .4-.9 .7-1.1 .8l-.2 .2 0 0 0 0C1 327.2-1.4 334.4 .8 340.9S9.1 352 16 352c21.8 0 43.8-5.6 62.1-12.5c9.2-3.5 17.8-7.4 25.3-11.4C134.1 343.3 169.8 352 208 352zM448 176c0 112.3-99.1 196.9-216.5 207C255.8 457.4 336.4 512 432 512c38.2 0 73.9-8.7 104.7-23.9c7.5 4 16 7.9 25.2 11.4c18.3 6.9 40.3 12.5 62.1 12.5c6.9 0 13.1-4.5 15.2-11.1c2.1-6.6-.2-13.8-5.8-17.9l0 0 0 0-.2-.2c-.2-.2-.6-.4-1.1-.8c-1-.8-2.5-2-4.3-3.7c-3.6-3.3-8.5-8.1-13.3-14.3c-5.5-7-10.7-15.4-14.2-24.7c24.9-29 39.6-64.7 39.6-103.4c0-92.8-84.9-168.9-192.6-175.5c.4 5.1 .6 10.3 .6 15.5z'],
        ];
    }

    /**
     * Normalizza la chiave icona verso una presente nel registry (markup / FA / SVG via filtro).
     */
    public static function sanitize_experience_badge_icon_key(string $icon): string
    {
        self::experience_badge_icon_svg('default');
        $icons = self::$experience_badge_icon_cache ?? [];
        $icon = sanitize_key($icon);
        if ('' === $icon || ! isset($icons[$icon])) {
            return 'default';
        }

        return $icon;
    }

    /**
     * Slug icona => etichetta per select in admin (estende le chiavi del registry, inclusi filtri).
     *
     * @return array<string, string>
     */
    public static function experience_badge_icon_admin_options(): array
    {
        self::experience_badge_icon_svg('default');
        $icons = self::$experience_badge_icon_cache ?? [];
        if (! is_array($icons) || [] === $icons) {
            return ['default' => __('Generica', 'fp-experiences')];
        }

        $keys = array_keys($icons);
        sort($keys);
        $labels = [
            'default' => __('Generica', 'fp-experiences'),
            'family' => __('Famiglia / bambini', 'fp-experiences'),
            'taste' => __('Gastronomia', 'fp-experiences'),
            'wine' => __('Vino', 'fp-experiences'),
            'olive' => __('Olio / oliva', 'fp-experiences'),
            'outdoor' => __('Outdoor / natura', 'fp-experiences'),
            'craft' => __('Artigianato / laboratorio', 'fp-experiences'),
            'star' => __('Stella / in evidenza', 'fp-experiences'),
            'clock' => __('Orologio / durata', 'fp-experiences'),
            'location' => __('Luogo / pin mappa', 'fp-experiences'),
            'heart' => __('Cuore / passione', 'fp-experiences'),
            'certificate' => __('Certificato / qualità', 'fp-experiences'),
            'camera' => __('Foto / ricordo', 'fp-experiences'),
            'music' => __('Musica / spettacolo', 'fp-experiences'),
            'bus' => __('Trasporto / pullman', 'fp-experiences'),
            'ticket' => __('Biglietto', 'fp-experiences'),
            'gift' => __('Regalo', 'fp-experiences'),
            'calendar' => __('Calendario / date', 'fp-experiences'),
            'info' => __('Informazioni', 'fp-experiences'),
            'phone' => __('Telefono', 'fp-experiences'),
            'envelope' => __('Email / messaggio', 'fp-experiences'),
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $labels[$k] ?? $k;
        }

        return $out;
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
        // Prima verifica: nonce REST valido
        if (self::verify_rest_nonce($request, 'wp_rest', ['_wpnonce'])) {
            return true;
        }

        // Seconda verifica: referer stesso dominio (solo per richieste pubbliche sicure)
        $referer = sanitize_text_field((string) $request->get_header('referer'));
        if ($referer) {
            $home = home_url();
            if ($home) {
                $parsed_home = wp_parse_url($home);
                $parsed_referer = wp_parse_url($referer);
                
                // Verifica che il dominio sia identico (non solo prefisso)
                if ($parsed_home && $parsed_referer && 
                    isset($parsed_home['host'], $parsed_referer['host']) &&
                    $parsed_home['host'] === $parsed_referer['host']) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function rtb_hold_timeout(): int
    {
        $settings = self::rtb_settings();

        return max(5, absint($settings['timeout'] ?? 1440));
    }

    public static function experience_uses_rtb(int $experience_id): bool
    {
        if ($experience_id <= 0) {
            return false;
        }

        // Controlla se RTB è abilitato globalmente
        $global_rtb_mode = self::rtb_mode();
        $global_rtb_enabled = ('off' !== $global_rtb_mode);

        // Controlla se l'esperienza ha un override specifico
        // IMPORTANTE: usa metadata_exists() per distinguere tra "mai impostato" e "impostato a false"
        $meta_exists = metadata_exists('post', $experience_id, '_fp_use_rtb');
        $value = get_post_meta($experience_id, '_fp_use_rtb', true);

        // Debug logging per tracciare la decisione RTB
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp Helpers] experience_uses_rtb(' . $experience_id . '): global_mode=' . $global_rtb_mode . ', global_enabled=' . ($global_rtb_enabled ? 'true' : 'false') . ', meta_exists=' . ($meta_exists ? 'true' : 'false') . ', exp_meta=' . print_r($value, true));
        }

        // Se il meta field non esiste nel database, usa l'impostazione globale
        if (! $meta_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-Exp Helpers] experience_uses_rtb(' . $experience_id . '): Meta not exists, using global setting -> ' . ($global_rtb_enabled ? 'true' : 'false'));
            }
            return $global_rtb_enabled;
        }

        // Se il meta field è vuoto/null ma esiste, considera come "usa globale"
        if ($value === '' || $value === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-Exp Helpers] experience_uses_rtb(' . $experience_id . '): Meta is empty, using global setting -> ' . ($global_rtb_enabled ? 'true' : 'false'));
            }
            return $global_rtb_enabled;
        }

        // Se il meta field è impostato, rispetta la scelta specifica dell'esperienza
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
        return self::normalize_yes_no_option(self::getOptions()->get('fp_exp_enable_meeting_points', 'yes'), true);
    }

    public static function meeting_points_import_enabled(): bool
    {
        return self::normalize_yes_no_option(self::getOptions()->get('fp_exp_enable_meeting_point_import', 'no'), false);
    }

    /**
     * Normalise array meta to a sanitised list of strings.
     *
     * @param array<int|string, mixed> $default
     *
     * @return array<int, string>
     */
    /**
     * Get meta array (delegated to UtilityHelper).
     *
     * @deprecated Use UtilityHelper::getMetaArray() instead
     *
     * @param array<string, mixed> $default
     *
     * @return array<string, mixed>
     */
    public static function get_meta_array(int $post_id, string $key, array $default = []): array
    {
        $raw = get_post_meta($post_id, $key, true);

        if (empty($raw)) {
            $raw = $default;
        }

        // Gestisce il caso di dati corrotti dove è stata salvata la stringa "Array"
        if (is_string($raw) && strtolower(trim($raw)) === 'array') {
            return [];
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
            $trimmed = trim($value);
            return '' !== $trimmed && strtolower($trimmed) !== 'array';
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
    /**
     * Read UTM cookie (delegated to TrackingHelper).
     *
     * @deprecated Use TrackingHelper::readUtmCookie() instead
     *
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

    /**
     * Check if rate limit is hit (delegated to UtilityHelper).
     *
     * @deprecated Use UtilityHelper::hitRateLimit() instead
     */
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

    /**
     * Generate client fingerprint (delegated to UtilityHelper).
     *
     * @deprecated Use UtilityHelper::clientFingerprint() instead
     */
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

    /**
     * Check if debug logging is enabled (delegated to UtilityHelper).
     *
     * @deprecated Use UtilityHelper::isDebugLoggingEnabled() instead
     */
    public static function debug_logging_enabled(): bool
    {
        // Try to use new Options service if available
        $options = self::getService(OptionsInterface::class);
        if ($options instanceof OptionsInterface) {
            $option = $options->get('fp_exp_debug_logging', 'yes');
        } else {
            // Fallback to direct get_option for backward compatibility
            $option = self::getOptions()->get('fp_exp_debug_logging', 'yes');
        }

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
     * Log a debug message.
     *
     * @param string $channel Log channel
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    /**
     * Log a debug message.
     *
     * @deprecated 1.2.0 Use LoggerInterface::log() or LoggerInterface::debug() instead.
     *                   This method is kept for backward compatibility but will be removed in version 2.0.0.
     * @see \FP_Exp\Services\Logger\LoggerInterface
     * @param string $channel Log channel name
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public static function log_debug(string $channel, string $message, array $context = []): void
    {
        if (WP_DEBUG && function_exists('_deprecated_function')) {
            _deprecated_function(
                __METHOD__,
                '1.2.1',
                'LoggerInterface::log() or LoggerInterface::debug()'
            );
        }

        if (! self::debug_logging_enabled()) {
            return;
        }

        // Try to use new Logger service if available
        $logger = self::getService(LoggerInterface::class);
        if ($logger instanceof LoggerInterface) {
            $logger->log($channel, $message, $context);
        } else {
            // Fallback to legacy Logger for backward compatibility
            Logger::log($channel, $message, $context);
        }
    }

    /**
     * Clear experience transients (delegated to ExperienceHelper).
     *
     * @deprecated Use ExperienceHelper::clearExperienceTransients() instead
     */
    public static function clear_experience_transients(int $experience_id): void
    {
        // Try to use new Cache service if available
        $cache = self::getService(CacheInterface::class);
        if ($cache instanceof CacheInterface) {
            $cache->delete('fp_exp_pricing_notice_' . $experience_id);
            $cache->delete('fp_exp_calendar_choices');
            $cache->delete('fp_exp_price_from_' . $experience_id);
        } else {
            // Fallback to direct delete_transient for backward compatibility
            delete_transient('fp_exp_pricing_notice_' . $experience_id);
            delete_transient('fp_exp_calendar_choices');
            delete_transient('fp_exp_price_from_' . $experience_id);
        }

        do_action('fp_exp_experience_transients_cleared', $experience_id);
    }

    /**
     * Get currency code (delegated to UtilityHelper).
     *
     * @deprecated Use UtilityHelper::currencyCode() instead
     */
    public static function currency_code(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            $currency = (string) \get_woocommerce_currency();
            if ($currency) {
                return $currency;
            }
        }

        // Try to use new Options service if available
        $options = self::getService(OptionsInterface::class);
        if ($options instanceof OptionsInterface) {
            $option = $options->get('woocommerce_currency', '');
        } else {
            // Fallback to direct get_option for backward compatibility
            $option = get_option('woocommerce_currency', '');
        }

        if (is_string($option) && '' !== $option) {
            return $option;
        }

        return 'EUR';
    }

    /**
     * Indica se per un evento a data singola le vendite biglietti (widget, checkout, regalo, RTB) sono consentite ora.
     */
    public static function single_event_ticket_sales_open(int $experience_id): bool
    {
        return self::single_event_ticket_sales_blocked_error($experience_id) === null;
    }

    /**
     * True se l’esperienza è evento a data singola e l’orario di inizio è nel passato (timezone del sito).
     */
    public static function single_event_is_past(int $experience_id): bool
    {
        if ($experience_id <= 0) {
            return false;
        }

        if (! (bool) get_post_meta($experience_id, '_fp_is_event', true)) {
            return false;
        }

        $tz = wp_timezone();
        $start = self::parse_fp_experience_datetime_meta(
            (string) get_post_meta($experience_id, '_fp_event_datetime', true),
            $tz
        );

        if ($start === null) {
            return false;
        }

        $now = new DateTimeImmutable('now', $tz);

        return $now >= $start;
    }

    /**
     * Data/ora di fine vendite biglietti per evento a data singola, se meta valorizzata e parsabile.
     */
    public static function single_event_ticket_sales_end_datetime(int $experience_id): ?DateTimeImmutable
    {
        if ($experience_id <= 0) {
            return null;
        }

        if (! (bool) get_post_meta($experience_id, '_fp_is_event', true)) {
            return null;
        }

        $raw = trim(str_replace('T', ' ', (string) get_post_meta($experience_id, '_fp_event_ticket_sales_end', true)));
        if ($raw === '') {
            return null;
        }

        return self::parse_fp_experience_datetime_meta($raw, wp_timezone());
    }

    /**
     * Coerente col widget: almeno uno slot con tetto capienza > 0 e tutti con posti esauriti.
     *
     * @param array<int, array<string, mixed>> $slots
     */
    public static function slots_indicate_single_event_fully_booked(array $slots): bool
    {
        if ($slots === []) {
            return false;
        }

        $fully = true;

        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $cap_total = isset($slot['capacity_total']) ? (int) $slot['capacity_total'] : 0;

            if ($cap_total <= 0) {
                $fully = false;
                break;
            }

            $remaining = isset($slot['remaining']) ? (int) $slot['remaining'] : 0;

            if ($remaining > 0) {
                $fully = false;
                break;
            }
        }

        return $fully;
    }

    /**
     * Blocco vendite per esperienza impostata come evento a data singola: dopo l’orario di fine vendite (se impostato)
     * o da quando inizia l’evento.
     *
     * @return WP_Error|null Null se le vendite sono consentite.
     */
    public static function single_event_ticket_sales_blocked_error(int $experience_id): ?WP_Error
    {
        if ($experience_id <= 0) {
            return null;
        }

        $is_event = (bool) get_post_meta($experience_id, '_fp_is_event', true);
        if (! $is_event) {
            return null;
        }

        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);

        $event_start = self::parse_fp_experience_datetime_meta(
            (string) get_post_meta($experience_id, '_fp_event_datetime', true),
            $tz
        );
        if ($event_start !== null && $now >= $event_start) {
            return new WP_Error(
                'fp_exp_single_event_started',
                __('Le vendite per questo evento sono chiuse: la data dell’evento è passata.', 'fp-experiences'),
                ['status' => 400]
            );
        }

        $sales_end_raw = trim((string) get_post_meta($experience_id, '_fp_event_ticket_sales_end', true));
        if ($sales_end_raw !== '') {
            $sales_end = self::parse_fp_experience_datetime_meta($sales_end_raw, $tz);
            if ($sales_end !== null && $now >= $sales_end) {
                return new WP_Error(
                    'fp_exp_ticket_sales_deadline_passed',
                    __('Le vendite biglietti per questo evento sono chiuse.', 'fp-experiences'),
                    ['status' => 400]
                );
            }
        }

        return null;
    }

    /**
     * @param string $raw Valore meta tipo data/ora esperienza (es. Y-m-d H:i).
     */
    private static function parse_fp_experience_datetime_meta(string $raw, DateTimeZone $tz): ?DateTimeImmutable
    {
        $value = trim(str_replace('T', ' ', $raw));
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $value, $tz);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }

        $with_t = str_replace(' ', 'T', $value);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $with_t, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }

        $ts = strtotime($value);
        if (false !== $ts) {
            return (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
        }

        return null;
    }

    /**
     * Get a service from the container if available.
     *
     * @param string $service Service interface/class name
     * @return object|null Service instance or null if not available
     */
    private static function getService(string $service): ?object
    {
        try {
            $kernel = Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has($service)) {
                return null;
            }

            return $container->make($service);
        } catch (\Throwable $e) {
            // If container is not available, return null to use fallback
            return null;
        }
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
