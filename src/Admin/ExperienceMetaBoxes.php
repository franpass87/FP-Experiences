<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\CalendarMetaBoxHandler;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\DetailsMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\ExtrasMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\MeetingPointMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\PolicyMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\PricingMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\SEOMetaBoxHandler;
use FP_Exp\Booking\Recurrence;
use FP_Exp\Booking\Slots;
use FP_Exp\MeetingPoints\MeetingPointCPT;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\LanguageHelper;
use WP_Error;
use WP_Post;

use function absint;
use function add_action;
use function add_meta_box;
use function array_filter;
use function array_map;
use function array_unique;
use function array_merge;
use function array_values;
use function checked;
use function current_user_can;
use function delete_post_meta;
use function delete_transient;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function get_current_screen;
use function get_edit_post_link;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_post_status;
use function get_post_status_object;
use function get_transient;
use function get_post_thumbnail_id;
use function get_terms;
use function get_the_title;
use function in_array;
use function implode;
use function is_array;
use function is_wp_error;
use function ob_get_clean;
use function ob_start;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
use function selected;
use function set_transient;
use function strval;
use function sprintf;
use function update_post_meta;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_attachment_is_image;
use function rest_url;
use function wp_create_nonce;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;
use function wp_get_post_terms;
use function wp_kses_post;
use function wp_get_attachment_image_src;
use function wp_get_attachment_url;
use function wp_set_post_terms;
use function remove_meta_box;
use function term_exists;
use function wp_insert_term;
use function set_post_thumbnail;
use function delete_post_thumbnail;

final class ExperienceMetaBoxes implements HookableInterface
{
    private CalendarMetaBoxHandler $calendar_handler;
    private DetailsMetaBoxHandler $details_handler;
    private PolicyMetaBoxHandler $policy_handler;
    private PricingMetaBoxHandler $pricing_handler;
    private SEOMetaBoxHandler $seo_handler;
    private ExtrasMetaBoxHandler $extras_handler;
    private MeetingPointMetaBoxHandler $meeting_point_handler;

    /**
     * ExperienceMetaBoxes constructor.
     *
     * @param CalendarMetaBoxHandler|null $calendar_handler Optional (will be created if not provided)
     * @param DetailsMetaBoxHandler|null $details_handler Optional (will be created if not provided)
     * @param PolicyMetaBoxHandler|null $policy_handler Optional (will be created if not provided)
     * @param PricingMetaBoxHandler|null $pricing_handler Optional (will be created if not provided)
     * @param SEOMetaBoxHandler|null $seo_handler Optional (will be created if not provided)
     * @param ExtrasMetaBoxHandler|null $extras_handler Optional (will be created if not provided)
     * @param MeetingPointMetaBoxHandler|null $meeting_point_handler Optional (will be created if not provided)
     */
    public function __construct(
        ?CalendarMetaBoxHandler $calendar_handler = null,
        ?DetailsMetaBoxHandler $details_handler = null,
        ?PolicyMetaBoxHandler $policy_handler = null,
        ?PricingMetaBoxHandler $pricing_handler = null,
        ?SEOMetaBoxHandler $seo_handler = null,
        ?ExtrasMetaBoxHandler $extras_handler = null,
        ?MeetingPointMetaBoxHandler $meeting_point_handler = null
    ) {
        // Try to get handlers from container if not provided
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        $container = null;
        if ($kernel !== null) {
            $container = $kernel->container();
        }

        // Inject handlers or get from container or create defaults (backward compatibility)
        $this->calendar_handler = $calendar_handler 
            ?? ($container && $container->has(CalendarMetaBoxHandler::class) 
                ? $container->make(CalendarMetaBoxHandler::class) 
                : new CalendarMetaBoxHandler());
        
        $this->details_handler = $details_handler 
            ?? ($container && $container->has(DetailsMetaBoxHandler::class) 
                ? $container->make(DetailsMetaBoxHandler::class) 
                : new DetailsMetaBoxHandler());
        
        $this->policy_handler = $policy_handler 
            ?? ($container && $container->has(PolicyMetaBoxHandler::class) 
                ? $container->make(PolicyMetaBoxHandler::class) 
                : new PolicyMetaBoxHandler());
        
        $this->pricing_handler = $pricing_handler 
            ?? ($container && $container->has(PricingMetaBoxHandler::class) 
                ? $container->make(PricingMetaBoxHandler::class) 
                : new PricingMetaBoxHandler());
        
        $this->seo_handler = $seo_handler 
            ?? ($container && $container->has(SEOMetaBoxHandler::class) 
                ? $container->make(SEOMetaBoxHandler::class) 
                : new SEOMetaBoxHandler());
        
        $this->extras_handler = $extras_handler 
            ?? ($container && $container->has(ExtrasMetaBoxHandler::class) 
                ? $container->make(ExtrasMetaBoxHandler::class) 
                : new ExtrasMetaBoxHandler());
        
        $this->meeting_point_handler = $meeting_point_handler 
            ?? ($container && $container->has(MeetingPointMetaBoxHandler::class) 
                ? $container->make(MeetingPointMetaBoxHandler::class) 
                : new MeetingPointMetaBoxHandler());
    }

    private const TAB_LABELS = [
        'details' => 'Dettagli',
        'pricing' => 'Biglietti & Prezzi',
        'calendar' => 'Calendario & Slot',
        'meeting-point' => 'Meeting Point',
        'extras' => 'Extra',
        'policy' => 'Policy/FAQ',
        'seo' => 'SEO/Schema',
    ];

    /**
     * Verifica se un plugin SEO è attivo
     * 
     * @return bool True se un plugin SEO è attivo
     */
    private function is_seo_plugin_active(): bool
    {
        // Verifica FP SEO Manager / FP SEO Performance
        if (defined('FP_SEO_PERFORMANCE_FILE') || 
            defined('FP_SEO_PERFORMANCE_VERSION') ||
            class_exists('\FP\SEO\Infrastructure\Plugin') ||
            class_exists('\FP_SEO\Core\Bootstrap\Bootstrap') || 
            function_exists('fp_seo_performance_init') ||
            defined('FP_SEO_VERSION') ||
            class_exists('\FP_SEO_Performance\Core\Bootstrap')) {
            return true;
        }

        // Verifica Yoast SEO
        if (defined('WPSEO_VERSION') || 
            class_exists('WPSEO_Options') ||
            function_exists('yoast_breadcrumb') ||
            class_exists('WPSEO_Metabox')) {
            return true;
        }

        // Verifica Rank Math
        if (defined('RANK_MATH_VERSION') || 
            class_exists('RankMath') ||
            function_exists('rank_math') ||
            class_exists('RankMath\Admin\Admin')) {
            return true;
        }

        // Verifica All in One SEO
        if (defined('AIOSEO_VERSION') || 
            class_exists('AIOSEO\Plugin\Plugin') ||
            class_exists('AIOSEO')) {
            return true;
        }

        // Verifica SEOPress
        if (defined('SEOPRESS_VERSION') || 
            function_exists('seopress_get_service') ||
            class_exists('SEOPress')) {
            return true;
        }

        // Verifica The SEO Framework
        if (defined('THE_SEO_FRAMEWORK_VERSION') || 
            class_exists('The_SEO_Framework\Core') ||
            class_exists('The_SEO_Framework')) {
            return true;
        }

        return false;
    }

    /**
     * Get the source post ID for WPML translations.
     * 
     * When creating a new translation, returns the original post ID to pre-populate data.
     * Otherwise returns the current post ID.
     *
     * @param int $post_id Current post ID
     * @return int Source post ID (original or current)
     */
    private function get_wpml_source_post_id(int $post_id): int
    {
        // Check if WPML is active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return $post_id;
        }

        // Check if we're creating a new translation via URL parameters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $trid = isset($_GET['trid']) ? absint($_GET['trid']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended  
        $source_lang = isset($_GET['source_lang']) ? sanitize_text_field(wp_unslash($_GET['source_lang'])) : '';

        // If trid and source_lang are present, this is a new translation
        if ($trid > 0 && !empty($source_lang)) {
            global $sitepress;
            if ($sitepress) {
                // Get the original post from the translation group
                $translations = $sitepress->get_element_translations($trid, 'post_fp_experience');
                
                if (isset($translations[$source_lang])) {
                    $original_id = (int) $translations[$source_lang]->element_id;
                    if ($original_id > 0) {
                        return $original_id;
                    }
                }
            }
        }

        // Check if this post has empty meta (suggesting it needs data from original)
        $post = get_post($post_id);
        if ($post && $post->post_status === 'auto-draft') {
            global $sitepress;
            if ($sitepress) {
                $default_lang = $sitepress->get_default_language();
                $post_lang = $sitepress->get_language_for_element($post_id, 'post_fp_experience');
                
                // If this is not the default language, try to get original
                if ($post_lang && $post_lang !== $default_lang) {
                    $trid = $sitepress->get_element_trid($post_id, 'post_fp_experience');
                    if ($trid) {
                        $translations = $sitepress->get_element_translations($trid, 'post_fp_experience');
                        if (isset($translations[$default_lang])) {
                            return (int) $translations[$default_lang]->element_id;
                        }
                    }
                }
            }
        }

