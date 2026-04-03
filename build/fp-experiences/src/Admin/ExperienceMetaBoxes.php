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
use FP_Exp\Utils\Helpers;
use WP_Post;

use function absint;
use function add_action;
use function add_meta_box;
use function array_merge;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function get_current_screen;
use function get_post;
use function get_post_meta;
use function get_post_status;
use function get_post_thumbnail_id;
use function get_transient;
use function in_array;
use function is_array;
use function ob_get_clean;
use function ob_start;
use function remove_meta_box;
use function rest_url;
use function sanitize_text_field;
use function set_post_thumbnail;
use function sprintf;
use function update_post_meta;
use function wp_create_nonce;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;

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

    /**
     * Etichette dei tab traducibili
     * 
     * @return array<string, string>
     */
    private function get_tab_labels(): array
    {
        return [
            'details' => esc_html__('Dettagli', 'fp-experiences'),
            'pricing' => esc_html__('Biglietti & Prezzi', 'fp-experiences'),
            'calendar' => esc_html__('Calendario & Slot', 'fp-experiences'),
            'meeting-point' => esc_html__('Meeting Point', 'fp-experiences'),
            'extras' => esc_html__('Extra', 'fp-experiences'),
            'policy' => esc_html__('Policy/FAQ', 'fp-experiences'),
            'seo' => esc_html__('SEO/Schema', 'fp-experiences'),
        ];
    }

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
            Helpers::admin_style_dependencies(),
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
                        
                        // Se non esiste già un elemento content, crealo DENTRO il tooltip
                        if (!$tooltip.find(".fp-exp-tooltip__content").length && !$tooltip.next(".fp-exp-tooltip__content").length) {
                            var $content = $("<span>")
                                .addClass("fp-exp-tooltip__content")
                                .attr("role", "tooltip")
                                .attr("aria-hidden", "true")
                                .text(tooltipText);
                            $tooltip.append($content);
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
                        // Cerca il content sia dentro che fuori (per retrocompatibilità)
                        var $content = $tooltip.find(".fp-exp-tooltip__content").length ? $tooltip.find(".fp-exp-tooltip__content") : $tooltip.next(".fp-exp-tooltip__content");
                        if ($content.length) {
                            $content.attr("aria-hidden", "false").css("display", "block");
                        }
                    });
                    
                    $(document).on("mouseleave.fpExpTooltip blur.fpExpTooltip", ".fp-exp-tooltip[data-tooltip]", function(e) {
                        var $tooltip = $(this);
                        // Cerca il content sia dentro che fuori (per retrocompatibilità)
                        var $content = $tooltip.find(".fp-exp-tooltip__content").length ? $tooltip.find(".fp-exp-tooltip__content") : $tooltip.next(".fp-exp-tooltip__content");
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
        <div class="fp-exp-admin fp-exp-experience-metabox" data-fp-exp-admin>
            <div class="fp-exp-metabox-sticky-head">
                <p id="fp-exp-metabox-tablist-label" class="fp-exp-metabox-tablist__label">
                    <?php esc_html_e('Sezioni dell’esperienza — scegli una scheda; i campi da compilare sono nei riquadri sotto.', 'fp-experiences'); ?>
                </p>
            <div class="fp-exp-tabs" role="tablist" aria-label="<?php echo esc_attr(esc_html__('Sezioni esperienza', 'fp-experiences')); ?>">
                <?php 
                $seo_plugin_active = $this->is_seo_plugin_active();
                foreach ($this->get_tab_labels() as $slug => $label) : 
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
                        <?php echo $label; ?>
                    </button>
                <?php endforeach; ?>
            </div>
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
}