        return $post_id;
    }

    /**
     * Check if current post is a WPML translation and get its original post ID.
     *
     * @param int $post_id Current post ID
     * @return int|null Original post ID or null if not a translation
     */
    private function get_wpml_original_id(int $post_id): ?int
    {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return null;
        }

        global $sitepress;
        if (!$sitepress) {
            return null;
        }

        $default_lang = $sitepress->get_default_language();
        $post_lang = $sitepress->get_language_for_element($post_id, 'post_fp_experience');
        
        // If this is the default language post, it's the original
        if (!$post_lang || $post_lang === $default_lang) {
            return null;
        }

        // Get the original post from translation group
        $trid = $sitepress->get_element_trid($post_id, 'post_fp_experience');
        if (!$trid) {
            return null;
        }

        $translations = $sitepress->get_element_translations($trid, 'post_fp_experience');
        if (isset($translations[$default_lang])) {
            return (int) $translations[$default_lang]->element_id;
        }

        return null;
    }

    /**
     * Check if post has empty meta (needs sync from original).
     *
     * @param int $post_id Post ID to check
     * @return bool True if meta is empty
     */
    private function has_empty_meta(int $post_id): bool
    {
        $duration = get_post_meta($post_id, '_fp_duration_minutes', true);
        $base_price = get_post_meta($post_id, '_fp_base_price', true);
        $availability = get_post_meta($post_id, '_fp_exp_availability', true);
        
        return empty($duration) && empty($base_price) && empty($availability);
    }

    /**
     * Render sync button for existing translations with empty meta.
     *
     * @param int $post_id Current post ID
     * @param int $source_post_id Source post ID used for rendering
     */
    private function maybe_render_sync_button(int $post_id, int $source_post_id): void
    {
        // Only show if this is an existing translation (not new)
        $post = get_post($post_id);
        if (!$post || $post->post_status === 'auto-draft') {
            return;
        }

        // Check if this is a WPML translation
        $original_id = $this->get_wpml_original_id($post_id);
        if (!$original_id) {
            return;
        }

        // Check if meta is empty (needs sync)
        if (!$this->has_empty_meta($post_id)) {
            return;
        }

        // Get original post title for reference
        $original_title = get_the_title($original_id);
        ?>
        <div class="fp-exp-sync-notice" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
            <span class="dashicons dashicons-warning" style="color: #856404; font-size: 20px;"></span>
            <div style="flex: 1;">
                <strong style="color: #856404;"><?php esc_html_e('Meta dati non sincronizzati', 'fp-experiences'); ?></strong>
                <p style="margin: 4px 0 0; color: #856404; font-size: 13px;">
                    <?php 
                    printf(
                        /* translators: %s: Original post title */
                        esc_html__('Questa traduzione non ha i meta dati copiati dall\'originale "%s". Clicca per sincronizzare.', 'fp-experiences'),
                        esc_html($original_title)
                    );
                    ?>
                </p>
            </div>
            <button type="button" class="button button-primary" id="fp-exp-sync-meta-btn" data-post-id="<?php echo esc_attr($post_id); ?>" data-original-id="<?php echo esc_attr($original_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('fp_exp_sync_meta')); ?>">
                <span class="dashicons dashicons-update" style="margin-right: 4px;"></span>
                <?php esc_html_e('Sincronizza meta', 'fp-experiences'); ?>
            </button>
        </div>
        <script>
        jQuery(function($) {
            $('#fp-exp-sync-meta-btn').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; margin-right: 4px;"></span> <?php echo esc_js(__('Sincronizzazione...', 'fp-experiences')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fp_exp_sync_meta_from_original',
                        post_id: $btn.data('post-id'),
                        original_id: $btn.data('original-id'),
                        nonce: $btn.data('nonce')
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload page to show updated meta
                            location.reload();
                        } else {
                            alert(response.data || '<?php echo esc_js(__('Errore durante la sincronizzazione', 'fp-experiences')); ?>');
                            $btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Errore di connessione', 'fp-experiences')); ?>');
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
        </script>
        <style>
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        </style>
        <?php
    }

    /**
     * AJAX handler to sync meta from original translation.
     */
    public function ajax_sync_meta_from_original(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_exp_sync_meta')) {
            wp_send_json_error(__('Nonce non valido', 'fp-experiences'));
        }

        // Get post IDs
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $original_id = isset($_POST['original_id']) ? absint($_POST['original_id']) : 0;

        if (!$post_id || !$original_id) {
            wp_send_json_error(__('ID post mancanti', 'fp-experiences'));
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Permessi insufficienti', 'fp-experiences'));
        }

        // Meta keys to sync
        $meta_keys = [
            '_fp_base_price',
            '_fp_pricing_rules',
            '_fp_exp_pricing',
            '_fp_ticket_types',
            '_fp_addons',
            '_fp_exp_availability',
            '_fp_schedule_rules',
            '_fp_schedule_exceptions',
            '_fp_duration_minutes',
            '_fp_lead_time_hours',
            '_fp_buffer_before_minutes',
            '_fp_buffer_after_minutes',
            '_fp_min_party',
            '_fp_capacity_slot',
            '_fp_resources',
            '_fp_age_min',
            '_fp_age_max',
            '_fp_meeting_point_id',
            '_fp_meeting_point_alt',
            '_fp_meeting_point',
            '_fp_gallery_ids',
            '_fp_gallery_video_url',
            '_fp_hero_image_id',
            '_thumbnail_id',
            '_fp_use_rtb',
            '_fp_languages',
            '_fp_short_desc',
            '_fp_highlights',
            '_fp_included',
            '_fp_excluded',
            '_fp_what_to_bring',
            '_fp_additional_notes',
            '_fp_cancellation_policy',
            '_fp_faq',
        ];

        $synced = 0;
        foreach ($meta_keys as $key) {
            $value = get_post_meta($original_id, $key, true);
            if ($value !== '' && $value !== null && $value !== false) {
                update_post_meta($post_id, $key, $value);
                $synced++;
            }
        }

        // Also sync thumbnail if present
        $thumbnail_id = get_post_thumbnail_id($original_id);
        if ($thumbnail_id) {
            set_post_thumbnail($post_id, $thumbnail_id);
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: Number of synced fields */
                __('Sincronizzati %d campi meta', 'fp-experiences'),
                $synced
            ),
            'synced' => $synced,
        ]);
    }

    private const PRICING_NOTICE_KEY = 'fp_exp_pricing_notice_';

    public function register_hooks(): void
    {
        add_action('add_meta_boxes_fp_experience', [$this, 'add_meta_box']);
        add_action('add_meta_boxes', [$this, 'remove_default_meta_boxes'], 99);
        add_action('save_post_fp_experience', [$this, 'save_meta_boxes'], 20, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'maybe_show_pricing_notice']);
        // AJAX action for syncing meta from original translation
        add_action('wp_ajax_fp_exp_sync_meta_from_original', [$this, 'ajax_sync_meta_from_original']);
    }

    public function remove_default_meta_boxes(): void
    {
        remove_meta_box('tagsdiv-fp_exp_language', 'fp_experience', 'side');
        remove_meta_box('postimagediv', 'fp_experience', 'side');
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'fp-exp-experience-admin',
            esc_html__('Impostazioni esperienza', 'fp-experiences'),
            [$this, 'render_meta_box'],
            'fp_experience',
            'normal',
            'high' // Priorità alta - il riordino viene gestito via JavaScript
        );
    }


    public function enqueue_assets(string $hook_suffix): void
    {
        if (! in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || 'fp_experience' !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);
        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['jquery'],
            Helpers::asset_version($admin_js),
            true
        );

        // Config base per fpExpAdmin
        wp_localize_script('fp-exp-admin', 'fpExpAdmin', [
            'restUrl' => rest_url('fp-exp/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pluginUrl' => FP_EXP_PLUGIN_URL,
            'strings' => [],
        ]);

        $post_id = isset($_GET['post']) ? absint((string) $_GET['post']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        wp_localize_script(
            'fp-exp-admin',
            'fpExpAdmin',
            [
                'strings' => [
                    'tablistLabel' => esc_html__('Sezioni esperienza', 'fp-experiences'),
                    'removeRow' => esc_html__('Rimuovi elemento', 'fp-experiences'),
                    'ticketWarning' => esc_html__('Aggiungi almeno un tipo di biglietto con un prezzo valido.', 'fp-experiences'),
                    'invalidPrice' => esc_html__('Il prezzo non può essere negativo.', 'fp-experiences'),
                    'invalidQuantity' => esc_html__('La quantità non può essere negativa.', 'fp-experiences'),
                    'selectImage' => esc_html__('Seleziona immagine', 'fp-experiences'),
                    'changeImage' => esc_html__('Modifica immagine', 'fp-experiences'),
                    'removeImage' => esc_html__('Rimuovi immagine', 'fp-experiences'),
                    'recurrenceMissingTimes' => esc_html__('Aggiungi almeno un orario alla ricorrenza prima di procedere.', 'fp-experiences'),
                    'recurrenceMissingDays' => esc_html__('Seleziona almeno un giorno della settimana per la ricorrenza.', 'fp-experiences'),
                    'recurrencePreviewError' => esc_html__('Impossibile calcolare la ricorrenza: verifica date e orari.', 'fp-experiences'),
                    'recurrencePreviewEmpty' => esc_html__('Nessuno slot futuro trovato per la regola indicata.', 'fp-experiences'),
                    'recurrenceGenerateSuccess' => esc_html__('Slot rigenerati: %d creati/aggiornati.', 'fp-experiences'),
                    'recurrenceGenerateError' => esc_html__('Errore durante la rigenerazione degli slot. Riprova più tardi.', 'fp-experiences'),
                    'recurrencePostMissing' => esc_html__('Salva l\'esperienza prima di generare gli slot.', 'fp-experiences'),
                    'recurrenceTimeLabel' => esc_html__('Orario ricorrenza', 'fp-experiences'),
                    'recurrenceRemoveTime' => esc_html__('Rimuovi orario', 'fp-experiences'),
                    'recurrenceLoading' => esc_html__('Generazione in corso…', 'fp-experiences'),
                    'recurrenceOpenEndedSuffix' => esc_html__('La ricorrenza resta attiva finché non imposti una data di fine.', 'fp-experiences'),
                    'trustBadgesStatus' => esc_html__('Badge selezionati: %1$s su %2$s', 'fp-experiences'),
                    'trustBadgesMax' => esc_html__('Hai raggiunto il numero massimo di badge selezionabili.', 'fp-experiences'),
                ],
                'rest' => [
                    'nonce' => wp_create_nonce('wp_rest'),
                    'preview' => rest_url('fp-exp/v1/calendar/recurrence/preview'),
                    'generate' => rest_url('fp-exp/v1/calendar/recurrence/generate'),
                ],
                'experienceId' => $post_id,
            ]
        );

        // Fix: Rimuovi la classe hide-if-js e riordina la metabox sopra SEO Performance
        wp_add_inline_script(
            'fp-exp-admin',
            'jQuery(document).ready(function($) {
                function fixMetabox() {
                    var metabox = $("#fp-exp-experience-admin");
                    if (metabox.length) {
                        // Rimuovi la classe hide-if-js se presente
                        if (metabox.hasClass("hide-if-js")) {
                            metabox.removeClass("hide-if-js").show();
                        }
                        
                        // Sposta la metabox sopra SEO Performance
                        var seoMetabox = $("#fp-seo-performance-metabox");
                        if (seoMetabox.length && metabox.length) {
                            // Verifica che non sia già nella posizione corretta
                            var metaboxParent = metabox.parent();
                            var seoParent = seoMetabox.parent();
                            if (metaboxParent.is(seoParent) && metabox.next().is(seoMetabox)) {
                                // Già nella posizione corretta
                                return;
                            }
                            seoMetabox.before(metabox);
                        }
                    } else {
                        // Se la metabox non esiste ancora, riprova dopo un breve delay
                        setTimeout(fixMetabox, 100);
                    }
                }
                
                // Esegui immediatamente e anche dopo un breve delay per sicurezza
                fixMetabox();
                setTimeout(fixMetabox, 500);
                
                // Inizializza i tooltip con data-tooltip
                function initTooltips() {
                    $(".fp-exp-tooltip[data-tooltip]").each(function() {
                        var $tooltip = $(this);
                        var tooltipText = $tooltip.attr("data-tooltip");
                        
                        // Se non esiste già un elemento content, crealo
                        if (!$tooltip.next(".fp-exp-tooltip__content").length) {
                            var $content = $("<span>")
                                .addClass("fp-exp-tooltip__content")
                                .attr("role", "tooltip")
                                .attr("aria-hidden", "true")
                                .text(tooltipText);
                            $tooltip.after($content);
                        }
                    });
                }
                
                // Gestisci hover/focus per i tooltip
                function setupTooltipEvents() {
                    // Rimuovi eventi esistenti
                    $(document).off("mouseenter.fpExpTooltip focus.fpExpTooltip", ".fp-exp-tooltip[data-tooltip]");
                    $(document).off("mouseleave.fpExpTooltip blur.fpExpTooltip", ".fp-exp-tooltip[data-tooltip]");
                    
                    // Usa namespace per evitare conflitti
                    $(document).on("mouseenter.fpExpTooltip focus.fpExpTooltip", ".fp-exp-tooltip[data-tooltip]", function(e) {
                        var $tooltip = $(this);
                        var $content = $tooltip.next(".fp-exp-tooltip__content");
                        if ($content.length) {
                            $content.attr("aria-hidden", "false").css("display", "block");
                        }
                    });
                    
                    $(document).on("mouseleave.fpExpTooltip blur.fpExpTooltip", ".fp-exp-tooltip[data-tooltip]", function(e) {
                        var $tooltip = $(this);
                        var $content = $tooltip.next(".fp-exp-tooltip__content");
                        if ($content.length) {
                            $content.attr("aria-hidden", "true").css("display", "none");
                        }
                    });
                }
                
                // Inizializza i tooltip
                initTooltips();
                setupTooltipEvents();
                
                // Reinizializza quando vengono aggiunti nuovi elementi (es. repeater)
                var observer = new MutationObserver(function(mutations) {
                    var needsInit = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    if ($(node).find(".fp-exp-tooltip[data-tooltip]").length || $(node).is(".fp-exp-tooltip[data-tooltip]")) {
                                        needsInit = true;
                                    }
                                }
                            });
                        }
                    });
                    if (needsInit) {
                        initTooltips();
                        setupTooltipEvents();
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        // For WPML translations, pre-populate from original post if this is a new translation
        $source_post_id = $this->get_wpml_source_post_id($post->ID);
        
        // Use DetailsMetaBoxHandler for Details tab
        $details = $this->details_handler->get($source_post_id);
        // Use PricingMetaBoxHandler for Pricing tab
        $pricing = $this->pricing_handler->get($source_post_id);
        // Use CalendarMetaBoxHandler for Calendar tab
        $availability = $this->calendar_handler->get($source_post_id);
        // Use MeetingPointMetaBoxHandler for Meeting Point tab
        $meeting_data = $this->meeting_point_handler->get($source_post_id);
        $meeting = [
            'primary' => $meeting_data['primary'] ?? 0,
            'alternatives' => $meeting_data['alternatives'] ?? [],
        ];
        $meeting_choices = $meeting_data['choices'] ?? [];
        // Use ExtrasMetaBoxHandler for Extras tab
        $extras = $this->extras_handler->get($source_post_id);
        // Use PolicyMetaBoxHandler for Policy tab
        $policy = $this->policy_handler->get($source_post_id);
        // Use SEOMetaBoxHandler for SEO tab
        $seo = $this->seo_handler->get($source_post_id);

        wp_nonce_field('fp_exp_meta_nonce', 'fp_exp_meta_nonce');
        
        // Show sync button for existing WPML translations with empty meta
        $this->maybe_render_sync_button($post->ID, $source_post_id);
        ?>
        <div class="fp-exp-admin" data-fp-exp-admin>
            <div class="fp-exp-tabs" role="tablist" aria-label="<?php echo esc_attr(esc_html__('Sezioni esperienza', 'fp-experiences')); ?>">
                <?php 
                $seo_plugin_active = $this->is_seo_plugin_active();
                foreach (self::TAB_LABELS as $slug => $label) : 
                    // Nascondi il tab SEO se un plugin SEO è attivo
                    if ($slug === 'seo' && $seo_plugin_active) {
                        continue;
                    }
                    $tab_id = 'fp-exp-tab-' . $slug; 
                ?>
                    <button
                        type="button"
                        class="fp-exp-tab"
                        role="tab"
                        id="<?php echo esc_attr($tab_id); ?>"
                        aria-controls="<?php echo esc_attr($tab_id . '-panel'); ?>"
                        aria-selected="<?php echo 'details' === $slug ? 'true' : 'false'; ?>"
                        data-tab="<?php echo esc_attr($slug); ?>"
                    >
                        <?php echo esc_html__($label, 'fp-experiences'); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="fp-exp-tab-panels">
                <?php 
                // Use DetailsMetaBoxHandler for Details tab
                $this->details_handler->render($details, (int) $post->ID); 
                ?>
                <?php 
                // Use PricingMetaBoxHandler for Pricing tab
                $this->pricing_handler->render($pricing, (int) $post->ID); 
                ?>
                <?php 
                // Use CalendarMetaBoxHandler for Calendar tab
                $this->calendar_handler->render($availability, (int) $post->ID); 
                ?>
                <?php 
                // Use MeetingPointMetaBoxHandler for Meeting Point tab
                $meeting_data = array_merge($meeting, ['choices' => $meeting_choices]);
                $this->meeting_point_handler->render($meeting_data, (int) $post->ID); 
                ?>
                <?php 
                // Use ExtrasMetaBoxHandler for Extras tab
                $this->extras_handler->render($extras, (int) $post->ID); 
                ?>
                <?php 
                // Use PolicyMetaBoxHandler for Policy tab
                $this->policy_handler->render($policy, (int) $post->ID); 
                ?>
                <?php 
                // Use SEOMetaBoxHandler for SEO tab - solo se nessun plugin SEO è attivo
                if (!$seo_plugin_active) {
                    $this->seo_handler->render($seo, (int) $post->ID);
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes(int $post_id, WP_Post $post, bool $update): void
    {
        unset($post, $update);

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (! isset($_POST['fp_exp_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST['fp_exp_meta_nonce']));
        if (! wp_verify_nonce($nonce, 'fp_exp_meta_nonce')) {
            return;
        }

        $raw = wp_unslash($_POST);

        // Protezione contro output non intenzionale che causa corruzione dati 'Array'
        ob_start();

        // Use DetailsMetaBoxHandler for Details tab
        $this->details_handler->save($post_id, $raw['fp_exp_details'] ?? []);
        // Use PricingMetaBoxHandler for Pricing tab
        $this->pricing_handler->save($post_id, $raw['fp_exp_pricing'] ?? []);
        // Use CalendarMetaBoxHandler for Calendar tab
        $this->calendar_handler->save($post_id, $raw['fp_exp_availability'] ?? []);
        // Use MeetingPointMetaBoxHandler for Meeting Point tab
        $this->meeting_point_handler->save($post_id, $raw['fp_exp_meeting_point'] ?? []);
        // Use ExtrasMetaBoxHandler for Extras tab
        $this->extras_handler->save($post_id, $raw['fp_exp_extras'] ?? []);
        // Use PolicyMetaBoxHandler for Policy tab
        $this->policy_handler->save($post_id, $raw['fp_exp_policy'] ?? []);
        // Use SEOMetaBoxHandler for SEO tab
        $this->seo_handler->save($post_id, $raw['fp_exp_seo'] ?? []);

        // Cattura e scarta qualsiasi output non intenzionale
        $unwanted_output = ob_get_clean();
        if (! empty($unwanted_output) && (defined('WP_DEBUG') && WP_DEBUG)) {
            error_log('FP_EXP: Output non intenzionale durante salvataggio metadati: ' . substr($unwanted_output, 0, 200));
        }

        // Generate recurrence slots only if we have valid recurrence data from the form
        $availability_data = $raw['fp_exp_availability'] ?? [];
        if ('publish' === get_post_status($post_id) && !empty($availability_data) && is_array($availability_data)) {
            $this->maybe_generate_recurrence_slots($post_id, $availability_data);
        }
    }

    public function maybe_show_pricing_notice(): void
    {
        $screen = get_current_screen();
        if (! $screen || 'post' !== $screen->base || 'fp_experience' !== $screen->post_type) {
            return;
        }

        $post_id = isset($_GET['post']) ? absint((string) $_GET['post']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! $post_id) {
            return;
        }

        $notice = get_transient(self::PRICING_NOTICE_KEY . $post_id);
        if ($notice) {
            delete_transient(self::PRICING_NOTICE_KEY . $post_id);

            if ('success' === $notice) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('✔ Prezzi salvati', 'fp-experiences') . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } elseif ('warning' === $notice) {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('⚠ Manca almeno un tipo biglietto', 'fp-experiences') . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }

        $post = get_post($post_id);
        if (! $post || 'publish' !== $post->post_status) {
            return;
        }

        $pricing = get_post_meta($post_id, '_fp_exp_pricing', true);
        if (! is_array($pricing) || ! $this->has_pricing($pricing)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Questa esperienza è pubblicata senza prezzi configurati. Aggiungi almeno un prezzo prima di accettare prenotazioni.', 'fp-experiences') . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
    /**
     * @deprecated Use DetailsMetaBoxHandler::render() instead
     * @param array<string, mixed> $details
     */
    private function render_details_tab(array $details, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-details-panel';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-details"
            data-tab-panel="details"
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Informazioni generali', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-short-desc">
                        <?php esc_html_e('Descrizione breve', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-short-desc-help', esc_html__('Testo sintetico mostrato in anteprima e nei widget.', 'fp-experiences')); ?>
                    </label>
                    <textarea
                        id="fp-exp-short-desc"
                        name="fp_exp_details[short_desc]"
                        rows="3"
                        placeholder="<?php echo esc_attr__('Es. Visita guidata alla Galleria degli Uffizi', 'fp-experiences'); ?>"
                        aria-describedby="fp-exp-short-desc-help"
                    ><?php echo esc_textarea((string) $details['short_desc']); ?></textarea>
                    <p class="fp-exp-field__description" id="fp-exp-short-desc-help"><?php esc_html_e('Suggerito massimo 160 caratteri.', 'fp-experiences'); ?></p>
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-duration">
                            <?php esc_html_e('Durata (minuti)', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-duration-help', esc_html__("Durata media dell'esperienza utilizzata anche nello schema.", 'fp-experiences')); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-duration"
                            name="fp_exp_details[duration_minutes]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $details['duration_minutes']); ?>"
                            aria-describedby="fp-exp-duration-help"
                        />
                        <p class="fp-exp-field__description" id="fp-exp-duration-help"><?php esc_html_e('Inserisci solo numeri interi.', 'fp-experiences'); ?></p>
                    </div>
                    <?php
                    $language_details = isset($details['languages']) && is_array($details['languages']) ? $details['languages'] : [];
                    $language_choices = isset($language_details['choices']) && is_array($language_details['choices']) ? $language_details['choices'] : [];
                    $language_selected = isset($language_details['selected']) && is_array($language_details['selected']) ? $language_details['selected'] : [];
                    $language_badges = isset($details['language_badges']) && is_array($details['language_badges']) ? $details['language_badges'] : [];
                    ?>
                    <div>
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Lingue disponibili', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-language-badge-help', esc_html__("Seleziona le lingue parlate durante l'esperienza: verranno mostrate nei badge pubblici e nel widget di prenotazione.", 'fp-experiences')); ?>
                        </span>
                        <?php if (! empty($language_choices)) : ?>
                            <div class="fp-exp-checkbox-grid" aria-describedby="fp-exp-language-badge-help">
                                <?php foreach ($language_choices as $choice) :
                                    if (! is_array($choice)) {
                                        continue;
                                    }

                                    $term_id = isset($choice['id']) ? (int) $choice['id'] : 0;
                                    $label = isset($choice['label']) ? (string) $choice['label'] : '';

                                    if ($term_id <= 0 || '' === $label) {
                                        continue;
                                    }
                                    ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="fp_exp_details[languages][]"
                                            value="<?php echo esc_attr((string) $term_id); ?>"
                                            <?php checked(in_array($term_id, $language_selected, true)); ?>
                                        />
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Non hai ancora creato lingue. Aggiungi nuove voci qui sotto per iniziare.', 'fp-experiences'); ?></p>
                        <?php endif; ?>
                        <div class="fp-exp-taxonomy-manual">
                            <label class="fp-exp-taxonomy-manual__label" for="fp-exp-languages-manual"><?php esc_html_e('Aggiungi nuove lingue', 'fp-experiences'); ?></label>
                            <input
                                type="text"
                                id="fp-exp-languages-manual"
                                name="fp_exp_details[languages_manual]"
                                class="regular-text"
                                placeholder="<?php echo esc_attr__('Es. Italiano, English, Deutsch', 'fp-experiences'); ?>"
                                autocomplete="off"
                            />
                            <p class="fp-exp-field__description"><?php esc_html_e('Separa le voci con una virgola: verranno create come termini e selezionate automaticamente.', 'fp-experiences'); ?></p>
                        </div>
                        <?php if (! empty($language_badges)) : ?>
                            <ul class="fp-exp-language-preview" role="list" aria-describedby="fp-exp-language-badge-help">
                                <?php foreach ($language_badges as $language) :
                                    if (! is_array($language)) {
                                        continue;
                                    }

                                    $sprite_id = isset($language['sprite']) ? (string) $language['sprite'] : '';
                                    $code = isset($language['code']) ? (string) $language['code'] : '';
                                    $aria_label = isset($language['aria_label']) ? (string) $language['aria_label'] : $code;
                                    $label = isset($language['label']) ? (string) $language['label'] : $code;

                                    if ('' === $code) {
                                        continue;
                                    }
                                    ?>
                                    <li class="fp-exp-language-preview__item">
                                        <?php if ($sprite_id) : ?>
                                            <span class="fp-exp-language-preview__flag" role="img" aria-label="<?php echo esc_attr($aria_label); ?>">
                                                <svg viewBox="0 0 24 16" aria-hidden="true" focusable="false">
                                                    <use href="<?php echo esc_url(LanguageHelper::get_sprite_url() . '#' . $sprite_id); ?>"></use>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                        <span class="fp-exp-language-preview__code" aria-hidden="true"><?php echo esc_html($code); ?></span>
                                        <span class="screen-reader-text"><?php echo esc_html($label); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Nessuna lingua selezionata al momento.', 'fp-experiences'); ?></p>
                        <?php endif; ?>
                        <p class="fp-exp-field__description" id="fp-exp-language-badge-help"><?php esc_html_e('Le lingue selezionate vengono mostrate nei badge pubblici, nel widget e nei filtri.', 'fp-experiences'); ?></p>
                    </div>
                </div>

                <?php
                $hero_image = $details['hero_image'];
                $hero_id = isset($hero_image['id']) ? (int) $hero_image['id'] : 0;
                $hero_url = isset($hero_image['url']) ? (string) $hero_image['url'] : '';
                $hero_width = isset($hero_image['width']) ? (int) $hero_image['width'] : 0;
                $hero_height = isset($hero_image['height']) ? (int) $hero_image['height'] : 0;
                ?>
                <div class="fp-exp-field">
                    <span class="fp-exp-field__label">
                        <?php esc_html_e('Immagine hero', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-hero-image-help', esc_html__('Seleziona l\'immagine principale mostrata come hero a tutta larghezza nella pagina esperienza.', 'fp-experiences')); ?>
                    </span>
                    <div class="fp-exp-cover-media" data-fp-media-control>
                        <input
                            type="hidden"
                            id="fp-exp-hero-image"
                            name="fp_exp_details[hero_image_id]"
                            value="<?php echo esc_attr((string) $hero_id); ?>"
                            data-fp-media-input
                        />
                        <div class="fp-exp-cover-media__preview" data-fp-media-preview>
                            <div class="fp-exp-cover-media__placeholder" data-fp-media-placeholder <?php echo $hero_url ? 'hidden' : ''; ?>>
                                <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                                    <rect x="1" y="1" width="46" height="30" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2" />
                                    <path d="M16 12a4 4 0 1 1 4 4 4 4 0 0 1-4-4Zm-6 14 8-10 6 7 4-5 8 8Z" fill="currentColor" />
                                </svg>
                                <span class="screen-reader-text"><?php esc_html_e('Nessuna immagine selezionata', 'fp-experiences'); ?></span>
                            </div>
                            <?php if ($hero_url) : ?>
                                <img
                                    src="<?php echo esc_url($hero_url); ?>"
                                    alt=""
                                    <?php if ($hero_width > 0) : ?>width="<?php echo esc_attr((string) $hero_width); ?>"<?php endif; ?>
                                    <?php if ($hero_height > 0) : ?>height="<?php echo esc_attr((string) $hero_height); ?>"<?php endif; ?>
                                    loading="lazy"
                                    data-fp-media-image
                                />
                            <?php endif; ?>
                        </div>
                        <div class="fp-exp-cover-media__actions">
                            <button
                                type="button"
                                class="button button-secondary"
                                data-fp-media-choose
                                data-label-select="<?php echo esc_attr__('Seleziona immagine', 'fp-experiences'); ?>"
                                data-label-change="<?php echo esc_attr__('Modifica immagine', 'fp-experiences'); ?>"
                            >
                                <?php echo $hero_url ? esc_html__('Modifica immagine', 'fp-experiences') : esc_html__('Seleziona immagine', 'fp-experiences'); ?>
                            </button>
                            <button
                                type="button"
                                class="button-link"
                                data-fp-media-remove
                                <?php echo $hero_url ? '' : ' hidden'; ?>
                            >
                                <?php esc_html_e('Rimuovi immagine', 'fp-experiences'); ?>
                            </button>
                        </div>
                    </div>
                    <p class="fp-exp-field__description" id="fp-exp-hero-image-help"><?php esc_html_e('Consigliata proporzione 16:9 con soggetti centrati.', 'fp-experiences'); ?></p>
                </div>

                <?php
                $gallery_details = $details['gallery'];
                $gallery_items = [];
                $gallery_ids = [];

                if (isset($gallery_details['items']) && is_array($gallery_details['items'])) {
                    $gallery_items = $gallery_details['items'];
                }

                if (isset($gallery_details['ids']) && is_array($gallery_details['ids'])) {
                    $gallery_ids = array_values(array_filter(array_map('absint', $gallery_details['ids'])));
                } elseif (! empty($gallery_items)) {
                    foreach ($gallery_items as $gallery_item) {
                        if (! is_array($gallery_item)) {
                            continue;
                        }

                        $candidate_id = isset($gallery_item['id']) ? absint((string) $gallery_item['id']) : 0;
                        if ($candidate_id > 0) {
                            $gallery_ids[] = $candidate_id;
                        }
                    }
                }

                $gallery_ids = array_values(array_unique(array_filter($gallery_ids)));
                $gallery_value = implode(',', array_map('strval', $gallery_ids));
                ?>
                <div class="fp-exp-field">
                    <span class="fp-exp-field__label">
                        <?php esc_html_e('Galleria immagini', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-gallery-help', esc_html__('Seleziona e ordina le immagini da mostrare nella galleria della pagina esperienza.', 'fp-experiences')); ?>
                    </span>
                    <div class="fp-exp-gallery-control" data-fp-gallery-control>
                        <input
                            type="hidden"
                            name="fp_exp_details[gallery_ids]"
                            value="<?php echo esc_attr($gallery_value); ?>"
                            data-fp-gallery-input
                        />
                        <template data-fp-gallery-item-template>
                            <?php $this->render_gallery_item([], true); ?>
                        </template>
                        <ul class="fp-exp-gallery-control__list" data-fp-gallery-list role="list">
                            <?php foreach ($gallery_items as $gallery_item) :
                                if (! is_array($gallery_item)) {
                                    continue;
                                }
                                $this->render_gallery_item($gallery_item);
                            endforeach; ?>
                        </ul>
                        <p class="fp-exp-gallery-control__empty" data-fp-gallery-empty <?php echo empty($gallery_items) ? '' : ' hidden'; ?>>
                            <?php esc_html_e('Nessuna immagine selezionata al momento.', 'fp-experiences'); ?>
                        </p>
                        <div class="fp-exp-gallery-control__actions">
                            <button
                                type="button"
                                class="button button-secondary"
                                data-fp-gallery-add
                                data-label-select="<?php echo esc_attr__('Seleziona immagini', 'fp-experiences'); ?>"
                                data-label-update="<?php echo esc_attr__('Aggiungi altre immagini', 'fp-experiences'); ?>"
                            >
                                <?php echo empty($gallery_items) ? esc_html__('Seleziona immagini', 'fp-experiences') : esc_html__('Aggiungi altre immagini', 'fp-experiences'); ?>
                            </button>
                            <button
                                type="button"
                                class="button-link"
                                data-fp-gallery-clear
                                data-label-clear="<?php echo esc_attr__('Rimuovi tutte', 'fp-experiences'); ?>"
                                <?php echo empty($gallery_items) ? ' hidden' : ''; ?>
                            >
                                <?php esc_html_e('Rimuovi tutte', 'fp-experiences'); ?>
                            </button>
                        </div>
                    </div>
					<p class="fp-exp-field__description" id="fp-exp-gallery-help"><?php esc_html_e("Le immagini vengono mostrate nella galleria pubblica seguendo l'ordine impostato qui sopra.", 'fp-experiences'); ?></p>
                </div>

                <?php
                $gallery_video_url = isset($details['gallery_video_url']) ? (string) $details['gallery_video_url'] : '';
                ?>
                <div class="fp-exp-field">
                    <label for="fp-exp-gallery-video-url" class="fp-exp-field__label">
                        <?php esc_html_e('Video YouTube della galleria', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-gallery-video-help', esc_html__('Inserisci l\'URL di un video YouTube da mostrare nella sezione "Uno sguardo all\'esperienza". Il video partirà automaticamente. Es: https://www.youtube.com/watch?v=ABC123', 'fp-experiences')); ?>
                    </label>
                    <input
                        type="url"
                        id="fp-exp-gallery-video-url"
                        name="fp_exp_details[gallery_video_url]"
                        value="<?php echo esc_attr($gallery_video_url); ?>"
                        placeholder="https://www.youtube.com/watch?v=..."
                        class="regular-text"
                        aria-describedby="fp-exp-gallery-video-help"
                    />
                    <p class="fp-exp-field__description" id="fp-exp-gallery-video-help">
                        <?php esc_html_e('Il video YouTube verrà mostrato prima della galleria di immagini e partirà automaticamente con audio disattivato.', 'fp-experiences'); ?>
                    </p>
                </div>

                <div class="fp-exp-field fp-exp-field--taxonomies">
                    <div class="fp-exp-field">
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Badge esperienza', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-experience-badges-help', esc_html__('Aggiungi i badge per questa esperienza compilando i campi sottostanti. I badge inseriti verranno mostrati nella pagina esperienza, nelle liste e nei badge rapidi. Se compili almeno un campo (titolo o descrizione), il badge verrà visualizzato automaticamente.', 'fp-experiences')); ?>
                        </span>
                        
                        <?php
                        // Recupera i badge esistenti
                        $custom_badges_existing = get_post_meta($post_id, '_fp_experience_badge_custom', true);
                        $custom_badges_existing = is_array($custom_badges_existing) ? $custom_badges_existing : [];
                        ?>
                        <div class="fp-exp-taxonomy-editor fp-exp-taxonomy-editor--compact" aria-describedby="fp-exp-experience-badges-help">
                            <div class="fp-exp-taxonomy-editor__list">
                                <?php
                                $badge_index = 0;
                                foreach ($custom_badges_existing as $entry) :
                                    $cid = sanitize_key((string) ($entry['id'] ?? ''));
                                    $clabel = sanitize_text_field((string) ($entry['label'] ?? ''));
                                    $cdesc = sanitize_text_field((string) ($entry['description'] ?? ''));
                                    ?>
                                    <div class="fp-exp-taxonomy-editor__item">
                                        <input type="hidden" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][id]" value="<?php echo esc_attr($cid); ?>" />
                                        <label class="fp-exp-taxonomy-editor__field">
                                            <span class="fp-exp-field__label"><?php esc_html_e('Titolo badge', 'fp-experiences'); ?></span>
                                            <input type="text" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][label]" value="<?php echo esc_attr($clabel); ?>" />
                                        </label>
                                        <label class="fp-exp-taxonomy-editor__field">
                                            <span class="fp-exp-field__label"><?php esc_html_e('Descrizione badge', 'fp-experiences'); ?></span>
                                            <textarea rows="2" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][description]"><?php echo esc_textarea($cdesc); ?></textarea>
                                        </label>
                                    </div>
                                <?php
                                    $badge_index++;
                                endforeach;
                                ?>
                                <?php for ($i = 0; $i < 6; $i++) : ?>
                                    <div class="fp-exp-taxonomy-editor__item">
                                        <input type="hidden" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][id]" value="" />
                                        <label class="fp-exp-taxonomy-editor__field">
                                            <span class="fp-exp-field__label"><?php esc_html_e('Titolo badge', 'fp-experiences'); ?></span>
                                            <input type="text" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][label]" value="" />
                                        </label>
                                        <label class="fp-exp-taxonomy-editor__field">
                                            <span class="fp-exp-field__label"><?php esc_html_e('Descrizione badge', 'fp-experiences'); ?></span>
                                            <textarea rows="2" name="fp_exp_details[experience_badges_custom][<?php echo $badge_index; ?>][description]"></textarea>
                                        </label>
                                    </div>
                                <?php
                                    $badge_index++;
                                endfor;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fp-exp-field">
                    <span class="fp-exp-field__label">
                        <?php esc_html_e('Badge di fiducia', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-bias-help', esc_html__('Evidenzia le leve persuasive che caratterizzano l\'esperienza; vengono mostrate nella panoramica.', 'fp-experiences')); ?>
                    </span>
                    <?php
                    $max_biases = Helpers::cognitive_bias_max_selection();
                    $selected_biases = isset($details['cognitive_biases']['selected']) && is_array($details['cognitive_biases']['selected'])
                        ? array_values(array_filter(array_map('strval', $details['cognitive_biases']['selected'])))
                        : [];
                    $selected_bias_count = count($selected_biases);
                    $status_template = __('Badge selezionati: %1$s su %2$s', 'fp-experiences');
                    $status_limit_message = __('Hai raggiunto il numero massimo di badge selezionabili.', 'fp-experiences');
                    $search_input_id = 'fp-exp-bias-search';
                    $grid_id = 'fp-exp-bias-grid';
                    ?>
                    <div class="fp-exp-checkbox-grid__search">
                        <label class="screen-reader-text" for="<?php echo esc_attr($search_input_id); ?>">
                            <?php esc_html_e('Filtra badge di fiducia', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="search"
                            id="<?php echo esc_attr($search_input_id); ?>"
                            class="fp-exp-checkbox-grid__search-input"
                            data-fp-cognitive-bias-search
                            placeholder="<?php echo esc_attr__('Cerca badge…', 'fp-experiences'); ?>"
                            autocomplete="off"
                            spellcheck="false"
                            aria-controls="<?php echo esc_attr($grid_id); ?>"
                        />
                    </div>
                    <div
                        id="<?php echo esc_attr($grid_id); ?>"
                        class="fp-exp-checkbox-grid"
                        aria-describedby="fp-exp-bias-status fp-exp-bias-help"
                        data-fp-cognitive-bias
                        data-max="<?php echo esc_attr((string) $max_biases); ?>"
                    >
                        <?php foreach ($details['cognitive_biases']['choices'] as $choice) :
                            $bias_id = (string) ($choice['id'] ?? '');
                            if ('' === $bias_id) {
                                continue;
                            }

                            $label = isset($choice['label']) ? (string) $choice['label'] : '';
                            if ('' === $label) {
                                continue;
                            }

                            $description = isset($choice['description']) ? (string) $choice['description'] : '';
                            $tagline = isset($choice['tagline']) ? (string) $choice['tagline'] : '';
                            $icon_name = isset($choice['icon']) ? (string) $choice['icon'] : '';
                            $icon_svg = Helpers::cognitive_bias_icon_svg($icon_name);
                            $keywords = isset($choice['keywords']) && is_array($choice['keywords'])
                                ? array_values(array_filter(array_map('strval', $choice['keywords'])))
                                : [];
                            $search_terms = array_merge([$label, $tagline, $description], $keywords);
                            $search_terms = array_values(array_filter($search_terms, static function ($term): bool {
                                return '' !== trim((string) $term);
                            }));
                            $search_terms = array_map(static function ($term): string {
                                $value = sanitize_text_field((string) $term);
                                if ('' === $value) {
                                    return '';
                                }

                                if (function_exists('mb_strtolower')) {
                                    return mb_strtolower($value, 'UTF-8');
                                }

                                return strtolower($value);
                            }, $search_terms);
                            $search_terms = array_values(array_filter($search_terms));
                            $search_blob = implode(' ', array_unique($search_terms));
                            ?>
                            <label class="fp-exp-checkbox-grid__item" data-search="<?php echo esc_attr($search_blob); ?>">
                                <input type="checkbox" name="fp_exp_details[cognitive_biases][]" value="<?php echo esc_attr($bias_id); ?>" <?php checked(in_array($bias_id, $details['cognitive_biases']['selected'], true)); ?> />
                                <span class="fp-exp-checkbox-grid__content">
                                    <span class="fp-exp-checkbox-grid__icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
                                    <span class="fp-exp-checkbox-grid__body">
                                        <span class="fp-exp-checkbox-grid__title"><?php echo esc_html($label); ?></span>
                                        <?php if ('' !== $tagline) : ?>
                                            <span class="fp-exp-checkbox-grid__tagline"><?php echo esc_html($tagline); ?></span>
                                        <?php endif; ?>
                                        <?php if ('' !== $description) : ?>
                                            <span class="fp-exp-checkbox-grid__description"><?php echo esc_html($description); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p
                        class="fp-exp-field__description fp-exp-field__description--muted"
                        data-fp-cognitive-bias-empty
                        hidden
                    >
                        <?php esc_html_e('Nessun badge corrisponde alla ricerca.', 'fp-experiences'); ?>
                    </p>
                    <p
                        class="fp-exp-field__description fp-exp-field__description--status"
                        id="fp-exp-bias-status"
                        data-fp-cognitive-bias-status
                        data-template="<?php echo esc_attr($status_template); ?>"
                        data-max-message="<?php echo esc_attr($status_limit_message); ?>"
                    >
                        <?php
                        echo esc_html(
                            sprintf(
                                $status_template,
                                $selected_bias_count,
                                $max_biases
                            )
                        );

                        if ($selected_bias_count >= $max_biases && '' !== $status_limit_message) {
                            echo ' ' . esc_html($status_limit_message);
                        }
                        ?>
                    </p>
                    <p class="fp-exp-field__description" id="fp-exp-bias-help"><?php esc_html_e('Scegli fino a tre badge di fiducia per creare aspettative chiare nella sezione panoramica.', 'fp-experiences'); ?></p>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label">
                        <?php esc_html_e('Pagina pubblica', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-linked-page-help', esc_html__('Ogni esperienza pubblicata genera una pagina WordPress con lo shortcode completo.', 'fp-experiences')); ?>
                    </label>
                    <?php
                    $page_details = $details['linked_page'] ?? [];
                    $page_id = isset($page_details['id']) ? (int) $page_details['id'] : 0;
                    $page_url = isset($page_details['url']) ? (string) $page_details['url'] : '';
                    $page_edit_url = isset($page_details['edit_url']) ? (string) $page_details['edit_url'] : '';
                    $page_status = isset($page_details['status_label']) ? (string) $page_details['status_label'] : '';
                    ?>
                    <?php if ($page_id && $page_url) : ?>
                        <div class="fp-exp-field__buttons" role="group" aria-describedby="fp-exp-linked-page-help">
                            <a
                                class="button button-secondary"
                                href="<?php echo esc_url($page_url); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php esc_html_e('Vedi pagina', 'fp-experiences'); ?>
                            </a>
                            <?php if ($page_edit_url) : ?>
                                <a class="button" href="<?php echo esc_url($page_edit_url); ?>">
                                    <?php esc_html_e('Modifica pagina', 'fp-experiences'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($page_status) : ?>
                            <p class="fp-exp-field__description" id="fp-exp-linked-page-help">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: current page status label. */
                                        __('Stato pagina: %s', 'fp-experiences'),
                                        $page_status
                                    )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="fp-exp-field__description" id="fp-exp-linked-page-help">
							<?php esc_html_e("La pagina viene generata automaticamente alla pubblicazione dell'esperienza.", 'fp-experiences'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-min-party">
                            <?php esc_html_e('Partecipanti minimi', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-min-party-help', esc_html__('Numero minimo richiesto per confermare la partenza.', 'fp-experiences')); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-min-party"
                            name="fp_exp_details[min_party]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $details['min_party']); ?>"
                            aria-describedby="fp-exp-min-party-help"
                        />
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-capacity">
                            <?php esc_html_e('Capienza totale', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-capacity-help', esc_html__('Numero massimo di posti disponibili complessivi.', 'fp-experiences')); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-capacity"
                            name="fp_exp_details[capacity_slot]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $details['capacity_slot']); ?>"
                            aria-describedby="fp-exp-capacity-help"
                        />
                    </div>
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-age-min">
                            <?php esc_html_e('Età minima', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-age-min-help', esc_html__('Età minima consigliata per partecipare.', 'fp-experiences')); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-age-min"
                            name="fp_exp_details[age_min]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $details['age_min']); ?>"
                            aria-describedby="fp-exp-age-min-help"
                        />
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-age-max">
                            <?php esc_html_e('Età massima', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-age-max-help', esc_html__('Lascia vuoto se non previsto.', 'fp-experiences')); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-age-max"
                            name="fp_exp_details[age_max]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $details['age_max']); ?>"
                            aria-describedby="fp-exp-age-max-help"
                        />
                    </div>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-children-rules">
                        <?php esc_html_e('Regole bambini', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-children-help', esc_html__('Note su policy bambini, passeggini o riduzioni.', 'fp-experiences')); ?>
                    </label>
                    <textarea
                        id="fp-exp-children-rules"
                        name="fp_exp_details[rules_children]"
                        rows="3"
                        placeholder="<?php echo esc_attr__('Es. Gratuito sotto i 6 anni accompagnati da un adulto', 'fp-experiences'); ?>"
                        aria-describedby="fp-exp-children-help"
                    ><?php echo esc_textarea((string) $details['rules_children']); ?></textarea>
                    <p class="fp-exp-field__description" id="fp-exp-children-help"><?php esc_html_e('Testo mostrato nelle informazioni aggiuntive.', 'fp-experiences'); ?></p>
                </div>
            </fieldset>
        </section>
        <?php
    }

    private function render_gallery_item(array $image, bool $is_template = false): void
    {
        $image_id = isset($image['id']) ? absint((string) $image['id']) : 0;
        $image_url = isset($image['url']) ? (string) $image['url'] : '';
        $image_alt = isset($image['alt']) ? (string) $image['alt'] : '';
        ?>
        <li class="fp-exp-gallery-control__item" data-fp-gallery-item<?php echo (! $is_template && $image_id > 0) ? ' data-id="' . esc_attr((string) $image_id) . '"' : ''; ?>>
            <div class="fp-exp-gallery-control__thumb">
                <span class="fp-exp-gallery-control__placeholder" data-fp-gallery-placeholder <?php echo $image_url ? ' hidden' : ''; ?>>
                    <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                        <rect x="1" y="1" width="46" height="30" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2" />
                        <path d="M16 12a4 4 0 1 1 4 4 4 4 0 0 1-4-4Zm-6 14 8-10 6 7 4-5 8 8Z" fill="currentColor" />
                    </svg>
                    <span class="screen-reader-text"><?php esc_html_e('Nessuna immagine selezionata', 'fp-experiences'); ?></span>
                </span>
                <?php if ($image_url) : ?>
                    <img
                        src="<?php echo esc_url($image_url); ?>"
                        alt="<?php echo esc_attr($image_alt); ?>"
                        loading="lazy"
                        data-fp-gallery-image
                    />
                <?php else : ?>
                    <img src="" alt="" loading="lazy" data-fp-gallery-image hidden />
                <?php endif; ?>
            </div>
            <div class="fp-exp-gallery-control__toolbar">
                <button
                    type="button"
                    class="fp-exp-gallery-control__move"
                    data-fp-gallery-move="prev"
                    aria-label="<?php esc_attr_e('Sposta prima', 'fp-experiences'); ?>"
                >
                    <span aria-hidden="true">↑</span>
                </button>
                <button
                    type="button"
                    class="fp-exp-gallery-control__move"
                    data-fp-gallery-move="next"
                    aria-label="<?php esc_attr_e('Sposta dopo', 'fp-experiences'); ?>"
                >
                    <span aria-hidden="true">↓</span>
                </button>
            </div>
            <button
                type="button"
                class="fp-exp-gallery-control__remove"
                data-fp-gallery-remove
                aria-label="<?php esc_attr_e('Rimuovi immagine', 'fp-experiences'); ?>"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        </li>
        <?php
    }
    /**
     * @deprecated Use PricingMetaBoxHandler::render() instead
     * @param array<string, mixed> $pricing
     */
    private function render_pricing_tab(array $pricing): void
    {
        $panel_id = 'fp-exp-tab-pricing-panel';
        $tickets = $pricing['tickets'];
        if (empty($tickets)) {
            $tickets = [['label' => '', 'price' => '', 'capacity' => '', 'slug' => '']];
        }

        $addons = $pricing['addons'];
        // Non mostrare addon vuoto di default - l'utente può aggiungerli con il pulsante "Aggiungi extra"
        if (empty($addons)) {
            $addons = [];
        }

        $group = $pricing['group'];
        $tax_class = $pricing['tax_class'];
        $selected_tax_class = '' === $tax_class ? 'standard' : $tax_class;
        $tax_class_options = $this->get_tax_class_options();
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-pricing"
            data-tab-panel="pricing"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Prezzo base esperienza', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-base-price">
                        <?php esc_html_e('Prezzo base (€)', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="number"
                        id="fp-exp-base-price"
                        name="fp_exp_pricing[base_price]"
                        step="0.01"
                        min="0"
                        value="<?php echo esc_attr((string) ($pricing['base_price'] ?? '')); ?>"
                    />
                    <p class="fp-exp-field__description">
                        <?php esc_html_e('Prezzo base che viene aggiunto al totale indipendentemente dal numero di biglietti. Lascia 0 se non vuoi un prezzo base.', 'fp-experiences'); ?>
                    </p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Tipi di biglietto', 'fp-experiences'); ?></legend>
                <div
                    class="fp-exp-repeater"
                    data-repeater="tickets"
                    data-repeater-next-index="<?php echo esc_attr((string) count($pricing['tickets'])); ?>"
                >
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($tickets as $index => $ticket) : ?>
                            <?php $this->render_ticket_row((string) $index, $ticket); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_ticket_row('__INDEX__', ['label' => '', 'price' => '', 'capacity' => '', 'slug' => ''], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add>
                            <?php esc_html_e('Aggiungi tipo biglietto', 'fp-experiences'); ?>
                        </button>
                    </p>
                    <p class="fp-exp-repeater__hint" data-repeater-hint="tickets"></p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Prezzo gruppo (opzionale)', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-group-price">
                            <?php esc_html_e('Prezzo totale (€)', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-group-price"
                            name="fp_exp_pricing[group][price]"
                            step="0.01"
                            min="0"
                            value="<?php echo esc_attr((string) ($group['price'] ?? '')); ?>"
                        />
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-group-capacity">
                            <?php esc_html_e('Capienza massima gruppo', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-group-capacity"
                            name="fp_exp_pricing[group][capacity]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) ($group['capacity'] ?? '')); ?>"
                        />
                    </div>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Extra', 'fp-experiences'); ?></legend>
                <div
                    class="fp-exp-repeater"
                    data-repeater="addons"
                    data-repeater-next-index="<?php echo esc_attr((string) count($pricing['addons'])); ?>"
                >
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($addons as $index => $addon) : ?>
                            <?php $this->render_addon_row((string) $index, $addon); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_addon_row('__INDEX__', ['name' => '', 'price' => '', 'type' => 'person', 'slug' => '', 'selection_type' => 'checkbox', 'selection_group' => ''], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add>
                            <?php esc_html_e('Aggiungi extra', 'fp-experiences'); ?>
                        </button>
                    </p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('IVA', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-tax-class">
                        <?php esc_html_e('Classe tassa WooCommerce', 'fp-experiences'); ?>
                    </label>
                    <select id="fp-exp-tax-class" name="fp_exp_pricing[tax_class]">
                        <option value="">&mdash; <?php esc_html_e('Seleziona classe tassa', 'fp-experiences'); ?> &mdash;</option>
                        <?php foreach ($tax_class_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $selected_tax_class); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>
        </section>
        <?php
    }
    /**
     * @deprecated Use CalendarMetaBoxHandler::render() instead
     * @param array<string, mixed> $availability
     */
    private function render_calendar_tab(array $availability): void
    {
        $panel_id = 'fp-exp-tab-calendar-panel';
        $recurrence = $availability['recurrence'] ?? Recurrence::defaults();
        if (! is_array($recurrence)) {
            $recurrence = Recurrence::defaults();
        } else {
            $recurrence = array_merge(Recurrence::defaults(), $recurrence);
        }

        // Sistema semplificato: solo weekly frequency
        $frequency = 'weekly';

        // Time slots semplificati
        $time_slots = $recurrence['time_slots'] ?? [];
        if (empty($time_slots)) {
            $time_slots = [['time' => '', 'capacity' => 0, 'buffer_before' => 0, 'buffer_after' => 0, 'days' => []]];
        }

        $recurrence_days = isset($recurrence['days']) && is_array($recurrence['days']) ? $recurrence['days'] : [];
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-calendar"
            data-tab-panel="calendar"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Guida rapida', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <p class="fp-exp-field__description"><?php esc_html_e('Configura la disponibilità dell\'esperienza in modo semplice:', 'fp-experiences'); ?></p>
                    <p class="fp-exp-field__description"><strong><?php esc_html_e('1.', 'fp-experiences'); ?></strong> <?php esc_html_e('Imposta capacità generale e buffer generali.', 'fp-experiences'); ?></p>
                    <p class="fp-exp-field__description"><strong><?php esc_html_e('2.', 'fp-experiences'); ?></strong> <?php esc_html_e('Seleziona i giorni della settimana in cui l\'esperienza è disponibile.', 'fp-experiences'); ?></p>
                    <p class="fp-exp-field__description"><strong><?php esc_html_e('3.', 'fp-experiences'); ?></strong> <?php esc_html_e('Aggiungi gli slot orari con eventuali override.', 'fp-experiences'); ?></p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Impostazioni generali', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-slot-capacity"><?php esc_html_e('Capacità generale', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-slot-capacity"
                            name="fp_exp_availability[slot_capacity]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['slot_capacity']); ?>"
                        />
                        <p class="fp-exp-field__description"><?php esc_html_e('Numero massimo di partecipanti per slot. Puoi sovrascriverlo per singoli orari.', 'fp-experiences'); ?></p>
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-lead-time"><?php esc_html_e('Preavviso minimo (ore)', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-lead-time"
                            name="fp_exp_availability[lead_time_hours]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['lead_time_hours']); ?>"
                        />
                        <p class="fp-exp-field__description"><?php esc_html_e('Tempo minimo richiesto prima della prenotazione.', 'fp-experiences'); ?></p>
                    </div>
                </div>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-buffer-before"><?php esc_html_e('Buffer generale prima (minuti)', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-buffer-before"
                            name="fp_exp_availability[buffer_before_minutes]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['buffer_before_minutes']); ?>"
                        />
                        <p class="fp-exp-field__description"><?php esc_html_e('Tempo di preparazione prima di ogni slot.', 'fp-experiences'); ?></p>
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-buffer-after"><?php esc_html_e('Buffer generale dopo (minuti)', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-buffer-after"
                            name="fp_exp_availability[buffer_after_minutes]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['buffer_after_minutes']); ?>"
                        />
                        <p class="fp-exp-field__description"><?php esc_html_e('Tempo di pulizia dopo ogni slot.', 'fp-experiences'); ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Giorni della settimana', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <p class="fp-exp-field__description"><?php esc_html_e('Seleziona i giorni in cui l\'esperienza è disponibile:', 'fp-experiences'); ?></p>
                    <div class="fp-exp-checkbox-grid">
                        <?php foreach ($this->get_week_days() as $day_key => $day_label) : ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="fp_exp_availability[recurrence][days][]"
                                    value="<?php echo esc_attr($day_key); ?>"
                                    <?php checked(in_array($this->map_weekday_for_ui($day_key), $recurrence_days, true)); ?>
                                />
                                <span><?php echo esc_html($day_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Slot orari', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <p class="fp-exp-field__description"><?php esc_html_e('Definisci gli orari in cui l\'esperienza è disponibile. Puoi impostare override opzionali per ogni slot.', 'fp-experiences'); ?></p>
                </div>
                
                <!-- Durata predefinita -->
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-duration"><?php esc_html_e('Durata predefinita slot (minuti)', 'fp-experiences'); ?></label>
                    <input 
                        type="number" 
                        id="fp-exp-duration"
                        min="15" 
                        step="5" 
                        name="fp_exp_availability[recurrence][duration]" 
                        value="<?php echo esc_attr((string) ($recurrence['duration'] ?? 60)); ?>" 
                    />
                    <p class="fp-exp-field__description"><?php esc_html_e('Usata come base per tutti gli slot, salvo override specifici.', 'fp-experiences'); ?></p>
                </div>

                <input type="hidden" name="fp_exp_availability[recurrence][frequency]" value="weekly" />

                <div class="fp-exp-repeater" data-repeater="time_slots" data-repeater-next-index="<?php echo esc_attr((string) count($time_slots)); ?>">
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($time_slots as $index => $slot) : ?>
                            <?php $this->render_simple_time_slot_row((string) $index, $slot, false); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_simple_time_slot_row('__INDEX__', [
                            'time' => '',
                            'capacity' => 0,
                            'buffer_before' => 0,
                            'buffer_after' => 0,
                            'days' => [],
                        ], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add><?php esc_html_e('Aggiungi slot orario', 'fp-experiences'); ?></button>
                    </p>
                </div>
            </fieldset>

        </section>
        <?php
    }
    /**
     * @deprecated Use MeetingPointMetaBoxHandler::render() instead
     * @param array<string, mixed> $meeting
     * @param array<int, array{id: int, title: string}> $choices
     */
    private function render_meeting_point_tab(array $meeting, array $choices): void
    {
        $panel_id = 'fp-exp-tab-meeting-point-panel';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-meeting-point"
            data-tab-panel="meeting-point"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Seleziona i meeting point', 'fp-experiences'); ?></legend>
                <?php if (! Helpers::meeting_points_enabled()) : ?>
                    <p class="fp-exp-field__description fp-exp-field__description--warning">
                        <?php esc_html_e('Attiva la funzione Meeting Point dalle impostazioni del plugin per gestire i luoghi di incontro.', 'fp-experiences'); ?>
                    </p>
                <?php endif; ?>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meeting-primary">
                        <?php esc_html_e('Meeting point principale', 'fp-experiences'); ?>
                    </label>
                    <select id="fp-exp-meeting-primary" name="fp_exp_meeting_point[primary]">
                        <option value="0">&mdash; <?php esc_html_e('Nessuno', 'fp-experiences'); ?> &mdash;</option>
                        <?php foreach ($choices as $choice) : ?>
                            <option value="<?php echo esc_attr((string) $choice['id']); ?>" <?php selected($meeting['primary'], $choice['id']); ?>><?php echo esc_html($choice['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meeting-alternatives">
                        <?php esc_html_e('Meeting point alternativi', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-meeting-alt-help', esc_html__('Seleziona uno o più punti di incontro alternativi per casi particolari.', 'fp-experiences')); ?>
                    </label>
                    <select
                        id="fp-exp-meeting-alternatives"
                        name="fp_exp_meeting_point[alternatives][]"
                        multiple
                        size="5"
                        aria-describedby="fp-exp-meeting-alt-help"
                    >
                        <?php foreach ($choices as $choice) : ?>
                            <option value="<?php echo esc_attr((string) $choice['id']); ?>" <?php selected(in_array($choice['id'], $meeting['alternatives'], true), true); ?>><?php echo esc_html($choice['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="fp-exp-field__description" id="fp-exp-meeting-alt-help"><?php esc_html_e('Usa CTRL/CMD + clic per selezionare più voci.', 'fp-experiences'); ?></p>
                </div>
            </fieldset>
        </section>
        <?php
    }
    /**
     * @deprecated Use ExtrasMetaBoxHandler::render() instead
     * @param array<string, mixed> $extras
     */
    private function render_extras_tab(array $extras): void
    {
        $panel_id = 'fp-exp-tab-extras-panel';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-extras"
            data-tab-panel="extras"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e("Cosa include l'esperienza", 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-highlights">
                        <?php esc_html_e('Highlight (uno per riga)', 'fp-experiences'); ?>
                    </label>
                    <textarea id="fp-exp-highlights" name="fp_exp_extras[highlights]" rows="4" placeholder="<?php echo esc_attr__('Accesso prioritario&#10;Guida certificata&#10;Piccoli gruppi', 'fp-experiences'); ?>"><?php echo esc_textarea($extras['highlights']); ?></textarea>
                </div>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-inclusions">
                            <?php esc_html_e('Incluso (uno per riga)', 'fp-experiences'); ?>
                        </label>
                        <textarea id="fp-exp-inclusions" name="fp_exp_extras[inclusions]" rows="4"><?php echo esc_textarea($extras['inclusions']); ?></textarea>
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-exclusions">
                            <?php esc_html_e('Non incluso (uno per riga)', 'fp-experiences'); ?>
                        </label>
                        <textarea id="fp-exp-exclusions" name="fp_exp_extras[exclusions]" rows="4"><?php echo esc_textarea($extras['exclusions']); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Consigli utili', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-what-to-bring">
                        <?php esc_html_e('Cosa portare', 'fp-experiences'); ?>
                    </label>
                    <textarea id="fp-exp-what-to-bring" name="fp_exp_extras[what_to_bring]" rows="3"><?php echo esc_textarea((string) $extras['what_to_bring']); ?></textarea>
                </div>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-notes">
                        <?php esc_html_e('Note aggiuntive', 'fp-experiences'); ?>
                    </label>
                    <textarea id="fp-exp-notes" name="fp_exp_extras[notes]" rows="3"><?php echo esc_textarea((string) $extras['notes']); ?></textarea>
                </div>
            </fieldset>
        </section>
        <?php
    }
    /**
     * @deprecated Use PolicyMetaBoxHandler::render() instead
     * @param array<string, mixed> $policy
     */
    private function render_policy_tab(array $policy): void
    {
        $panel_id = 'fp-exp-tab-policy-panel';
        $faq_items = $policy['faq'];
        if (empty($faq_items)) {
            $faq_items = [['question' => '', 'answer' => '']];
        }
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-policy"
            data-tab-panel="policy"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Policy di cancellazione', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-policy-text">
                        <?php esc_html_e('Testo policy', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-policy-help', esc_html__('Puoi utilizzare HTML semplice per grassetti o link.', 'fp-experiences')); ?>
                    </label>
                    <textarea id="fp-exp-policy-text" name="fp_exp_policy[cancel]" rows="5" aria-describedby="fp-exp-policy-help"><?php echo esc_textarea((string) $policy['cancel']); ?></textarea>
                    <p class="fp-exp-field__description" id="fp-exp-policy-help"><?php esc_html_e('Esempio: Cancellazione gratuita fino a 48 ore dalla partenza.', 'fp-experiences'); ?></p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('FAQ', 'fp-experiences'); ?></legend>
                <div class="fp-exp-repeater" data-repeater="faq" data-repeater-next-index="<?php echo esc_attr((string) count($policy['faq'])); ?>">
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($faq_items as $index => $item) : ?>
                            <?php $this->render_faq_row((string) $index, $item); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_faq_row('__INDEX__', ['question' => '', 'answer' => ''], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add><?php esc_html_e('Aggiungi FAQ', 'fp-experiences'); ?></button>
                    </p>
                </div>
            </fieldset>
        </section>
        <?php
    }
    /**
     * @deprecated Use SEOMetaBoxHandler::render() instead
     * @param array<string, mixed> $seo
     */
    private function render_seo_tab(array $seo): void
    {
        $panel_id = 'fp-exp-tab-seo-panel';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-seo"
            data-tab-panel="seo"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('SEO', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meta-title">
                        <?php esc_html_e('Meta title personalizzato', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="text"
                        id="fp-exp-meta-title"
                        name="fp_exp_seo[meta_title]"
                        value="<?php echo esc_attr($seo['meta_title']); ?>"
                        placeholder="<?php echo esc_attr__('Es. Tour segreto di Firenze | Brand', 'fp-experiences'); ?>"
                    />
                </div>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meta-description">
                        <?php esc_html_e('Meta description', 'fp-experiences'); ?>
                    </label>
                    <textarea id="fp-exp-meta-description" name="fp_exp_seo[meta_description]" rows="4"><?php echo esc_textarea($seo['meta_description']); ?></textarea>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Schema markup', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-schema-json">
                        <?php esc_html_e('Schema JSON-LD personalizzato', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-schema-help', esc_html__('Incolla JSON-LD valido per sovrascrivere lo schema generato automaticamente.', 'fp-experiences')); ?>
                    </label>
                    <textarea id="fp-exp-schema-json" name="fp_exp_seo[schema_json]" rows="6" aria-describedby="fp-exp-schema-help" class="code"><?php echo esc_textarea($seo['schema_json']); ?></textarea>
						<p class="fp-exp-field__description" id="fp-exp-schema-help"><?php esc_html_e("Lascia vuoto per usare lo schema standard dell\'esperienza.", 'fp-experiences'); ?></p>
                </div>
            </fieldset>
        </section>
        <?php
    }
    private function render_tooltip(string $id, string $text): void
    {
        $tooltip_id = $id . '-tooltip';
        $visible_id = $id . '-tooltip-content';
        ?>
        <button type="button" class="fp-exp-tooltip" aria-describedby="<?php echo esc_attr($tooltip_id); ?>">
            <span class="screen-reader-text" id="<?php echo esc_attr($tooltip_id); ?>"><?php echo esc_html($text); ?></span>
            <span aria-hidden="true">i</span>
        </button>
        <span class="fp-exp-tooltip__content" id="<?php echo esc_attr($visible_id); ?>" role="tooltip" aria-hidden="true"><?php echo esc_html($text); ?></span>
        <?php
    }
    private function render_ticket_row(string $index, array $ticket, bool $is_template = false): void
    {
        $name_prefix = 'fp_exp_pricing[tickets][' . $index . ']';
        $label_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][label]' : $name_prefix . '[label]';
        $price_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][price]' : $name_prefix . '[price]';
        $capacity_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][capacity]' : $name_prefix . '[capacity]';
        $slug_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][slug]' : $name_prefix . '[slug]';
        $use_as_price_from_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][use_as_price_from]' : $name_prefix . '[use_as_price_from]';
        $use_as_price_from_checked = ! empty($ticket['use_as_price_from']);
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item draggable="true">
            <div class="fp-exp-repeater-row__fields">
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Etichetta', 'fp-experiences'); ?></span>
                    <input type="text" <?php echo $this->field_name_attribute($label_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['label'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Es. Adulto', 'fp-experiences'); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Codice', 'fp-experiences'); ?></span>
                    <input type="text" <?php echo $this->field_name_attribute($slug_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['slug'] ?? '')); ?>" placeholder="<?php echo esc_attr__('adulto', 'fp-experiences'); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Prezzo (€)', 'fp-experiences'); ?></span>
                    <input type="number" min="0" step="0.01" <?php echo $this->field_name_attribute($price_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['price'] ?? '')); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Capienza', 'fp-experiences'); ?></span>
                    <input type="number" min="0" step="1" <?php echo $this->field_name_attribute($capacity_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['capacity'] ?? '')); ?>" />
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" <?php echo $this->field_name_attribute($use_as_price_from_name, $is_template); ?> value="1" <?php checked($use_as_price_from_checked); ?> />
                    <span class="fp-exp-field__label" style="margin: 0;"><?php esc_html_e('Prezzo "da..."', 'fp-experiences'); ?></span>
                </label>
            </div>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }
    private function render_addon_row(string $index, array $addon, bool $is_template = false): void
    {
        $name_prefix = 'fp_exp_pricing[addons][' . $index . ']';
        $label_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][name]' : $name_prefix . '[name]';
        $price_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][price]' : $name_prefix . '[price]';
        $type_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][type]' : $name_prefix . '[type]';
        $slug_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][slug]' : $name_prefix . '[slug]';
        $image_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][image_id]' : $name_prefix . '[image_id]';
        $description_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][description]' : $name_prefix . '[description]';
        $selection_type_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][selection_type]' : $name_prefix . '[selection_type]';
        $selection_group_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][selection_group]' : $name_prefix . '[selection_group]';
        $type_value = isset($addon['type']) ? (string) $addon['type'] : 'person';
        $selection_type_value = isset($addon['selection_type']) ? (string) $addon['selection_type'] : 'checkbox';
        $selection_group_value = isset($addon['selection_group']) ? (string) $addon['selection_group'] : '';
        $image_id = isset($addon['image_id']) ? absint((string) $addon['image_id']) : 0;
        $image = $image_id > 0 ? wp_get_attachment_image_src($image_id, 'thumbnail') : false;
        $image_url = $image ? (string) $image[0] : '';
        $image_width = $image ? absint((string) $image[1]) : 0;
        $image_height = $image ? absint((string) $image[2]) : 0;
        $image_alt = isset($addon['name']) ? (string) $addon['name'] : '';
        ?>
        <div class="fp-exp-repeater-row fp-exp-addon-row" data-repeater-item draggable="true">
            <div class="fp-exp-repeater-row__fields">
                <!-- Sezione Immagine -->
                <div class="fp-exp-addon-section fp-exp-addon-section--media">
                    <div class="fp-exp-addon-media" data-fp-media-control>
                        <span class="fp-exp-field__label"><?php esc_html_e('Immagine', 'fp-experiences'); ?></span>
                        <input
                            type="hidden"
                            <?php echo $this->field_name_attribute($image_name, $is_template); ?>
                            value="<?php echo esc_attr((string) $image_id); ?>"
                            data-fp-media-input
                        />
                        <div class="fp-exp-addon-media__preview" data-fp-media-preview>
                            <div class="fp-exp-addon-media__placeholder" data-fp-media-placeholder <?php echo $image_url ? ' hidden' : ''; ?>>
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5">
                                        <rect x="3.75" y="8.25" width="16.5" height="12" rx="2" />
                                        <path d="M3.75 11.25h16.5" />
                                        <path d="M12 3.75c-1.657 0-3 1.231-3 2.75 0 1.519 1.343 2.75 3 2.75s3-1.231 3-2.75c0-1.519-1.343-2.75-3-2.75Zm0 0C12 3 11.25 2.25 10.5 2.25S9 3 9 3.75" />
                                        <path d="M12 3.75c0-.75.75-1.5 1.5-1.5s1.5.75 1.5 1.5" />
                                    </g>
                                </svg>
                                <span class="screen-reader-text"><?php esc_html_e('Nessuna immagine selezionata', 'fp-experiences'); ?></span>
                            </div>
                            <?php if ($image_url) : ?>
                                <img
                                    src="<?php echo esc_url($image_url); ?>"
                                    alt="<?php echo esc_attr($image_alt); ?>"
                                    <?php if ($image_width > 0) : ?> width="<?php echo esc_attr((string) $image_width); ?>"<?php endif; ?>
                                    <?php if ($image_height > 0) : ?> height="<?php echo esc_attr((string) $image_height); ?>"<?php endif; ?>
                                    loading="lazy"
                                    data-fp-media-image
                                />
                            <?php endif; ?>
                        </div>
                        <div class="fp-exp-addon-media__actions">
                            <button
                                type="button"
                                class="button button-secondary fp-exp-addon-media__choose"
                                data-fp-media-choose
                                data-label-select="<?php echo esc_attr__('Seleziona immagine', 'fp-experiences'); ?>"
                                data-label-change="<?php echo esc_attr__('Modifica immagine', 'fp-experiences'); ?>"
                            >
                                <?php echo $image_url ? esc_html__('Modifica immagine', 'fp-experiences') : esc_html__('Seleziona immagine', 'fp-experiences'); ?>
                            </button>
                            <button
                                type="button"
                                class="button-link fp-exp-addon-media__remove"
                                data-fp-media-remove
                                <?php echo $image_url ? '' : ' hidden'; ?>
                            >
                                <?php esc_html_e('Rimuovi immagine', 'fp-experiences'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sezione Informazioni Base -->
                <div class="fp-exp-addon-section">
                    <h4 class="fp-exp-addon-section__title"><?php esc_html_e('Informazioni Base', 'fp-experiences'); ?></h4>
                    <div class="fp-exp-addon-section__fields">
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Nome extra', 'fp-experiences'); ?> <span class="fp-exp-required">*</span></span>
                            <input type="text" <?php echo $this->field_name_attribute($label_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['name'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Es: Transfer, Audio guida, Pranzo', 'fp-experiences'); ?>" required />
                        </label>
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Codice', 'fp-experiences'); ?></span>
                            <input type="text" <?php echo $this->field_name_attribute($slug_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['slug'] ?? '')); ?>" placeholder="<?php echo esc_attr__('transfer, audio-guida, pranzo', 'fp-experiences'); ?>" />
                            <small class="fp-exp-field__help"><?php esc_html_e('Lascia vuoto per generare automaticamente dal nome', 'fp-experiences'); ?></small>
                        </label>
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Descrizione breve', 'fp-experiences'); ?></span>
                            <textarea rows="2" maxlength="160" <?php echo $this->field_name_attribute($description_name, $is_template); ?> placeholder="<?php echo esc_attr__('Breve descrizione che apparirà sotto il nome (max 160 caratteri)', 'fp-experiences'); ?>"><?php echo esc_textarea((string) ($addon['description'] ?? '')); ?></textarea>
                            <small class="fp-exp-field__help"><?php esc_html_e('Opzionale. Aiuta l\'utente a capire cosa include l\'extra.', 'fp-experiences'); ?></small>
                        </label>
                    </div>
                </div>

                <!-- Sezione Prezzo -->
                <div class="fp-exp-addon-section">
                    <h4 class="fp-exp-addon-section__title"><?php esc_html_e('Prezzo e Calcolo', 'fp-experiences'); ?></h4>
                    <div class="fp-exp-addon-section__fields fp-exp-addon-section__fields--inline">
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Prezzo (€)', 'fp-experiences'); ?> <span class="fp-exp-required">*</span></span>
                            <input type="number" min="0" step="0.01" <?php echo $this->field_name_attribute($price_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['price'] ?? '')); ?>" placeholder="0.00" required />
                        </label>
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Calcolo prezzo', 'fp-experiences'); ?></span>
                            <select <?php echo $this->field_name_attribute($type_name, $is_template); ?>>
                                <option value="person" <?php selected($type_value, 'person'); ?>><?php esc_html_e('Per persona', 'fp-experiences'); ?></option>
                                <option value="booking" <?php selected($type_value, 'booking'); ?>><?php esc_html_e('Per prenotazione', 'fp-experiences'); ?></option>
                            </select>
                            <small class="fp-exp-field__help"><?php esc_html_e('Moltiplicato per numero ospiti o fisso', 'fp-experiences'); ?></small>
                        </label>
                    </div>
                </div>

                <!-- Sezione Comportamento Selezione -->
                <div class="fp-exp-addon-section fp-exp-addon-section--highlight">
                    <h4 class="fp-exp-addon-section__title">
                        <?php esc_html_e('Comportamento Selezione', 'fp-experiences'); ?>
                        <span class="fp-exp-addon-section__badge"><?php esc_html_e('Nuovo', 'fp-experiences'); ?></span>
                    </h4>
                    <p class="fp-exp-addon-section__intro">
                        <?php esc_html_e('Configura come l\'utente può selezionare questo extra nel frontend.', 'fp-experiences'); ?>
                    </p>
                    <div class="fp-exp-addon-section__fields fp-exp-addon-section__fields--inline">
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Tipo selezione', 'fp-experiences'); ?></span>
                            <select <?php echo $this->field_name_attribute($selection_type_name, $is_template); ?>>
                                <option value="checkbox" <?php selected($selection_type_value, 'checkbox'); ?>><?php esc_html_e('☑ Checkbox (multipla)', 'fp-experiences'); ?></option>
                                <option value="radio" <?php selected($selection_type_value, 'radio'); ?>><?php esc_html_e('◉ Radio (una scelta)', 'fp-experiences'); ?></option>
                            </select>
                            <small class="fp-exp-field__help">
                                <strong><?php esc_html_e('Checkbox:', 'fp-experiences'); ?></strong> <?php esc_html_e('L\'utente può selezionare più extra insieme', 'fp-experiences'); ?><br>
                                <strong><?php esc_html_e('Radio:', 'fp-experiences'); ?></strong> <?php esc_html_e('L\'utente può scegliere solo un extra tra quelli del gruppo', 'fp-experiences'); ?>
                            </small>
                        </label>
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Gruppo selezione', 'fp-experiences'); ?></span>
                            <input type="text" <?php echo $this->field_name_attribute($selection_group_name, $is_template); ?> value="<?php echo esc_attr($selection_group_value); ?>" placeholder="<?php echo esc_attr__('Es: Trasporto, Pranzo, Servizi', 'fp-experiences'); ?>" />
                            <small class="fp-exp-field__help">
                                <?php esc_html_e('Raggruppa extra correlati. Gli extra con lo stesso gruppo appaiono insieme nel frontend.', 'fp-experiences'); ?><br>
                                <strong><?php esc_html_e('Radio:', 'fp-experiences'); ?></strong> <?php esc_html_e('Solo un extra selezionabile per gruppo', 'fp-experiences'); ?><br>
                                <strong><?php esc_html_e('Checkbox:', 'fp-experiences'); ?></strong> <?php esc_html_e('Tutti selezionabili, visualizzati insieme', 'fp-experiences'); ?>
                            </small>
                        </label>
                    </div>
                </div>
            </div>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove aria-label="<?php esc_attr_e('Rimuovi extra', 'fp-experiences'); ?>">&times;</button>
            </p>
        </div>
        <?php
    }
    private function render_time_row(string $index, string $time, bool $is_template = false): void
    {
        $field_name = $is_template ? 'fp_exp_availability[times][__INDEX__]' : 'fp_exp_availability[times][' . $index . ']';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <p>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Orario disponibile', 'fp-experiences'); ?></span>
                    <input type="time" <?php echo $this->field_name_attribute($field_name, $is_template); ?> value="<?php echo esc_attr($time); ?>" />
                </label>
            </p>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    /**
     * Render a simplified time slot row for the new simplified calendar system.
     */
    private function render_simple_time_slot_row(string $index, array $slot, bool $is_template = false): void
    {
        $time_name = $is_template
            ? 'fp_exp_availability[recurrence][time_slots][__INDEX__][time]'
            : 'fp_exp_availability[recurrence][time_slots][' . $index . '][time]';
        $capacity_name = $is_template
            ? 'fp_exp_availability[recurrence][time_slots][__INDEX__][capacity]'
            : 'fp_exp_availability[recurrence][time_slots][' . $index . '][capacity]';
        $buffer_before_name = $is_template
            ? 'fp_exp_availability[recurrence][time_slots][__INDEX__][buffer_before]'
            : 'fp_exp_availability[recurrence][time_slots][' . $index . '][buffer_before]';
        $buffer_after_name = $is_template
            ? 'fp_exp_availability[recurrence][time_slots][__INDEX__][buffer_after]'
            : 'fp_exp_availability[recurrence][time_slots][' . $index . '][buffer_after]';

        $time_value = isset($slot['time']) ? (string) $slot['time'] : '';
        $capacity_value = isset($slot['capacity']) ? (string) absint((string) $slot['capacity']) : '';
        $buffer_before_value = isset($slot['buffer_before']) ? (string) absint((string) $slot['buffer_before']) : '';
        $buffer_after_value = isset($slot['buffer_after']) ? (string) absint((string) $slot['buffer_after']) : '';

        // Nascondi valori zero
        if ('0' === $capacity_value) {
            $capacity_value = '';
        }
        if ('0' === $buffer_before_value) {
            $buffer_before_value = '';
        }
        if ('0' === $buffer_after_value) {
            $buffer_after_value = '';
        }
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <div class="fp-exp-field fp-exp-field--columns">
                <div>
                    <label>
                        <span class="fp-exp-field__label"><?php esc_html_e('Orario', 'fp-experiences'); ?></span>
                        <input 
                            type="time" 
                            name="<?php echo esc_attr($time_name); ?>" 
                            value="<?php echo esc_attr($time_value); ?>" 
                            placeholder="<?php echo esc_attr__('Es. 10:00', 'fp-experiences'); ?>"
                        />
                    </label>
                </div>
                <div>
                    <label>
                        <span class="fp-exp-field__label"><?php esc_html_e('Capacità override', 'fp-experiences'); ?> <em>(<?php esc_html_e('opzionale', 'fp-experiences'); ?>)</em></span>
                        <input 
                            type="number" 
                            name="<?php echo esc_attr($capacity_name); ?>" 
                            value="<?php echo esc_attr($capacity_value); ?>" 
                            min="0" 
                            step="1" 
                            placeholder="<?php echo esc_attr__('Usa capienza generale', 'fp-experiences'); ?>"
                        />
                    </label>
                </div>
            </div>
            <div class="fp-exp-field fp-exp-field--columns">
                <div>
                    <label>
                        <span class="fp-exp-field__label"><?php esc_html_e('Buffer prima override (min)', 'fp-experiences'); ?> <em>(<?php esc_html_e('opz.', 'fp-experiences'); ?>)</em></span>
                        <input 
                            type="number" 
                            name="<?php echo esc_attr($buffer_before_name); ?>" 
                            value="<?php echo esc_attr($buffer_before_value); ?>" 
                            min="0" 
                            step="1" 
                            placeholder="<?php echo esc_attr__('Usa buffer generale', 'fp-experiences'); ?>"
                        />
                    </label>
                </div>
                <div>
                    <label>
                        <span class="fp-exp-field__label"><?php esc_html_e('Buffer dopo override (min)', 'fp-experiences'); ?> <em>(<?php esc_html_e('opz.', 'fp-experiences'); ?>)</em></span>
                        <input 
                            type="number" 
                            name="<?php echo esc_attr($buffer_after_name); ?>" 
                            value="<?php echo esc_attr($buffer_after_value); ?>" 
                            min="0" 
                            step="1" 
                            placeholder="<?php echo esc_attr__('Usa buffer generale', 'fp-experiences'); ?>"
                        />
                    </label>
                </div>
            </div>
            <p class="fp-exp-repeater-row__actions">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    private function render_custom_slot_row(string $index, array $slot, bool $is_template = false): void
    {
        $prefix = 'fp_exp_availability[custom_slots][' . $index . ']';
        $date_name = $is_template ? 'fp_exp_availability[custom_slots][__INDEX__][date]' : $prefix . '[date]';
        $time_name = $is_template ? 'fp_exp_availability[custom_slots][__INDEX__][time]' : $prefix . '[time]';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <p>
                <label>
                    <?php esc_html_e('Data', 'fp-experiences'); ?>
                    <input type="date" <?php echo $this->field_name_attribute($date_name, $is_template); ?> value="<?php echo esc_attr((string) ($slot['date'] ?? '')); ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Orario', 'fp-experiences'); ?>
                    <input type="time" <?php echo $this->field_name_attribute($time_name, $is_template); ?> value="<?php echo esc_attr((string) ($slot['time'] ?? '')); ?>" />
                </label>
            </p>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    private function render_time_set_row(string $index, array $set, bool $is_template = false, string $frequency = 'weekly'): void
    {
        $label_name = $is_template
            ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][label]'
            : 'fp_exp_availability[recurrence][time_sets][' . $index . '][label]';
        $times_base = $is_template
            ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][times]'
            : 'fp_exp_availability[recurrence][time_sets][' . $index . '][times]';
        $capacity_name = $is_template
            ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][capacity]'
            : 'fp_exp_availability[recurrence][time_sets][' . $index . '][capacity]';
        $buffer_before_name = $is_template
            ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][buffer_before]'
            : 'fp_exp_availability[recurrence][time_sets][' . $index . '][buffer_before]';
        $buffer_after_name = $is_template
            ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][buffer_after]'
            : 'fp_exp_availability[recurrence][time_sets][' . $index . '][buffer_after]';

        $label_value = isset($set['label']) ? (string) $set['label'] : '';
        $times = [];
        $capacity_value = isset($set['capacity']) ? (string) absint((string) $set['capacity']) : '';
        $buffer_before_value = isset($set['buffer_before']) ? (string) absint((string) $set['buffer_before']) : '';
        $buffer_after_value = isset($set['buffer_after']) ? (string) absint((string) $set['buffer_after']) : '';

        if ('0' === $capacity_value) {
            $capacity_value = '';
        }
        if ('0' === $buffer_before_value) {
            $buffer_before_value = '';
        }
        if ('0' === $buffer_after_value) {
            $buffer_after_value = '';
        }

        if (isset($set['times']) && is_array($set['times'])) {
            foreach ($set['times'] as $time) {
                $times[] = (string) $time;
            }
        }

        if (empty($times)) {
            $times = [''];
        }

        $next_index = $is_template ? 1 : count($times);
        ?>
        <div
            class="fp-exp-repeater-row fp-exp-recurrence-set"
            data-repeater-item
            data-time-set
            data-time-set-next-index="<?php echo esc_attr((string) $next_index); ?>"
            data-time-set-base="<?php echo esc_attr($times_base); ?>"
        >
            <div class="fp-exp-recurrence-set__header">
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Nome set (opzionale)', 'fp-experiences'); ?></span>
                    <input type="text" <?php echo $this->field_name_attribute($label_name, $is_template); ?> value="<?php echo esc_attr($label_value); ?>" placeholder="<?php echo esc_attr__('Mattina', 'fp-experiences'); ?>" />
                </label>
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </div>
            <?php if ('weekly' === $frequency) : ?>
                <div class="fp-exp-field fp-exp-recurrence-set__days">
                    <span class="fp-exp-field__label"><?php esc_html_e('Giorni (override, opzionale)', 'fp-experiences'); ?></span>
                    <div class="fp-exp-checkbox-grid">
                        <?php foreach ($this->get_week_days() as $day_key => $day_label) : ?>
                            <label>
                                <input
                                    type="checkbox"
                                    <?php
                                    $name = $is_template
                                        ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][days][]'
                                        : 'fp_exp_availability[recurrence][time_sets][' . $index . '][days][]';
                                    echo $this->field_name_attribute($name, $is_template);
                                    ?>
                                    value="<?php echo esc_attr($day_key); ?>"
                                    <?php
                                    $current_days = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
                                    checked(in_array($this->map_weekday_for_ui($day_key), $current_days, true));
                                    ?>
                                />
                                <span><?php echo esc_html($day_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="fp-exp-recurrence-set__chips" data-time-set-chips>
                <?php foreach ($times as $time_index => $time_value) : ?>
                    <span class="fp-exp-chip" data-time-set-chip>
                        <label>
                            <span class="screen-reader-text"><?php esc_html_e('Orario ricorrenza', 'fp-experiences'); ?></span>
                            <input type="time" <?php echo $this->field_name_attribute($times_base . '[' . $time_index . ']', $is_template); ?> value="<?php echo esc_attr($time_value); ?>" />
                        </label>
                        <button type="button" class="fp-exp-chip__remove" data-time-set-remove aria-label="<?php echo esc_attr__('Rimuovi orario', 'fp-experiences'); ?>">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="fp-exp-recurrence-set__actions">
                <button type="button" class="button button-secondary" data-time-set-add><?php esc_html_e('Aggiungi orario', 'fp-experiences'); ?></button>
            </p>
            <div class="fp-exp-field fp-exp-field--columns fp-exp-recurrence-set__metrics">
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Capienza slot', 'fp-experiences'); ?></span>
                    <input type="number" min="0" step="1" <?php echo $this->field_name_attribute($capacity_name, $is_template); ?> value="<?php echo esc_attr($capacity_value); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Buffer prima (minuti)', 'fp-experiences'); ?></span>
                    <input type="number" min="0" step="1" <?php echo $this->field_name_attribute($buffer_before_name, $is_template); ?> value="<?php echo esc_attr($buffer_before_value); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Buffer dopo (minuti)', 'fp-experiences'); ?></span>
                    <input type="number" min="0" step="1" <?php echo $this->field_name_attribute($buffer_after_name, $is_template); ?> value="<?php echo esc_attr($buffer_after_value); ?>" />
                </label>
            </div>
        </div>
        <?php
    }
    private function render_faq_row(string $index, array $item, bool $is_template = false): void
    {
        $prefix = 'fp_exp_policy[faq][' . $index . ']';
        $question_name = $is_template ? 'fp_exp_policy[faq][__INDEX__][question]' : $prefix . '[question]';
        $answer_name = $is_template ? 'fp_exp_policy[faq][__INDEX__][answer]' : $prefix . '[answer]';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item draggable="true">
            <div class="fp-exp-repeater-row__fields">
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Domanda', 'fp-experiences'); ?></span>
                    <input type="text" <?php echo $this->field_name_attribute($question_name, $is_template); ?> value="<?php echo esc_attr((string) ($item['question'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Qual è il punto di ritrovo?', 'fp-experiences'); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Risposta', 'fp-experiences'); ?></span>
                    <textarea rows="3" <?php echo $this->field_name_attribute($answer_name, $is_template); ?>><?php echo esc_textarea((string) ($item['answer'] ?? '')); ?></textarea>
                </label>
            </div>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }
    private function field_name_attribute(string $name, bool $is_template): string
    {
        if ($is_template) {
            return 'data-name="' . esc_attr($name) . '"';
        }

        return 'name="' . esc_attr($name) . '"';
    }
    /**
     * @deprecated Use DetailsMetaBoxHandler::save() instead
     * @param mixed $raw
     */
    private function save_details_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            return;
        }

        $short_desc = isset($raw['short_desc']) ? sanitize_text_field((string) $raw['short_desc']) : '';
        $duration = isset($raw['duration_minutes']) ? absint((string) $raw['duration_minutes']) : 0;
        $min_party = isset($raw['min_party']) ? absint((string) $raw['min_party']) : 0;
        $capacity_slot = isset($raw['capacity_slot']) ? absint((string) $raw['capacity_slot']) : 0;
        $age_min = isset($raw['age_min']) ? absint((string) $raw['age_min']) : 0;
        $age_max = isset($raw['age_max']) ? absint((string) $raw['age_max']) : 0;
        $rules_children = isset($raw['rules_children']) ? sanitize_text_field((string) $raw['rules_children']) : '';
        $hero_id = isset($raw['hero_image_id']) ? absint((string) $raw['hero_image_id']) : 0;
        if ($hero_id > 0 && ! wp_attachment_is_image($hero_id)) {
            $hero_id = 0;
        }

        $cognitive_biases = isset($raw['cognitive_biases']) && is_array($raw['cognitive_biases'])
            ? array_values(array_filter(array_map('sanitize_key', $raw['cognitive_biases'])))
            : [];
        $cognitive_biases = array_values(array_unique($cognitive_biases));
        $cognitive_biases = array_slice($cognitive_biases, 0, Helpers::cognitive_bias_max_selection());

        $experience_badges = isset($raw['experience_badges']) && is_array($raw['experience_badges'])
            ? array_values(array_filter(array_map('sanitize_key', $raw['experience_badges'])))
            : [];
        $experience_badges = array_values(array_unique($experience_badges));
        $available_badges = Helpers::experience_badge_choices();
        $experience_badges = array_values(array_filter($experience_badges, static function (string $badge) use ($available_badges): bool {
            return isset($available_badges[$badge]);
        }));

        // Overrides per titolo/descrizione dei badge selezionati
        $badge_overrides_input = isset($raw['experience_badges_overrides']) && is_array($raw['experience_badges_overrides'])
            ? $raw['experience_badges_overrides']
            : [];
        $badge_overrides = [];
        foreach ($badge_overrides_input as $badge_id => $entry) {
            $id = sanitize_key((string) $badge_id);
            if ('' === $id || ! is_array($entry)) {
                continue;
            }
            $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
            $desc = isset($entry['description']) ? sanitize_text_field((string) $entry['description']) : '';
            if ('' === $label && '' === $desc) {
                continue;
            }
            $payload = [];
            if ('' !== $label) {
                $payload['label'] = $label;
            }
            if (array_key_exists('description', $entry)) {
                $payload['description'] = $desc;
            }
            if (! empty($payload)) {
                $badge_overrides[$id] = $payload;
            }
        }

        // Badge personalizzati per questa esperienza
        $custom_input = isset($raw['experience_badges_custom']) && is_array($raw['experience_badges_custom'])
            ? $raw['experience_badges_custom']
            : [];
        $custom_badges = [];
        $seen_custom = [];
        foreach ($custom_input as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $clabel = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
            if ('' === $clabel) {
                continue;
            }
            $cid = isset($entry['id']) ? sanitize_key((string) $entry['id']) : '';
            // Genera automaticamente un ID se non fornito
            if ('' === $cid) {
                $cid = sanitize_key($clabel);
            }
            if ('' === $cid) {
                continue;
            }
            if (isset($seen_custom[$cid])) {
                continue;
            }
            $seen_custom[$cid] = true;
            $cdesc = isset($entry['description']) ? sanitize_text_field((string) $entry['description']) : '';
            $custom_badges[] = [
                'id' => $cid,
                'label' => $clabel,
                'description' => $cdesc,
            ];
        }

        $gallery_raw = $raw['gallery_ids'] ?? '';
        $gallery_candidates = [];

        if (is_array($gallery_raw)) {
            $gallery_candidates = $gallery_raw;
        } elseif (is_string($gallery_raw) && '' !== trim($gallery_raw)) {
            $gallery_candidates = explode(',', $gallery_raw);
        }

        $gallery_ids = [];
        foreach ($gallery_candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = reset($candidate);
            }

            $candidate_id = absint((string) $candidate);
            if ($candidate_id > 0 && wp_attachment_is_image($candidate_id)) {
                $gallery_ids[] = $candidate_id;
            }
        }

        $gallery_ids = array_values(array_unique($gallery_ids));

        $gallery_video_url = isset($raw['gallery_video_url']) ? esc_url_raw((string) $raw['gallery_video_url']) : '';

        $this->update_or_delete_meta($post_id, '_fp_short_desc', $short_desc);
        $this->update_or_delete_meta($post_id, '_fp_duration_minutes', $duration);
        $this->update_or_delete_meta($post_id, '_fp_min_party', $min_party);
        $this->update_or_delete_meta($post_id, '_fp_capacity_slot', $capacity_slot);
        $this->update_or_delete_meta($post_id, '_fp_age_min', $age_min);
        $this->update_or_delete_meta($post_id, '_fp_age_max', $age_max);
        $this->update_or_delete_meta($post_id, '_fp_rules_children', $rules_children);
        $this->update_or_delete_meta($post_id, '_fp_cognitive_biases', $cognitive_biases);
        $this->update_or_delete_meta($post_id, '_fp_experience_badges', $experience_badges);
        $this->update_or_delete_meta($post_id, '_fp_experience_badge_overrides', $badge_overrides);
        $this->update_or_delete_meta($post_id, '_fp_experience_badge_custom', $custom_badges);
        $this->update_or_delete_meta($post_id, '_fp_gallery_ids', $gallery_ids);
        $this->update_or_delete_meta($post_id, '_fp_gallery_video_url', $gallery_video_url);

        $language_selected = isset($raw['languages']) && is_array($raw['languages'])
            ? array_values(array_filter(array_map('absint', $raw['languages'])))
            : [];
        $language_manual_labels = isset($raw['languages_manual'])
            ? $this->parse_manual_taxonomy_input($raw['languages_manual'])
            : [];

        if (! empty($language_manual_labels)) {
            $language_selected = array_merge(
                $language_selected,
                $this->ensure_taxonomy_terms($language_manual_labels, 'fp_exp_language')
            );
        }

        $language_selected = array_values(array_unique(array_filter(array_map('absint', $language_selected))));

        if ($hero_id > 0) {
            update_post_meta($post_id, '_fp_hero_image_id', $hero_id);
            set_post_thumbnail($post_id, $hero_id);
        } else {
            delete_post_meta($post_id, '_fp_hero_image_id');
            delete_post_thumbnail($post_id);
        }

        wp_set_post_terms($post_id, $language_selected, 'fp_exp_language', false);

        $language_terms = $this->get_assigned_terms($post_id, 'fp_exp_language');
        $language_names = $this->get_term_names_by_ids($language_terms, 'fp_exp_language');
        $this->update_or_delete_meta($post_id, '_fp_languages', $language_names);
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function parse_manual_taxonomy_input($raw): array
    {
        if (is_array($raw)) {
            $raw = implode(',', array_map('strval', $raw));
        }

        $raw = trim((string) $raw);
        if ('' === $raw) {
            return [];
        }

        $labels = [];
        foreach (explode(',', $raw) as $part) {
            $label = sanitize_text_field(trim((string) $part));
            if ('' !== $label) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, int>
     */
    private function ensure_taxonomy_terms(array $labels, string $taxonomy): array
    {
        $term_ids = [];

        foreach ($labels as $label) {
            $term_id = 0;
            $existing = term_exists($label, $taxonomy);

            if (is_array($existing)) {
                $term_id = isset($existing['term_id']) ? (int) $existing['term_id'] : 0;
            } elseif (is_int($existing)) {
                $term_id = $existing;
            }

            if ($term_id <= 0) {
                $created = wp_insert_term($label, $taxonomy);
                if ($created instanceof WP_Error) {
                    continue;
                }

                $term_id = isset($created['term_id']) ? (int) $created['term_id'] : 0;
            }

            if ($term_id > 0) {
                $term_ids[] = $term_id;
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $term_ids))));
    }

    /**
     * @param array<string|int, array<string, mixed>> $raw
     * @return array<int, array{id: int, name: string, description: string}>
     */
    private function sanitize_taxonomy_term_updates(array $raw): array
    {
        $entries = [];

        foreach ($raw as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $term_id = isset($entry['id']) ? absint((string) $entry['id']) : absint((string) $key);
            $name = isset($entry['name']) ? sanitize_text_field((string) $entry['name']) : '';
            $description = isset($entry['description']) ? sanitize_textarea_field((string) $entry['description']) : '';

            if ($term_id <= 0 || '' === $name) {
                continue;
            }

            $entries[] = [
                'id' => $term_id,
                'name' => $name,
                'description' => $description,
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $raw
     * @return array<int, array{name: string, description: string}>
     */
    private function sanitize_taxonomy_new_entries(array $raw): array
    {
        $entries = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = isset($entry['name']) ? sanitize_text_field((string) $entry['name']) : '';
            $description = isset($entry['description']) ? sanitize_textarea_field((string) $entry['description']) : '';

            if ('' === $name) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'description' => $description,
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, array{id: int, name: string, description: string}> $entries
     */
    private function update_taxonomy_term_details(array $entries, string $taxonomy): void
    {
        foreach ($entries as $entry) {
            $term_id = $entry['id'];
            $name = $entry['name'];
            $description = $entry['description'];

            $term = get_term($term_id, $taxonomy);

            if (! $term || is_wp_error($term)) {
                continue;
            }

            $current_name = sanitize_text_field((string) $term->name);
            $current_description = sanitize_textarea_field((string) $term->description);

            $args = [];

            if ($name !== $current_name) {
                $args['name'] = $name;
            }

            if ($description !== $current_description) {
                $args['description'] = $description;
            }

            if (empty($args)) {
                continue;
            }

            wp_update_term($term_id, $taxonomy, $args);
        }
    }

    /**
     * @param array<int, array{name: string, description: string}> $entries
     * @return array<int, int>
     */
    private function create_taxonomy_terms_with_details(array $entries, string $taxonomy): array
    {
        $term_ids = [];

        foreach ($entries as $entry) {
            $name = $entry['name'];
            $description = $entry['description'];

            $term_id = 0;
            $existing = term_exists($name, $taxonomy);

            if (is_array($existing)) {
                $term_id = isset($existing['term_id']) ? (int) $existing['term_id'] : 0;
            } elseif (is_int($existing)) {
                $term_id = $existing;
            }

            if ($term_id > 0) {
                wp_update_term($term_id, $taxonomy, [
                    'description' => $description,
                ]);

                $term_ids[] = $term_id;
                continue;
            }

            $created = wp_insert_term($name, $taxonomy, [
                'description' => $description,
            ]);

            if ($created instanceof WP_Error) {
                continue;
            }

            $term_id = isset($created['term_id']) ? (int) $created['term_id'] : 0;

            if ($term_id > 0) {
                $term_ids[] = $term_id;
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $term_ids))));
    }

    private function save_pricing_meta(int $post_id, $raw): string
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_exp_pricing');
            delete_post_meta($post_id, '_fp_ticket_types');
            delete_post_meta($post_id, '_fp_addons');
            return 'warning';
        }

        $pricing = [
            'tickets' => [],
            'group' => [],
            'addons' => [],
            'tax_class' => '',
        ];

        $legacy_tickets = [];
        $has_ticket = false;

        if (isset($raw['tickets']) && is_array($raw['tickets'])) {
            foreach ($raw['tickets'] as $ticket) {
                if (! is_array($ticket)) {
                    continue;
                }

                $label = isset($ticket['label']) ? sanitize_text_field((string) $ticket['label']) : '';
                $price = isset($ticket['price']) ? max(0.0, (float) $ticket['price']) : 0.0;
                $capacity = isset($ticket['capacity']) ? absint((string) $ticket['capacity']) : 0;
                $slug = isset($ticket['slug']) ? sanitize_key((string) $ticket['slug']) : '';
                $use_as_price_from = ! empty($ticket['use_as_price_from']);
                if ('' === $slug && '' !== $label) {
                    $slug = sanitize_key($label);
                }

                if ('' === $label || '' === $slug) {
                    continue;
                }

                $pricing['tickets'][] = [
                    'label' => $label,
                    'price' => $price,
                    'capacity' => $capacity,
                    'slug' => $slug,
                    'use_as_price_from' => $use_as_price_from,
                ];

                $legacy_tickets[] = [
                    'slug' => $slug,
                    'label' => $label,
                    'price' => $price,
                    'min' => 0,
                    'max' => $capacity,
                    'capacity' => $capacity,
                    'description' => '',
                    'use_as_price_from' => $use_as_price_from,
                ];

                if ($price > 0) {
                    $has_ticket = true;
                }
            }
        }

        $group = $raw['group'] ?? [];
        if (is_array($group)) {
            $group_price = isset($group['price']) ? max(0.0, (float) $group['price']) : 0.0;
            $group_capacity = isset($group['capacity']) ? absint((string) $group['capacity']) : 0;
            if ($group_price > 0 || $group_capacity > 0) {
                $pricing['group'] = [
                    'price' => $group_price,
                    'capacity' => $group_capacity,
                ];
            }
        }

        $legacy_addons = [];
        if (isset($raw['addons']) && is_array($raw['addons'])) {
            foreach ($raw['addons'] as $index => $addon) {
                if (! is_array($addon)) {
                    continue;
                }

                $name = isset($addon['name']) ? sanitize_text_field((string) $addon['name']) : '';
                $price = isset($addon['price']) ? max(0.0, (float) $addon['price']) : 0.0;
                $type = isset($addon['type']) ? sanitize_key((string) $addon['type']) : 'person';
                $slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';
                $image_id = isset($addon['image_id']) ? absint((string) $addon['image_id']) : 0;
                $description = isset($addon['description']) ? sanitize_text_field((string) $addon['description']) : '';
                $selection_type = isset($addon['selection_type']) ? sanitize_key((string) $addon['selection_type']) : 'checkbox';
                $selection_group = isset($addon['selection_group']) ? sanitize_text_field((string) $addon['selection_group']) : '';
                if ($image_id > 0 && ! wp_attachment_is_image($image_id)) {
                    $image_id = 0;
                }
                
                // Generate slug from name if empty
                if ('' === $slug && '' !== $name) {
                    $slug = sanitize_key($name);
                }
                
                // Ensure unique slug by appending index if needed
                if ('' !== $slug) {
                    $existing_slugs = array_column($legacy_addons, 'slug');
                    if (in_array($slug, $existing_slugs, true)) {
                        $slug = $slug . '-' . $index;
                    }
                }

                // Skip only if both name and slug are empty
                if ('' === $name && '' === $slug) {
                    continue;
                }

                if (! in_array($type, ['person', 'booking'], true)) {
                    $type = 'person';
                }

                if (! in_array($selection_type, ['checkbox', 'radio'], true)) {
                    $selection_type = 'checkbox';
                }

                $pricing['addons'][] = [
                    'name' => $name,
                    'price' => $price,
                    'type' => $type,
                    'slug' => $slug,
                    'image_id' => $image_id,
                    'description' => $description,
                    'selection_type' => $selection_type,
                    'selection_group' => $selection_group,
                ];

                $legacy_addons[] = [
                    'slug' => $slug,
                    'label' => $name,
                    'price' => $price,
                    'allow_multiple' => 'booking' !== $type,
                    'max' => 0,
                    'description' => $description,
                    'image_id' => $image_id,
                    'selection_type' => $selection_type,
                    'selection_group' => $selection_group,
                ];
            }
        }

        $tax_class = isset($raw['tax_class']) ? sanitize_key((string) $raw['tax_class']) : '';
        if ('standard' === $tax_class) {
            $tax_class = '';
        }
        $pricing['tax_class'] = $tax_class;

        // Salva base_price
        $base_price = isset($raw['base_price']) ? max(0.0, (float) $raw['base_price']) : 0.0;
        $pricing['base_price'] = $base_price;
        
        // Salva anche in _fp_base_price per retrocompatibilità con Pricing::get_base_price()
        if ($base_price > 0) {
            update_post_meta($post_id, '_fp_base_price', (string) $base_price);
        } else {
            delete_post_meta($post_id, '_fp_base_price');
        }

        if (! empty($pricing['tickets']) || ! empty($pricing['group']) || ! empty($pricing['addons']) || '' !== $pricing['tax_class'] || $base_price > 0) {
            update_post_meta($post_id, '_fp_exp_pricing', $pricing);
        } else {
            delete_post_meta($post_id, '_fp_exp_pricing');
        }

        if (! empty($legacy_tickets)) {
            update_post_meta($post_id, '_fp_ticket_types', $legacy_tickets);
        } else {
            delete_post_meta($post_id, '_fp_ticket_types');
        }

        if (! empty($legacy_addons)) {
            update_post_meta($post_id, '_fp_addons', $legacy_addons);
        } else {
            delete_post_meta($post_id, '_fp_addons');
        }

        return $has_ticket ? 'success' : 'warning';
    }
    private function save_availability_meta(int $post_id, $raw): array
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_exp_availability');
            $this->update_or_delete_meta($post_id, '_fp_lead_time_hours', 0);
            $this->update_or_delete_meta($post_id, '_fp_buffer_before_minutes', 0);
            $this->update_or_delete_meta($post_id, '_fp_buffer_after_minutes', 0);
            delete_post_meta($post_id, '_fp_exp_recurrence');

            return [
                'availability' => [],
                'recurrence' => Recurrence::defaults(),
            ];
        }

        // Simplified system: always weekly
        $frequency = 'weekly';

        $slot_capacity = isset($raw['slot_capacity']) ? absint((string) $raw['slot_capacity']) : 0;
        $lead_time = isset($raw['lead_time_hours']) ? absint((string) $raw['lead_time_hours']) : 0;
        $buffer_before = isset($raw['buffer_before_minutes']) ? absint((string) $raw['buffer_before_minutes']) : 0;
        $buffer_after = isset($raw['buffer_after_minutes']) ? absint((string) $raw['buffer_after_minutes']) : 0;

        $availability = [
            'frequency' => $frequency,
            'slot_capacity' => $slot_capacity,
            'lead_time_hours' => $lead_time,
            'buffer_before_minutes' => $buffer_before,
            'buffer_after_minutes' => $buffer_after,
        ];

        update_post_meta($post_id, '_fp_exp_availability', $availability);

        // Sanitize recurrence data (simplified format)
        $recurrence_raw = isset($raw['recurrence']) && is_array($raw['recurrence']) ? $raw['recurrence'] : [];
        $recurrence_meta = Recurrence::sanitize($recurrence_raw);

        // Salva solo se ci sono dati significativi (giorni e time_slots)
        $has_data = !empty($recurrence_meta['days']) && !empty($recurrence_meta['time_slots']);
        
        if ($has_data) {
            update_post_meta($post_id, '_fp_exp_recurrence', $recurrence_meta);
        } else {
            delete_post_meta($post_id, '_fp_exp_recurrence');
        }
        
        // NOTE: sync_recurrence_to_availability() can overwrite _fp_exp_availability
        // We already saved it above (line 2577), so we skip this to preserve slot_capacity
        // The frontend can read times from _fp_exp_recurrence if needed
        // $this->sync_recurrence_to_availability($post_id, $recurrence_meta, $slot_capacity, $lead_time, $buffer_before, $buffer_after);

        $this->update_or_delete_meta($post_id, '_fp_lead_time_hours', $lead_time);
        $this->update_or_delete_meta($post_id, '_fp_buffer_before_minutes', $buffer_before);
        $this->update_or_delete_meta($post_id, '_fp_buffer_after_minutes', $buffer_after);

        return [
            'availability' => $availability,
            'recurrence' => $recurrence_meta,
        ];
    }

    /**
     * Sincronizza i dati di ricorrenza nel formato legacy per AvailabilityService.
     *
     * @param int   $post_id
     * @param array $recurrence
     * @param int   $slot_capacity
     * @param int   $lead_time
     * @param int   $buffer_before
     * @param int   $buffer_after
     */
    private function sync_recurrence_to_availability(int $post_id, array $recurrence, int $slot_capacity, int $lead_time, int $buffer_before, int $buffer_after): void
    {
        // Log per debug (può essere rimosso in produzione)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP_EXP: Syncing recurrence to availability for post %d. Slot capacity: %d',
                $post_id,
                $slot_capacity
            ));
        }
        
        // Estrai tutti gli orari dai time_slots (nuovo formato) o time_sets (vecchio formato)
        $all_times = [];
        $all_days = [];
        
        // Supporta sia il nuovo formato time_slots che il vecchio time_sets
        $slots_data = isset($recurrence['time_slots']) && is_array($recurrence['time_slots']) 
            ? $recurrence['time_slots'] 
            : (isset($recurrence['time_sets']) && is_array($recurrence['time_sets']) ? $recurrence['time_sets'] : []);
        
        if (!empty($slots_data)) {
            foreach ($slots_data as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                
                // Nuovo formato time_slots: singolo campo 'time'
                if (isset($slot['time'])) {
                    $time_str = trim((string) $slot['time']);
                    if ($time_str !== '' && !in_array($time_str, $all_times, true)) {
                        $all_times[] = $time_str;
                    }
                }
                // Vecchio formato time_sets: array 'times'
                elseif (isset($slot['times']) && is_array($slot['times'])) {
                    foreach ($slot['times'] as $time) {
                        $time_str = trim((string) $time);
                        if ($time_str !== '' && !in_array($time_str, $all_times, true)) {
                            $all_times[] = $time_str;
                        }
                    }
                }
                
                // Raccogli i giorni (per ricorrenze settimanali)
                if (isset($slot['days']) && is_array($slot['days'])) {
                    foreach ($slot['days'] as $day) {
                        $day_str = trim((string) $day);
                        if ($day_str !== '' && !in_array($day_str, $all_days, true)) {
                            $all_days[] = $day_str;
                        }
                    }
                }
            }
        }
        
        // Se non ci sono giorni specificati negli slot, usa i giorni globali
        if (empty($all_days) && isset($recurrence['days']) && is_array($recurrence['days'])) {
            $all_days = $recurrence['days'];
        }
        
        // Log per debug (può essere rimosso in produzione)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP_EXP: Extracted %d times and %d days from slots. Times: [%s], Days: [%s]',
                count($all_times),
                count($all_days),
                implode(', ', $all_times),
                implode(', ', $all_days)
            ));
        }
        
        // Determina la frequenza
        $frequency = isset($recurrence['frequency']) ? (string) $recurrence['frequency'] : 'weekly';
        if (!in_array($frequency, ['daily', 'weekly', 'custom'], true)) {
            $frequency = 'weekly';
        }
        
        // Se la frequenza è 'specific', convertila in 'custom' per compatibilità
        if ($frequency === 'specific') {
            $frequency = 'custom';
        }
        
        // Costruisci l'array di availability in formato legacy
        $availability = [
            'frequency' => $frequency,
            'times' => $all_times,
            'days_of_week' => $all_days,
            'custom_slots' => [],
            'slot_capacity' => $slot_capacity,
            'lead_time_hours' => $lead_time,
            'buffer_before_minutes' => $buffer_before,
            'buffer_after_minutes' => $buffer_after,
            'start_date' => isset($recurrence['start_date']) ? sanitize_text_field((string) $recurrence['start_date']) : '',
            'end_date' => isset($recurrence['end_date']) ? sanitize_text_field((string) $recurrence['end_date']) : '',
        ];
        
        // Pulisci i campi non necessari in base alla frequenza
        if ($frequency !== 'weekly') {
            $availability['days_of_week'] = [];
        }
        
        if ($frequency === 'custom') {
            $availability['times'] = [];
        }
        
        // Salva sempre availability se c'è QUALSIASI configurazione
        // Anche solo slot_capacity, lead_time, o buffer devono essere preservati
        $has_config = !empty($availability['times']) 
            || !empty($availability['custom_slots']) 
            || $slot_capacity > 0
            || $lead_time > 0
            || $buffer_before > 0
            || $buffer_after > 0;
            
        if ($has_config) {
            update_post_meta($post_id, '_fp_exp_availability', $availability);
        } else {
            // Solo se NESSUN campo è configurato, cancella il meta
            delete_post_meta($post_id, '_fp_exp_availability');
        }
    }

    private function maybe_generate_recurrence_slots(int $post_id, array $data): void
    {
        $recurrence = isset($data['recurrence']) && is_array($data['recurrence']) ? $data['recurrence'] : [];

        if (empty($recurrence) || ! Recurrence::is_actionable($recurrence)) {
            return;
        }

        $availability_defaults = [
            'slot_capacity' => 0,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'capacity_per_type' => [],
            'resource_lock' => [],
            'price_rules' => [],
        ];

        $availability = isset($data['availability']) && is_array($data['availability'])
            ? array_merge($availability_defaults, $data['availability'])
            : $availability_defaults;

        $rules = Recurrence::build_rules($recurrence, [
            'slot_capacity' => $availability['slot_capacity'],
            'buffer_before_minutes' => $availability['buffer_before_minutes'],
            'buffer_after_minutes' => $availability['buffer_after_minutes'],
            'capacity_per_type' => $availability['capacity_per_type'],
            'resource_lock' => $availability['resource_lock'],
            'price_rules' => $availability['price_rules'],
        ]);

        if (empty($rules)) {
            return;
        }

        $options = [
            'default_duration' => isset($recurrence['duration']) ? absint((string) $recurrence['duration']) : 60,
            'default_capacity' => absint((string) $availability['slot_capacity']),
            'buffer_before' => absint((string) $availability['buffer_before_minutes']),
            'buffer_after' => absint((string) $availability['buffer_after_minutes']),
            'replace_existing' => true,
        ];

        Slots::generate_recurring_slots($post_id, $rules, [], $options);
    }
    /**
     * @deprecated Use MeetingPointMetaBoxHandler::save() instead
     * @param mixed $raw
     */
    private function save_meeting_point_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_meeting_point_id');
            delete_post_meta($post_id, '_fp_meeting_point_alt');
            delete_post_meta($post_id, '_fp_meeting_point');
            return;
        }

        $primary_id = isset($raw['primary']) ? absint((string) $raw['primary']) : 0;
        $alternatives = [];

        if (isset($raw['alternatives']) && is_array($raw['alternatives'])) {
            foreach ($raw['alternatives'] as $value) {
                $alt_id = absint((string) $value);
                if ($alt_id > 0 && $alt_id !== $primary_id) {
                    $alternatives[] = $alt_id;
                }
            }
        }

        $alternatives = array_values(array_unique($alternatives));

        if ($primary_id > 0) {
            update_post_meta($post_id, '_fp_meeting_point_id', $primary_id);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point_id');
        }

        if (! empty($alternatives)) {
            update_post_meta($post_id, '_fp_meeting_point_alt', $alternatives);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point_alt');
        }

        $summary = Repository::get_primary_summary_for_experience($post_id, $primary_id);
        if ($summary) {
            update_post_meta($post_id, '_fp_meeting_point', $summary);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point');
        }
    }
    /**
     * @deprecated Use ExtrasMetaBoxHandler::save() instead
     * @param mixed $raw
     */
    private function save_extras_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_highlights');
            delete_post_meta($post_id, '_fp_inclusions');
            delete_post_meta($post_id, '_fp_exclusions');
            delete_post_meta($post_id, '_fp_what_to_bring');
            delete_post_meta($post_id, '_fp_notes');
            return;
        }

        $highlights = isset($raw['highlights']) ? $this->lines_to_array($raw['highlights']) : [];
        $inclusions = isset($raw['inclusions']) ? $this->lines_to_array($raw['inclusions']) : [];
        $exclusions = isset($raw['exclusions']) ? $this->lines_to_array($raw['exclusions']) : [];
        $what_to_bring = isset($raw['what_to_bring']) ? $this->lines_to_array($raw['what_to_bring']) : [];
        $notes = isset($raw['notes']) ? $this->lines_to_array($raw['notes']) : [];

        $this->update_or_delete_meta($post_id, '_fp_highlights', $highlights);
        $this->update_or_delete_meta($post_id, '_fp_inclusions', $inclusions);
        $this->update_or_delete_meta($post_id, '_fp_exclusions', $exclusions);
        $this->update_or_delete_meta($post_id, '_fp_what_to_bring', $what_to_bring);
        $this->update_or_delete_meta($post_id, '_fp_notes', $notes);
    }
    /**
     * @deprecated Use PolicyMetaBoxHandler::save() instead
     * @param mixed $raw
     */
    private function save_policy_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_policy_cancel');
            delete_post_meta($post_id, '_fp_faq');
            return;
        }

        $policy = isset($raw['cancel']) ? wp_kses_post((string) $raw['cancel']) : '';
        $faq = [];

        if (isset($raw['faq']) && is_array($raw['faq'])) {
            foreach ($raw['faq'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $question = isset($item['question']) ? sanitize_text_field((string) $item['question']) : '';
                $answer = isset($item['answer']) ? wp_kses_post((string) $item['answer']) : '';

                if ('' === $question || '' === $answer) {
                    continue;
                }

                $faq[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        $this->update_or_delete_meta($post_id, '_fp_policy_cancel', $policy);
        $this->update_or_delete_meta($post_id, '_fp_faq', $faq);
    }
    /**
     * @deprecated Use SEOMetaBoxHandler::save() instead
     * @param mixed $raw
     */
    private function save_seo_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_meta_title');
            delete_post_meta($post_id, '_fp_meta_description');
            delete_post_meta($post_id, '_fp_schema_manual');
            return;
        }

        $meta_title = isset($raw['meta_title']) ? sanitize_text_field((string) $raw['meta_title']) : '';
        $meta_description = isset($raw['meta_description']) ? sanitize_text_field((string) $raw['meta_description']) : '';
        $schema_json = isset($raw['schema_json']) ? trim((string) $raw['schema_json']) : '';

        $this->update_or_delete_meta($post_id, '_fp_meta_title', $meta_title);
        $this->update_or_delete_meta($post_id, '_fp_meta_description', $meta_description);
        $this->update_or_delete_meta($post_id, '_fp_schema_manual', $schema_json);
    }
    /**
     * @deprecated Use DetailsMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_details_meta(int $post_id): array
    {
        $language_selected = $this->get_assigned_terms($post_id, 'fp_exp_language');
        $language_names = $this->get_term_names_by_ids($language_selected, 'fp_exp_language');

        return [
            'short_desc' => sanitize_text_field((string) get_post_meta($post_id, '_fp_short_desc', true)),
            'duration_minutes' => absint((string) get_post_meta($post_id, '_fp_duration_minutes', true)),
            'language_badges' => LanguageHelper::build_language_badges($language_names),
            'languages' => [
                'choices' => $this->get_taxonomy_choices('fp_exp_language'),
                'selected' => $language_selected,
            ],
            'linked_page' => $this->get_linked_page_details($post_id),
            'min_party' => absint((string) get_post_meta($post_id, '_fp_min_party', true)),
            'capacity_slot' => absint((string) get_post_meta($post_id, '_fp_capacity_slot', true)),
            'age_min' => absint((string) get_post_meta($post_id, '_fp_age_min', true)),
            'age_max' => absint((string) get_post_meta($post_id, '_fp_age_max', true)),
            'rules_children' => sanitize_text_field((string) get_post_meta($post_id, '_fp_rules_children', true)),
            'hero_image' => $this->get_hero_image($post_id),
            'gallery' => $this->get_gallery_for_editor($post_id),
            'gallery_video_url' => esc_url((string) get_post_meta($post_id, '_fp_gallery_video_url', true)),
            'cognitive_biases' => [
                'choices' => Helpers::cognitive_bias_choices(),
                'selected' => $this->get_selected_cognitive_biases($post_id),
            ],
            'experience_badges' => [
                'choices' => Helpers::experience_badge_choices(),
                'selected' => $this->get_selected_experience_badges($post_id),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function get_selected_experience_badges(int $post_id): array
    {
        $stored = get_post_meta($post_id, '_fp_experience_badges', true);

        if (! is_array($stored)) {
            $stored = [];
        }

        $badges = array_map(static fn ($badge): string => sanitize_key((string) $badge), $stored);
        $badges = array_values(array_unique(array_filter($badges)));

        $available = Helpers::experience_badge_choices();

        return array_values(array_filter($badges, static function (string $badge) use ($available): bool {
            return isset($available[$badge]);
        }));
    }

    /**
     * @param array<int, int> $term_ids
     * @return array<int, string>
     */
    private function get_term_names_by_ids(array $term_ids, string $taxonomy): array
    {
        if (empty($term_ids)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'include' => $term_ids,
        ]);

        if (! is_array($terms) || is_wp_error($terms)) {
            return [];
        }

        $names_by_id = [];
        foreach ($terms as $term) {
            if (! isset($term->term_id)) {
                continue;
            }

            $names_by_id[(int) $term->term_id] = sanitize_text_field((string) $term->name);
        }

        $ordered = [];
        foreach ($term_ids as $term_id) {
            if (isset($names_by_id[$term_id]) && '' !== $names_by_id[$term_id]) {
                $ordered[] = $names_by_id[$term_id];
            }
        }

        return array_values(array_unique($ordered));
    }

    private function get_hero_image(int $post_id): array
    {
        $image_id = absint((string) get_post_meta($post_id, '_fp_hero_image_id', true));

        if ($image_id <= 0) {
            $gallery_ids = get_post_meta($post_id, '_fp_gallery_ids', true);
            if (is_array($gallery_ids)) {
                foreach ($gallery_ids as $candidate) {
                    $maybe_id = absint($candidate);
                    if ($maybe_id > 0) {
                        $image_id = $maybe_id;
                        break;
                    }
                }
            }
        }

        if ($image_id <= 0) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $image_id = $thumbnail_id ? (int) $thumbnail_id : 0;
        }

        if ($image_id <= 0) {
            return ['id' => 0, 'url' => '', 'width' => 0, 'height' => 0];
        }

        $image = wp_get_attachment_image_src($image_id, 'large');

        return [
            'id' => $image_id,
            'url' => $image ? (string) ($image[0] ?? '') : '',
            'width' => $image ? absint((string) ($image[1] ?? 0)) : 0,
            'height' => $image ? absint((string) ($image[2] ?? 0)) : 0,
        ];
    }

    private function get_gallery_for_editor(int $post_id): array
    {
        $stored = get_post_meta($post_id, '_fp_gallery_ids', true);

        if (! is_array($stored)) {
            $stored = [];
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $stored))));
        $items = [];

        foreach ($ids as $image_id) {
            if ($image_id <= 0 || ! wp_attachment_is_image($image_id)) {
                continue;
            }

            $source = wp_get_attachment_image_src($image_id, 'medium');
            $url = $source ? (string) ($source[0] ?? '') : '';
            $width = $source ? absint((string) ($source[1] ?? 0)) : 0;
            $height = $source ? absint((string) ($source[2] ?? 0)) : 0;

            if ('' === $url) {
                $fallback = wp_get_attachment_url($image_id);
                $url = $fallback ? (string) $fallback : '';
            }

            $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            if (! is_string($alt)) {
                $alt = '';
            }

            if ('' === $alt) {
                $alt = get_the_title($image_id);
            }

            $items[] = [
                'id' => $image_id,
                'url' => $url,
                'width' => $width,
                'height' => $height,
                'alt' => $alt ? (string) $alt : '',
            ];
        }

        return [
            'ids' => $ids,
            'items' => $items,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function get_selected_cognitive_biases(int $post_id): array
    {
        $stored = get_post_meta($post_id, '_fp_cognitive_biases', true);

        if (! is_array($stored)) {
            return [];
        }

        $valid = array_map(static fn ($choice) => (string) $choice['id'], Helpers::cognitive_bias_choices());

        return array_values(array_filter(array_map(static function ($item) use ($valid) {
            $key = sanitize_key((string) $item);

            return in_array($key, $valid, true) ? $key : '';
        }, $stored)));
    }

    private function get_taxonomy_choices(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (! is_array($terms) || is_wp_error($terms)) {
            return [];
        }

        return array_map(static function ($term) {
            return [
                'id' => (int) $term->term_id,
                'label' => sanitize_text_field((string) $term->name),
                'description' => sanitize_textarea_field((string) $term->description),
            ];
        }, $terms);
    }

    private function get_assigned_terms(int $post_id, string $taxonomy): array
    {
        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);

        if (! is_array($terms) || is_wp_error($terms)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $terms)));
    }

    /**
     * @return array<string, int|string>
     */
    private function get_linked_page_details(int $post_id): array
    {
        $page_id = absint((string) get_post_meta($post_id, '_fp_exp_page_id', true));
        if (! $page_id) {
            return [
                'id' => 0,
                'url' => '',
                'edit_url' => '',
                'status' => '',
                'status_label' => '',
            ];
        }

        $status = get_post_status($page_id) ?: '';
        $status_object = $status ? get_post_status_object($status) : null;

        return [
            'id' => $page_id,
            'url' => get_permalink($page_id) ?: '',
            'edit_url' => get_edit_post_link($page_id, 'raw') ?: '',
            'status' => $status,
            'status_label' => $status_object && ! empty($status_object->label) ? (string) $status_object->label : '',
        ];
    }
    /**
     * @deprecated Use PricingMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_pricing_meta(int $post_id): array
    {
        $defaults = [
            'tickets' => [],
            'group' => [],
            'addons' => [],
            'tax_class' => '',
            'base_price' => '',
        ];

        $meta = get_post_meta($post_id, '_fp_exp_pricing', true);
        if (! is_array($meta)) {
            $meta = [];
        }

        // Leggi base_price da _fp_base_price meta field (per retrocompatibilità)
        $base_price = get_post_meta($post_id, '_fp_base_price', true);
        if ('' !== $base_price && ! isset($meta['base_price'])) {
            $meta['base_price'] = (string) $base_price;
        }

        return array_merge($defaults, $meta);
    }
    /**
     * @deprecated Use CalendarMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_availability_meta(int $post_id): array
    {
        $defaults = [
            'frequency' => 'daily',
            'times' => [],
            'days_of_week' => [],
            'custom_slots' => [],
            'slot_capacity' => 0,
            'lead_time_hours' => absint((string) get_post_meta($post_id, '_fp_lead_time_hours', true)),
            'buffer_before_minutes' => absint((string) get_post_meta($post_id, '_fp_buffer_before_minutes', true)),
            'buffer_after_minutes' => absint((string) get_post_meta($post_id, '_fp_buffer_after_minutes', true)),
            'recurrence' => Recurrence::defaults(),
        ];

        $meta = get_post_meta($post_id, '_fp_exp_availability', true);
        if (! is_array($meta)) {
            $defaults['lead_time_hours'] = absint((string) get_post_meta($post_id, '_fp_lead_time_hours', true));
            $defaults['buffer_before_minutes'] = absint((string) get_post_meta($post_id, '_fp_buffer_before_minutes', true));
            $defaults['buffer_after_minutes'] = absint((string) get_post_meta($post_id, '_fp_buffer_after_minutes', true));
            return $defaults;
        }

        $availability = array_merge($defaults, $meta);
        $availability['lead_time_hours'] = absint((string) ($availability['lead_time_hours'] ?? get_post_meta($post_id, '_fp_lead_time_hours', true)));
        $availability['buffer_before_minutes'] = absint((string) ($availability['buffer_before_minutes'] ?? get_post_meta($post_id, '_fp_buffer_before_minutes', true)));
        $availability['buffer_after_minutes'] = absint((string) ($availability['buffer_after_minutes'] ?? get_post_meta($post_id, '_fp_buffer_after_minutes', true)));
        $availability['recurrence'] = $this->get_recurrence_meta($post_id);

        return $availability;
    }

    private function get_recurrence_meta(int $post_id): array
    {
        $stored = get_post_meta($post_id, '_fp_exp_recurrence', true);
        if (! is_array($stored)) {
            return Recurrence::defaults();
        }

        unset($stored['enabled']);
        $stored['frequency'] = isset($stored['frequency']) ? sanitize_key((string) $stored['frequency']) : 'weekly';

        if (! in_array($stored['frequency'], ['daily', 'weekly', 'specific'], true)) {
            $stored['frequency'] = 'weekly';
        }

        $stored['start_date'] = isset($stored['start_date']) ? sanitize_text_field((string) $stored['start_date']) : '';
        $stored['end_date'] = isset($stored['end_date']) ? sanitize_text_field((string) $stored['end_date']) : '';
        $stored['duration'] = isset($stored['duration']) ? absint((string) $stored['duration']) : 60;

        if (! isset($stored['days']) || ! is_array($stored['days'])) {
            $stored['days'] = [];
        }

        // Supporta sia il nuovo formato time_slots che il vecchio time_sets
        $time_slots = [];
        
        // Nuovo formato: time_slots (semplificato)
        if (isset($stored['time_slots']) && is_array($stored['time_slots'])) {
            foreach ($stored['time_slots'] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                
                $time = isset($slot['time']) ? trim(sanitize_text_field((string) $slot['time'])) : '';
                if ('' === $time) {
                    continue;
                }
                
                $time_slots[] = [
                    'time' => $time,
                    'capacity' => isset($slot['capacity']) ? absint((string) $slot['capacity']) : 0,
                    'buffer_before' => isset($slot['buffer_before']) ? absint((string) $slot['buffer_before']) : 0,
                    'buffer_after' => isset($slot['buffer_after']) ? absint((string) $slot['buffer_after']) : 0,
                    'days' => isset($slot['days']) && is_array($slot['days']) ? $slot['days'] : [],
                ];
            }
        }
        
        // Vecchio formato: time_sets (per retrocompatibilità)
        $time_sets = [];
        if (isset($stored['time_sets']) && is_array($stored['time_sets'])) {
            foreach ($stored['time_sets'] as $set) {
                if (! is_array($set)) {
                    continue;
                }

                $label = isset($set['label']) ? sanitize_text_field((string) $set['label']) : '';
                $times = [];
                $set_days = [];

                if (isset($set['times']) && is_array($set['times'])) {
                    foreach ($set['times'] as $time) {
                        $time_string = trim(sanitize_text_field((string) $time));
                        if ('' === $time_string) {
                            continue;
                        }
                        $times[] = $time_string;
                    }
                }

                if (isset($set['days']) && is_array($set['days'])) {
                    foreach ($set['days'] as $day) {
                        $day_key = sanitize_key((string) $day);
                        $mapped = $this->map_weekday_for_ui($day_key);
                        if ('' !== $mapped && ! in_array($mapped, $set_days, true)) {
                            $set_days[] = $mapped;
                        }
                    }
                }

                if (empty($times)) {
                    continue;
                }

                $time_sets[] = [
                    'label' => $label,
                    'times' => array_values(array_unique($times)),
                    'days' => $set_days,
                    'capacity' => isset($set['capacity']) ? absint((string) $set['capacity']) : 0,
                    'buffer_before' => isset($set['buffer_before']) ? absint((string) $set['buffer_before']) : 0,
                    'buffer_after' => isset($set['buffer_after']) ? absint((string) $set['buffer_after']) : 0,
                ];
            }
        }

        $stored['time_slots'] = $time_slots;
        $stored['time_sets'] = $time_sets;
        
        // Migrazione automatica: se ci sono time_sets ma non time_slots, converti per l'interfaccia
        if (empty($time_slots) && !empty($time_sets)) {
            $converted_slots = [];
            foreach ($time_sets as $set) {
                if (!isset($set['times']) || !is_array($set['times'])) {
                    continue;
                }
                
                // Converti ogni time del set in un time_slot separato
                foreach ($set['times'] as $time) {
                    $converted_slots[] = [
                        'time' => $time,
                        'capacity' => $set['capacity'] ?? 0,
                        'buffer_before' => $set['buffer_before'] ?? 0,
                        'buffer_after' => $set['buffer_after'] ?? 0,
                        'days' => $set['days'] ?? [],
                    ];
                }
            }
            $stored['time_slots'] = $converted_slots;
        }

        return array_merge(Recurrence::defaults(), $stored);
    }
    /**
     * @deprecated Use MeetingPointMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_meeting_point_meta(int $post_id): array
    {
        $primary = absint((string) get_post_meta($post_id, '_fp_meeting_point_id', true));
        $alternatives = get_post_meta($post_id, '_fp_meeting_point_alt', true);
        $alternatives = is_array($alternatives) ? array_map('absint', $alternatives) : [];

        return [
            'primary' => $primary,
            'alternatives' => $alternatives,
        ];
    }
    private function get_meeting_point_choices(): array
    {
        if (! Helpers::meeting_points_enabled()) {
            return [];
        }

        $posts = get_posts([
            'post_type' => MeetingPointCPT::POST_TYPE,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish'],
            'fields' => 'ids',
        ]);

        $choices = [];
        foreach ($posts as $post_id) {
            $point = Repository::get_meeting_point((int) $post_id);
            if (! $point) {
                continue;
            }

            $choices[] = [
                'id' => $point['id'],
                'title' => $point['title'],
            ];
        }

        return $choices;
    }
    /**
     * @deprecated Use ExtrasMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_extras_meta(int $post_id): array
    {
        $highlights = get_post_meta($post_id, '_fp_highlights', true);
        $inclusions = get_post_meta($post_id, '_fp_inclusions', true);
        $exclusions = get_post_meta($post_id, '_fp_exclusions', true);
        $what_to_bring = get_post_meta($post_id, '_fp_what_to_bring', true);
        $notes = get_post_meta($post_id, '_fp_notes', true);

        return [
            'highlights' => $this->array_to_lines($highlights),
            'inclusions' => $this->array_to_lines($inclusions),
            'exclusions' => $this->array_to_lines($exclusions),
            'what_to_bring' => $this->array_to_lines($what_to_bring),
            'notes' => $this->array_to_lines($notes),
        ];
    }
    /**
     * @deprecated Use PolicyMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_policy_meta(int $post_id): array
    {
        $faq_meta = get_post_meta($post_id, '_fp_faq', true);
        $faq = [];

        if (is_array($faq_meta)) {
            foreach ($faq_meta as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $faq[] = [
                    'question' => sanitize_text_field((string) ($item['question'] ?? '')),
                    'answer' => wp_kses_post((string) ($item['answer'] ?? '')),
                ];
            }
        }

        return [
            'cancel' => wp_kses_post((string) get_post_meta($post_id, '_fp_policy_cancel', true)),
            'faq' => $faq,
        ];
    }
    /**
     * @deprecated Use SEOMetaBoxHandler::get() instead
     * @return array<string, mixed>
     */
    private function get_seo_meta(int $post_id): array
    {
        return [
            'meta_title' => sanitize_text_field((string) get_post_meta($post_id, '_fp_meta_title', true)),
            'meta_description' => sanitize_text_field((string) get_post_meta($post_id, '_fp_meta_description', true)),
            'schema_json' => (string) get_post_meta($post_id, '_fp_schema_manual', true),
        ];
    }
    private function has_pricing(array $pricing): bool
    {
        if (! empty($pricing['tickets'])) {
            foreach ((array) $pricing['tickets'] as $ticket) {
                if (is_array($ticket) && isset($ticket['price']) && (float) $ticket['price'] > 0) {
                    return true;
                }
            }
        }

        if (! empty($pricing['group']) && isset($pricing['group']['price']) && (float) $pricing['group']['price'] > 0) {
            return true;
        }

        return false;
    }
    private function update_or_delete_meta(int $post_id, string $key, $value): void
    {
        if (is_array($value)) {
            $filtered = array_filter($value, static function ($item) {
                if (is_array($item)) {
                    return ! empty(array_filter($item, static fn($val) => '' !== $val && null !== $val));
                }

                return '' !== $item && null !== $item;
            });

            if (empty($filtered)) {
                delete_post_meta($post_id, $key);
                return;
            }

            update_post_meta($post_id, $key, array_values($filtered));
            return;
        }

        if ('' === $value || null === $value) {
            delete_post_meta($post_id, $key);
            return;
        }

        update_post_meta($post_id, $key, $value);
    }
    private function lines_to_array($value): array
    {
        if (is_array($value)) {
            $sanitized = array_map('sanitize_text_field', $value);
            // Filtra elementi vuoti e la stringa "Array" (dati corrotti)
            return array_values(array_filter($sanitized, static function($item) {
                return '' !== $item && strtolower(trim($item)) !== 'array';
            }));
        }

        $string_value = (string) $value;
        
        // Gestisce il caso di dati corrotti dove è stata salvata la stringa "Array"
        if (strtolower(trim($string_value)) === 'array') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $string_value);
        if (! is_array($lines)) {
            return [];
        }

        $sanitized = array_map('sanitize_text_field', $lines);
        // Filtra elementi vuoti e la stringa "Array" (dati corrotti)
        return array_values(array_filter($sanitized, static function($item) {
            return '' !== $item && strtolower(trim($item)) !== 'array';
        }));
    }

    private function array_to_lines($value): string
    {
        if (is_array($value)) {
            $sanitized = array_map('sanitize_text_field', $value);
            // Filtra elementi vuoti e la stringa "Array" (dati corrotti)
            $items = array_values(array_filter($sanitized, static function($item) {
                return '' !== $item && strtolower(trim($item)) !== 'array';
            }));
            return implode("\n", $items);
        }

        if (is_string($value)) {
            // Gestisce il caso di dati corrotti dove è stata salvata la stringa "Array"
            if (strtolower(trim($value)) === 'array') {
                return '';
            }
            return $value;
        }

        return '';
    }
    private function get_tax_class_options(): array
    {
        $options = [
            'standard' => esc_html__('Aliquota standard', 'fp-experiences'),
        ];

        if (class_exists('WC_Tax')) {
            $classes = \WC_Tax::get_tax_classes();
            foreach ($classes as $class_name) {
                $slug = sanitize_key((string) sanitize_title($class_name));
                if ('' === $slug) {
                    continue;
                }

                $options[$slug] = $class_name;
            }
        }

        return $options;
    }

    private function get_recurrence_frequency_summary_template(string $frequency): string
    {
        switch ($frequency) {
            case 'daily':
                return esc_html__('Gli slot si ripetono ogni giorno nel periodo indicato.', 'fp-experiences');
            case 'weekly':
                return esc_html__('Gli slot si ripetono ogni settimana nei giorni selezionati: %s.', 'fp-experiences');
            case 'specific':
                return esc_html__('Gli slot vengono generati solo per le date inserite nei set orari.', 'fp-experiences');
            default:
                return '';
        }
    }

    /**
     * @param array<int, string> $days
     * @param bool               $open_ended
     */
    private function get_recurrence_frequency_summary(string $frequency, array $days, bool $open_ended = false): string
    {
        $template = $this->get_recurrence_frequency_summary_template($frequency);

        if ('' === $template) {
            return '';
        }

        if ('weekly' !== $frequency) {
            return $open_ended
                ? $template . ' ' . esc_html__('La ricorrenza resta attiva finché non imposti una data di fine.', 'fp-experiences')
                : $template;
        }

        $labels_map = [];
        foreach ($this->get_week_days() as $day_key => $day_label) {
            $labels_map[$this->map_weekday_for_ui($day_key)] = $day_label;
        }

        $selected_labels = [];
        foreach ($days as $day) {
            if (isset($labels_map[$day])) {
                $selected_labels[] = $labels_map[$day];
            }
        }

        $message = $selected_labels
            ? sprintf($template, implode(', ', $selected_labels))
            : sprintf($template, esc_html__('Nessun giorno selezionato', 'fp-experiences'));

        if ($open_ended) {
            $message .= ' ' . esc_html__('La ricorrenza resta attiva finché non imposti una data di fine.', 'fp-experiences');
        }

        return $message;
    }

    private function get_week_days(): array
    {
        return [
            'mon' => esc_html__('Lunedì', 'fp-experiences'),
            'tue' => esc_html__('Martedì', 'fp-experiences'),
            'wed' => esc_html__('Mercoledì', 'fp-experiences'),
            'thu' => esc_html__('Giovedì', 'fp-experiences'),
            'fri' => esc_html__('Venerdì', 'fp-experiences'),
            'sat' => esc_html__('Sabato', 'fp-experiences'),
            'sun' => esc_html__('Domenica', 'fp-experiences'),
        ];
    }

    private function map_weekday_for_ui(string $day): string
    {
        $map = [
            'mon' => 'monday',
            'tue' => 'tuesday',
            'wed' => 'wednesday',
            'thu' => 'thursday',
            'fri' => 'friday',
            'sat' => 'saturday',
            'sun' => 'sunday',
        ];

        return $map[$day] ?? $day;
    }
}
