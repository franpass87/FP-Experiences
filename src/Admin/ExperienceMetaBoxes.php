<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Booking\Recurrence;
use FP_Exp\MeetingPoints\MeetingPointCPT;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\LanguageHelper;
use WP_Post;

use function absint;
use function add_action;
use function add_meta_box;
use function array_filter;
use function array_map;
use function array_unique;
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
use function in_array;
use function implode;
use function is_array;
use function is_wp_error;
use function sanitize_key;
use function sanitize_text_field;
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
use function wp_set_post_terms;
use function remove_meta_box;

final class ExperienceMetaBoxes
{
    private const TAB_LABELS = [
        'details' => 'Dettagli',
        'pricing' => 'Biglietti & Prezzi',
        'calendar' => 'Calendario & Slot',
        'meeting-point' => 'Meeting Point',
        'extras' => 'Extra',
        'policy' => 'Policy/FAQ',
        'seo' => 'SEO/Schema',
    ];

    private const PRICING_NOTICE_KEY = 'fp_exp_pricing_notice_';

    public function register_hooks(): void
    {
        add_action('add_meta_boxes_fp_experience', [$this, 'add_meta_box']);
        add_action('add_meta_boxes', [$this, 'remove_default_meta_boxes'], 99);
        add_action('save_post_fp_experience', [$this, 'save_meta_boxes'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'maybe_show_pricing_notice']);
    }

    public function remove_default_meta_boxes(): void
    {
        remove_meta_box('fp_exp_themediv', 'fp_experience', 'side');
        remove_meta_box('tagsdiv-fp_exp_language', 'fp_experience', 'side');
        remove_meta_box('tagsdiv-fp_exp_duration', 'fp_experience', 'side');
        remove_meta_box('tagsdiv-fp_exp_family_friendly', 'fp_experience', 'side');
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
            'high'
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

        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            Helpers::asset_version('assets/css/admin.css')
        );

        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/js/admin.js',
            [],
            Helpers::asset_version('assets/js/admin.js'),
            true
        );

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
                    'recurrencePreviewError' => esc_html__('Impossibile calcolare la ricorrenza: verifica date e orari.', 'fp-experiences'),
                    'recurrencePreviewEmpty' => esc_html__('Nessuno slot futuro trovato per la regola indicata.', 'fp-experiences'),
                    'recurrenceGenerateSuccess' => esc_html__('Slot rigenerati: %d creati/aggiornati.', 'fp-experiences'),
                    'recurrenceGenerateError' => esc_html__('Errore durante la rigenerazione degli slot. Riprova più tardi.', 'fp-experiences'),
                    'recurrencePostMissing' => esc_html__('Salva l\'esperienza prima di generare gli slot.', 'fp-experiences'),
                    'recurrenceTimeLabel' => esc_html__('Orario ricorrenza', 'fp-experiences'),
                    'recurrenceRemoveTime' => esc_html__('Rimuovi orario', 'fp-experiences'),
                    'recurrenceLoading' => esc_html__('Generazione in corso…', 'fp-experiences'),
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
    }

    public function render_meta_box(WP_Post $post): void
    {
        $details = $this->get_details_meta($post->ID);
        $pricing = $this->get_pricing_meta($post->ID);
        $availability = $this->get_availability_meta($post->ID);
        $meeting = $this->get_meeting_point_meta($post->ID);
        $meeting_choices = $this->get_meeting_point_choices();
        $extras = $this->get_extras_meta($post->ID);
        $policy = $this->get_policy_meta($post->ID);
        $seo = $this->get_seo_meta($post->ID);

        wp_nonce_field('fp_exp_meta_nonce', 'fp_exp_meta_nonce');
        ?>
        <div class="fp-exp-admin" data-fp-exp-admin>
            <div class="fp-exp-tabs" role="tablist" aria-label="<?php echo esc_attr(esc_html__('Sezioni esperienza', 'fp-experiences')); ?>">
                <?php foreach (self::TAB_LABELS as $slug => $label) : ?>
                    <?php $tab_id = 'fp-exp-tab-' . $slug; ?>
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
                <?php $this->render_details_tab($details); ?>
                <?php $this->render_pricing_tab($pricing); ?>
                <?php $this->render_calendar_tab($availability); ?>
                <?php $this->render_meeting_point_tab($meeting, $meeting_choices); ?>
                <?php $this->render_extras_tab($extras); ?>
                <?php $this->render_policy_tab($policy); ?>
                <?php $this->render_seo_tab($seo); ?>
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

        $this->save_details_meta($post_id, $raw['fp_exp_details'] ?? []);
        $pricing_status = $this->save_pricing_meta($post_id, $raw['fp_exp_pricing'] ?? []);
        $this->save_availability_meta($post_id, $raw['fp_exp_availability'] ?? []);
        $this->save_meeting_point_meta($post_id, $raw['fp_exp_meeting_point'] ?? []);
        $this->save_extras_meta($post_id, $raw['fp_exp_extras'] ?? []);
        $this->save_policy_meta($post_id, $raw['fp_exp_policy'] ?? []);
        $this->save_seo_meta($post_id, $raw['fp_exp_seo'] ?? []);

        set_transient(self::PRICING_NOTICE_KEY . $post_id, $pricing_status, MINUTE_IN_SECONDS);
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
    private function render_details_tab(array $details): void
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
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-languages">
                            <?php esc_html_e('Lingue disponibili', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-languages-help', esc_html__('Separa le lingue con una virgola. Esempio: Italiano, Inglese.', 'fp-experiences')); ?>
                        </label>
                        <input
                            type="text"
                            id="fp-exp-languages"
                            name="fp_exp_details[languages]"
                            value="<?php echo esc_attr((string) $details['languages']); ?>"
                            placeholder="<?php echo esc_attr__('Italiano, Inglese', 'fp-experiences'); ?>"
                            aria-describedby="fp-exp-languages-help"
                        />
                        <p class="fp-exp-field__description" id="fp-exp-languages-help"><?php esc_html_e('Le lingue vengono mostrate nei badge e nel markup schema.', 'fp-experiences'); ?></p>
                        <?php if (! empty($details['language_badges'])) : ?>
                            <ul class="fp-exp-language-preview" role="list">
                                <?php foreach ($details['language_badges'] as $language) :
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
                        <?php endif; ?>
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

                <div class="fp-exp-field fp-exp-field--taxonomies">
                    <div class="fp-exp-field">
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Temi esperienza', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-theme-help', esc_html__('Scegli uno o più temi per alimentare i filtri pubblici.', 'fp-experiences')); ?>
                        </span>
                        <div class="fp-exp-checkbox-grid" aria-describedby="fp-exp-theme-help">
                            <?php foreach ($details['taxonomies']['theme']['choices'] as $choice) :
                                $term_id = (int) $choice['id'];
                                ?>
                                <label>
                                    <input type="checkbox" name="fp_exp_details[themes][]" value="<?php echo esc_attr((string) $term_id); ?>" <?php checked(in_array($term_id, $details['taxonomies']['theme']['selected'], true)); ?> />
                                    <span><?php echo esc_html($choice['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="fp-exp-field__description" id="fp-exp-theme-help"><?php esc_html_e('I temi selezionati compaiono nella panoramica e nelle liste.', 'fp-experiences'); ?></p>
                    </div>

                    <div class="fp-exp-field">
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Lingue per filtri', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-language-tax-help', esc_html__('Collega le lingue ai filtri rapidi e mostra le bandierine nella panoramica.', 'fp-experiences')); ?>
                        </span>
                        <div class="fp-exp-checkbox-grid" aria-describedby="fp-exp-language-tax-help">
                            <?php foreach ($details['taxonomies']['language']['choices'] as $choice) :
                                $term_id = (int) $choice['id'];
                                ?>
                                <label>
                                    <input type="checkbox" name="fp_exp_details[taxonomy_languages][]" value="<?php echo esc_attr((string) $term_id); ?>" <?php checked(in_array($term_id, $details['taxonomies']['language']['selected'], true)); ?> />
                                    <span><?php echo esc_html($choice['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="fp-exp-field__description" id="fp-exp-language-tax-help"><?php esc_html_e('Utilizza le stesse lingue definite nei filtri globali.', 'fp-experiences'); ?></p>
                    </div>

                    <div class="fp-exp-field">
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Durate aggiuntive', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-duration-tax-help', esc_html__('Associa etichette come "Mezza giornata" o "Serale" per filtrare le esperienze.', 'fp-experiences')); ?>
                        </span>
                        <div class="fp-exp-checkbox-grid" aria-describedby="fp-exp-duration-tax-help">
                            <?php foreach ($details['taxonomies']['duration']['choices'] as $choice) :
                                $term_id = (int) $choice['id'];
                                ?>
                                <label>
                                    <input type="checkbox" name="fp_exp_details[durations][]" value="<?php echo esc_attr((string) $term_id); ?>" <?php checked(in_array($term_id, $details['taxonomies']['duration']['selected'], true)); ?> />
                                    <span><?php echo esc_html($choice['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="fp-exp-field__description" id="fp-exp-duration-tax-help"><?php esc_html_e('Mostrate nella panoramica accanto alla durata in minuti.', 'fp-experiences'); ?></p>
                    </div>

                    <div class="fp-exp-field">
                        <span class="fp-exp-field__label">
                            <?php esc_html_e('Family friendly', 'fp-experiences'); ?>
                            <?php $this->render_tooltip('fp-exp-family-help', esc_html__('Attiva le etichette per famiglie selezionando le opzioni disponibili.', 'fp-experiences')); ?>
                        </span>
                        <div class="fp-exp-checkbox-grid" aria-describedby="fp-exp-family-help">
                            <?php foreach ($details['taxonomies']['family']['choices'] as $choice) :
                                $term_id = (int) $choice['id'];
                                ?>
                                <label>
                                    <input type="checkbox" name="fp_exp_details[family_friendly][]" value="<?php echo esc_attr((string) $term_id); ?>" <?php checked(in_array($term_id, $details['taxonomies']['family']['selected'], true)); ?> />
                                    <span><?php echo esc_html($choice['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="fp-exp-field__description" id="fp-exp-family-help"><?php esc_html_e('Contrassegna l\'esperienza come adatta alle famiglie e mostra il badge dedicato.', 'fp-experiences'); ?></p>
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
                            <?php esc_html_e('La pagina viene generata automaticamente alla pubblicazione dell’esperienza.', 'fp-experiences'); ?>
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
    private function render_pricing_tab(array $pricing): void
    {
        $panel_id = 'fp-exp-tab-pricing-panel';
        $tickets = $pricing['tickets'];
        if (empty($tickets)) {
            $tickets = [['label' => '', 'price' => '', 'capacity' => '', 'slug' => '']];
        }

        $addons = $pricing['addons'];
        if (empty($addons)) {
            $addons = [['name' => '', 'price' => '', 'type' => 'person', 'slug' => '']];
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
                        <?php $this->render_addon_row('__INDEX__', ['name' => '', 'price' => '', 'type' => 'person', 'slug' => ''], true); ?>
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
    private function render_calendar_tab(array $availability): void
    {
        $panel_id = 'fp-exp-tab-calendar-panel';
        $recurrence = $availability['recurrence'] ?? Recurrence::defaults();
        if (! is_array($recurrence)) {
            $recurrence = Recurrence::defaults();
        } else {
            $recurrence = array_merge(Recurrence::defaults(), $recurrence);
        }

        $frequency = isset($recurrence['frequency']) ? (string) $recurrence['frequency'] : 'weekly';
        if (! in_array($frequency, ['daily', 'weekly', 'specific'], true)) {
            $frequency = 'weekly';
        }

        $time_sets = $recurrence['time_sets'];
        $recurrence_days = isset($recurrence['days']) && is_array($recurrence['days']) ? $recurrence['days'] : [];
        $frequency_summary = $this->get_recurrence_frequency_summary($frequency, $recurrence_days);
        if (empty($time_sets)) {
            $time_sets = [['label' => '', 'times' => [''], 'days' => []]];
        }
        $custom_slots = isset($availability['custom_slots']) && is_array($availability['custom_slots'])
            ? $availability['custom_slots']
            : [];
        if (empty($custom_slots)) {
            $custom_slots = [['date' => '', 'time' => '']];
        }
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
                <legend><?php esc_html_e('Guida rapida agli slot', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <p class="fp-exp-field__description"><?php esc_html_e('Organizza gli orari seguendo tre passaggi:', 'fp-experiences'); ?></p>
                    <p class="fp-exp-field__description"><strong><?php esc_html_e('1.', 'fp-experiences'); ?></strong> <?php esc_html_e('Definisci qui sotto la capienza di base e i buffer globali.', 'fp-experiences'); ?></p>
                    <p class="fp-exp-field__description"><strong><?php esc_html_e('2.', 'fp-experiences'); ?></strong> <?php esc_html_e('Attiva la ricorrenza automatica per generare gli slot ricorrenti.', 'fp-experiences'); ?></p>
                    <p class="fp-exp-field__description"><strong><?php esc_html_e('3.', 'fp-experiences'); ?></strong> <?php esc_html_e('Aggiungi eventuali eccezioni con gli slot manuali una tantum.', 'fp-experiences'); ?></p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Impostazioni generali degli slot', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-slot-capacity"><?php esc_html_e('Capienza predefinita slot', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-slot-capacity"
                            name="fp_exp_availability[slot_capacity]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['slot_capacity']); ?>"
                        />
                        <p class="fp-exp-field__description"><?php esc_html_e('Valore usato per gli slot generati automaticamente. Puoi modificarlo per singolo slot dal calendario.', 'fp-experiences'); ?></p>
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
                        <p class="fp-exp-field__description"><?php esc_html_e('Limita la possibilità di prenotare gli slot troppo a ridosso della partenza.', 'fp-experiences'); ?></p>
                    </div>
                </div>
                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-buffer-before"><?php esc_html_e('Buffer prima (minuti)', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-buffer-before"
                            name="fp_exp_availability[buffer_before_minutes]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['buffer_before_minutes']); ?>"
                        />
                    </div>
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-buffer-after"><?php esc_html_e('Buffer dopo (minuti)', 'fp-experiences'); ?></label>
                        <input
                            type="number"
                            id="fp-exp-buffer-after"
                            name="fp_exp_availability[buffer_after_minutes]"
                            min="0"
                            step="1"
                            value="<?php echo esc_attr((string) $availability['buffer_after_minutes']); ?>"
                        />
                    </div>
                </div>
                <p class="fp-exp-field__description"><?php esc_html_e('I buffer vengono applicati quando si generano nuovi slot o si controlla la disponibilità.', 'fp-experiences'); ?></p>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Ricorrenza automatica', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field fp-exp-field--switch">
                    <label class="fp-exp-switch">
                        <input type="checkbox" name="fp_exp_availability[recurrence][enabled]" value="1" data-recurrence-toggle <?php checked(! empty($recurrence['enabled'])); ?> />
                        <span><?php esc_html_e('Attiva generazione automatica slot (RRULE)', 'fp-experiences'); ?></span>
                    </label>
                    <p class="fp-exp-field__description"><?php esc_html_e('Configura regole ricorrenti per popolare automaticamente il calendario senza toccare gli slot già esistenti.', 'fp-experiences'); ?></p>
                </div>
                <div class="fp-exp-field">
                    <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Suggerimento: compila gli step dall’alto verso il basso e usa il pulsante di anteprima per verificare il risultato prima di generare.', 'fp-experiences'); ?></p>
                </div>
                <div class="fp-exp-recurrence" data-recurrence-settings <?php echo ! empty($recurrence['enabled']) ? '' : 'hidden'; ?>>
                    <div class="fp-exp-field fp-exp-field--columns">
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Data inizio', 'fp-experiences'); ?></span>
                            <input type="date" name="fp_exp_availability[recurrence][start_date]" value="<?php echo esc_attr((string) ($recurrence['start_date'] ?? '')); ?>" />
                        </label>
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Data fine', 'fp-experiences'); ?></span>
                            <input type="date" name="fp_exp_availability[recurrence][end_date]" value="<?php echo esc_attr((string) ($recurrence['end_date'] ?? '')); ?>" />
                        </label>
                    </div>

                    <div class="fp-exp-field">
                        <span class="fp-exp-field__label"><?php esc_html_e('Tipo di disponibilità ricorrente', 'fp-experiences'); ?></span>
                        <div class="fp-exp-radio-cards" role="radiogroup">
                            <label
                                class="fp-exp-radio-card<?php echo 'daily' === $frequency ? ' is-selected' : ''; ?>"
                                data-recurrence-frequency-card
                                data-frequency-summary-template="<?php echo esc_attr($this->get_recurrence_frequency_summary_template('daily')); ?>"
                            >
                                <input
                                    type="radio"
                                    name="fp_exp_availability[recurrence][frequency]"
                                    value="daily"
                                    data-recurrence-frequency
                                    <?php checked($frequency, 'daily'); ?>
                                />
                                <span class="fp-exp-radio-card__title"><?php esc_html_e('Giornaliera', 'fp-experiences'); ?></span>
                                <span class="fp-exp-radio-card__text"><?php esc_html_e('Gli stessi orari sono attivi ogni giorno tra la data di inizio e fine.', 'fp-experiences'); ?></span>
                            </label>
                            <label
                                class="fp-exp-radio-card<?php echo 'weekly' === $frequency ? ' is-selected' : ''; ?>"
                                data-recurrence-frequency-card
                                data-frequency-summary-template="<?php echo esc_attr($this->get_recurrence_frequency_summary_template('weekly')); ?>"
                            >
                                <input
                                    type="radio"
                                    name="fp_exp_availability[recurrence][frequency]"
                                    value="weekly"
                                    data-recurrence-frequency
                                    <?php checked($frequency, 'weekly'); ?>
                                />
                                <span class="fp-exp-radio-card__title"><?php esc_html_e('Settimanale', 'fp-experiences'); ?></span>
                                <span class="fp-exp-radio-card__text"><?php esc_html_e('Scegli i giorni della settimana in cui ripetere gli orari.', 'fp-experiences'); ?></span>
                            </label>
                            <label
                                class="fp-exp-radio-card<?php echo 'specific' === $frequency ? ' is-selected' : ''; ?>"
                                data-recurrence-frequency-card
                                data-frequency-summary-template="<?php echo esc_attr($this->get_recurrence_frequency_summary_template('specific')); ?>"
                            >
                                <input
                                    type="radio"
                                    name="fp_exp_availability[recurrence][frequency]"
                                    value="specific"
                                    data-recurrence-frequency
                                    <?php checked($frequency, 'specific'); ?>
                                />
                                <span class="fp-exp-radio-card__title"><?php esc_html_e('Date specifiche', 'fp-experiences'); ?></span>
                                <span class="fp-exp-radio-card__text"><?php esc_html_e('Usa la data di inizio/fine per delimitare il periodo e aggiungi solo gli slot speciali richiesti.', 'fp-experiences'); ?></span>
                            </label>
                        </div>
                        <p class="fp-exp-field__description" data-recurrence-frequency-help data-frequency="daily" <?php echo 'daily' === $frequency ? '' : 'hidden'; ?>>
                            <?php esc_html_e('Gli orari selezionati verranno generati per ogni giorno del periodo indicato.', 'fp-experiences'); ?>
                        </p>
                        <p class="fp-exp-field__description" data-recurrence-frequency-help data-frequency="weekly" <?php echo 'weekly' === $frequency ? '' : 'hidden'; ?>>
                            <?php esc_html_e('Seleziona i giorni attivi qui sotto o lascia tutti deselezionati per usare gli orari solo nelle date manuali.', 'fp-experiences'); ?>
                        </p>
                        <p class="fp-exp-field__description" data-recurrence-frequency-help data-frequency="specific" <?php echo 'specific' === $frequency ? '' : 'hidden'; ?>>
                            <?php esc_html_e('Questa opzione è ideale per stagionalità limitate o weekend particolari: genera slot solo nelle date indicate manualmente.', 'fp-experiences'); ?>
                        </p>
                        <p
                            class="fp-exp-field__description fp-exp-recurrence__summary"
                            data-recurrence-frequency-summary
                            <?php echo '' === $frequency_summary ? 'hidden' : ''; ?>
                        >
                            <?php echo esc_html($frequency_summary); ?>
                        </p>
                    </div>

                    <div
                        class="fp-exp-field"
                        data-recurrence-days
                        data-recurrence-weekly-empty="<?php echo esc_attr__('Nessun giorno selezionato', 'fp-experiences'); ?>"
                        <?php echo 'weekly' === $frequency ? '' : 'hidden'; ?>
                    >
                        <span class="fp-exp-field__label"><?php esc_html_e('Giorni attivi', 'fp-experiences'); ?></span>
                        <div class="fp-exp-checkbox-grid">
                            <?php foreach ($this->get_week_days() as $day_key => $day_label) : ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="fp_exp_availability[recurrence][days][]"
                                        value="<?php echo esc_attr($day_key); ?>"
                                        data-recurrence-day
                                        data-day-label="<?php echo esc_attr($day_label); ?>"
                                        <?php checked(in_array($this->map_weekday_for_ui($day_key), $recurrence_days, true)); ?>
                                    />
                                    <span><?php echo esc_html($day_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="fp-exp-field fp-exp-field--columns">
                        <label>
                            <span class="fp-exp-field__label"><?php esc_html_e('Durata slot (minuti)', 'fp-experiences'); ?></span>
                            <input type="number" min="15" step="5" name="fp_exp_availability[recurrence][duration]" value="<?php echo esc_attr((string) ($recurrence['duration'] ?? 60)); ?>" />
                        </label>
                        <div class="fp-exp-field__hint" data-recurrence-errors hidden></div>
                    </div>

                    <div class="fp-exp-repeater fp-exp-recurrence__sets" data-repeater="recurrence_time_sets" data-repeater-next-index="<?php echo esc_attr((string) count($time_sets)); ?>">
                        <div class="fp-exp-repeater__items" data-recurrence-time-set-list>
                            <?php foreach ($time_sets as $index => $set) : ?>
                                <?php $this->render_time_set_row((string) $index, $set, false, $frequency); ?>
                            <?php endforeach; ?>
                        </div>
                        <template data-repeater-template>
                            <?php $this->render_time_set_row('__INDEX__', [
                                'label' => '',
                                'times' => [''],
                                'days' => [],
                                'capacity' => 0,
                                'buffer_before' => 0,
                                'buffer_after' => 0,
                            ], true, $frequency); ?>
                        </template>
                        <p class="fp-exp-repeater__actions">
                            <button type="button" class="button button-secondary" data-repeater-add><?php esc_html_e('Aggiungi time set', 'fp-experiences'); ?></button>
                        </p>
                    </div>

                    <div class="fp-exp-recurrence__actions">
                        <button type="button" class="button" data-recurrence-preview><?php esc_html_e('Anteprima ricorrenza', 'fp-experiences'); ?></button>
                        <button type="button" class="button button-primary" data-recurrence-generate><?php esc_html_e('Rigenera slot da RRULE', 'fp-experiences'); ?></button>
                        <span class="fp-exp-recurrence__status" data-recurrence-status aria-live="polite"></span>
                    </div>

                    <div class="fp-exp-recurrence__preview" data-recurrence-preview-list hidden>
                        <h4><?php esc_html_e('Prossimi slot generati', 'fp-experiences'); ?></h4>
                        <ul></ul>
                    </div>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Slot manuali una tantum', 'fp-experiences'); ?></legend>
                <p class="fp-exp-field__description"><?php esc_html_e('Inserisci qui le eccezioni al calendario standard, ad esempio eventi speciali o date extra.', 'fp-experiences'); ?></p>
                <div class="fp-exp-repeater" data-repeater="custom_slots" data-repeater-next-index="<?php echo esc_attr((string) count($custom_slots)); ?>">
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($custom_slots as $index => $slot) : ?>
                            <?php $this->render_custom_slot_row((string) $index, $slot); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_custom_slot_row('__INDEX__', ['date' => '', 'time' => ''], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add><?php esc_html_e('Aggiungi slot manuale', 'fp-experiences'); ?></button>
                    </p>
                </div>
                <p class="fp-exp-field__description fp-exp-field__description--muted"><?php esc_html_e('Se lasci tutti i campi vuoti non verrà creato alcuno slot extra.', 'fp-experiences'); ?></p>
            </fieldset>
        </section>
        <?php
    }
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
                    <p class="fp-exp-field__description" id="fp-exp-schema-help"><?php esc_html_e("Lascia vuoto per usare lo schema standard dell'esperienza.", 'fp-experiences'); ?></p>
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
        $type_value = isset($addon['type']) ? (string) $addon['type'] : 'person';
        $image_id = isset($addon['image_id']) ? absint((string) $addon['image_id']) : 0;
        $image = $image_id > 0 ? wp_get_attachment_image_src($image_id, 'thumbnail') : false;
        $image_url = $image ? (string) $image[0] : '';
        $image_width = $image ? absint((string) $image[1]) : 0;
        $image_height = $image ? absint((string) $image[2]) : 0;
        $image_alt = isset($addon['name']) ? (string) $addon['name'] : '';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item draggable="true">
            <div class="fp-exp-repeater-row__fields">
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
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Nome extra', 'fp-experiences'); ?></span>
                    <input type="text" <?php echo $this->field_name_attribute($label_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['name'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Transfer', 'fp-experiences'); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Codice', 'fp-experiences'); ?></span>
                    <input type="text" <?php echo $this->field_name_attribute($slug_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['slug'] ?? '')); ?>" placeholder="<?php echo esc_attr__('transfer', 'fp-experiences'); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Descrizione breve', 'fp-experiences'); ?></span>
                    <textarea rows="2" maxlength="160" <?php echo $this->field_name_attribute($description_name, $is_template); ?>><?php echo esc_textarea((string) ($addon['description'] ?? '')); ?></textarea>
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Prezzo (€)', 'fp-experiences'); ?></span>
                    <input type="number" min="0" step="0.01" <?php echo $this->field_name_attribute($price_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['price'] ?? '')); ?>" />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Calcolo', 'fp-experiences'); ?></span>
                    <select <?php echo $this->field_name_attribute($type_name, $is_template); ?>>
                        <option value="person" <?php selected($type_value, 'person'); ?>><?php esc_html_e('Per persona', 'fp-experiences'); ?></option>
                        <option value="booking" <?php selected($type_value, 'booking'); ?>><?php esc_html_e('Per prenotazione', 'fp-experiences'); ?></option>
                    </select>
                </label>
            </div>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
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
        $days_base = $is_template
            ? 'fp_exp_availability[recurrence][time_sets][__INDEX__][days]'
            : 'fp_exp_availability[recurrence][time_sets][' . $index . '][days]';
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
        $set_days = [];
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

        if (isset($set['days']) && is_array($set['days'])) {
            foreach ($set['days'] as $day) {
                $set_days[] = (string) $day;
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
            <div class="fp-exp-field" data-time-set-days <?php echo 'weekly' === $frequency ? '' : 'hidden'; ?>>
                <span class="fp-exp-field__label"><?php esc_html_e('Giorni attivi per questo set', 'fp-experiences'); ?></span>
                <div class="fp-exp-checkbox-grid">
                    <?php foreach ($this->get_week_days() as $day_key => $day_label) : ?>
                        <label>
                            <input type="checkbox" <?php echo $this->field_name_attribute($days_base . '[]', $is_template); ?> value="<?php echo esc_attr($day_key); ?>" <?php checked(in_array($this->map_weekday_for_ui($day_key), $set_days, true)); ?> />
                            <span><?php echo esc_html($day_label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ('weekly' === $frequency) : ?>
                    <p class="fp-exp-field__description"><?php esc_html_e('Lascia vuoto per usare i giorni generali della ricorrenza settimanale.', 'fp-experiences'); ?></p>
                <?php endif; ?>
            </div>
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
    private function save_details_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            return;
        }

        $short_desc = isset($raw['short_desc']) ? sanitize_text_field((string) $raw['short_desc']) : '';
        $duration = isset($raw['duration_minutes']) ? absint((string) $raw['duration_minutes']) : 0;
        $languages_raw = isset($raw['languages']) ? (string) $raw['languages'] : '';
        $languages = array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', $languages_raw))));
        $min_party = isset($raw['min_party']) ? absint((string) $raw['min_party']) : 0;
        $capacity_slot = isset($raw['capacity_slot']) ? absint((string) $raw['capacity_slot']) : 0;
        $age_min = isset($raw['age_min']) ? absint((string) $raw['age_min']) : 0;
        $age_max = isset($raw['age_max']) ? absint((string) $raw['age_max']) : 0;
        $rules_children = isset($raw['rules_children']) ? sanitize_text_field((string) $raw['rules_children']) : '';
        $hero_id = isset($raw['hero_image_id']) ? absint((string) $raw['hero_image_id']) : 0;
        if ($hero_id > 0 && ! wp_attachment_is_image($hero_id)) {
            $hero_id = 0;
        }
        $theme_terms = isset($raw['themes']) && is_array($raw['themes']) ? array_filter(array_map('absint', $raw['themes'])) : [];
        $language_terms = isset($raw['taxonomy_languages']) && is_array($raw['taxonomy_languages'])
            ? array_filter(array_map('absint', $raw['taxonomy_languages']))
            : [];
        $duration_terms = isset($raw['durations']) && is_array($raw['durations']) ? array_filter(array_map('absint', $raw['durations'])) : [];
        $family_terms = isset($raw['family_friendly']) && is_array($raw['family_friendly']) ? array_filter(array_map('absint', $raw['family_friendly'])) : [];
        $cognitive_biases = isset($raw['cognitive_biases']) && is_array($raw['cognitive_biases'])
            ? array_values(array_filter(array_map('sanitize_key', $raw['cognitive_biases'])))
            : [];
        $cognitive_biases = array_values(array_unique($cognitive_biases));
        $cognitive_biases = array_slice($cognitive_biases, 0, Helpers::cognitive_bias_max_selection());

        $this->update_or_delete_meta($post_id, '_fp_short_desc', $short_desc);
        $this->update_or_delete_meta($post_id, '_fp_duration_minutes', $duration);
        $this->update_or_delete_meta($post_id, '_fp_languages', $languages);
        $this->update_or_delete_meta($post_id, '_fp_min_party', $min_party);
        $this->update_or_delete_meta($post_id, '_fp_capacity_slot', $capacity_slot);
        $this->update_or_delete_meta($post_id, '_fp_age_min', $age_min);
        $this->update_or_delete_meta($post_id, '_fp_age_max', $age_max);
        $this->update_or_delete_meta($post_id, '_fp_rules_children', $rules_children);
        $this->update_or_delete_meta($post_id, '_fp_cognitive_biases', $cognitive_biases);

        if ($hero_id > 0) {
            update_post_meta($post_id, '_fp_hero_image_id', $hero_id);
        } else {
            delete_post_meta($post_id, '_fp_hero_image_id');
        }

        wp_set_post_terms($post_id, $theme_terms, 'fp_exp_theme', false);
        wp_set_post_terms($post_id, $language_terms, 'fp_exp_language', false);
        wp_set_post_terms($post_id, $duration_terms, 'fp_exp_duration', false);
        wp_set_post_terms($post_id, $family_terms, 'fp_exp_family_friendly', false);
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
                ];

                $legacy_tickets[] = [
                    'slug' => $slug,
                    'label' => $label,
                    'price' => $price,
                    'min' => 0,
                    'max' => $capacity,
                    'capacity' => $capacity,
                    'description' => '',
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
            foreach ($raw['addons'] as $addon) {
                if (! is_array($addon)) {
                    continue;
                }

                $name = isset($addon['name']) ? sanitize_text_field((string) $addon['name']) : '';
                $price = isset($addon['price']) ? max(0.0, (float) $addon['price']) : 0.0;
                $type = isset($addon['type']) ? sanitize_key((string) $addon['type']) : 'person';
                $slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';
                $image_id = isset($addon['image_id']) ? absint((string) $addon['image_id']) : 0;
                $description = isset($addon['description']) ? sanitize_text_field((string) $addon['description']) : '';
                if ($image_id > 0 && ! wp_attachment_is_image($image_id)) {
                    $image_id = 0;
                }
                if ('' === $slug && '' !== $name) {
                    $slug = sanitize_key($name);
                }

                if ('' === $name || '' === $slug) {
                    continue;
                }

                if (! in_array($type, ['person', 'booking'], true)) {
                    $type = 'person';
                }

                $pricing['addons'][] = [
                    'name' => $name,
                    'price' => $price,
                    'type' => $type,
                    'slug' => $slug,
                    'image_id' => $image_id,
                    'description' => $description,
                ];

                $legacy_addons[] = [
                    'slug' => $slug,
                    'label' => $name,
                    'price' => $price,
                    'allow_multiple' => 'booking' !== $type,
                    'max' => 0,
                    'description' => $description,
                    'image_id' => $image_id,
                ];
            }
        }

        $tax_class = isset($raw['tax_class']) ? sanitize_key((string) $raw['tax_class']) : '';
        if ('standard' === $tax_class) {
            $tax_class = '';
        }
        $pricing['tax_class'] = $tax_class;

        if (! empty($pricing['tickets']) || ! empty($pricing['group']) || ! empty($pricing['addons']) || '' !== $pricing['tax_class']) {
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
    private function save_availability_meta(int $post_id, $raw): void
    {
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_exp_availability');
            $this->update_or_delete_meta($post_id, '_fp_lead_time_hours', 0);
            $this->update_or_delete_meta($post_id, '_fp_buffer_before_minutes', 0);
            $this->update_or_delete_meta($post_id, '_fp_buffer_after_minutes', 0);
            return;
        }

        $frequency = isset($raw['frequency']) ? sanitize_key((string) $raw['frequency']) : 'daily';
        if (! in_array($frequency, ['daily', 'weekly', 'custom'], true)) {
            $frequency = 'daily';
        }

        $slot_capacity = isset($raw['slot_capacity']) ? absint((string) $raw['slot_capacity']) : 0;
        $lead_time = isset($raw['lead_time_hours']) ? absint((string) $raw['lead_time_hours']) : 0;
        $buffer_before = isset($raw['buffer_before_minutes']) ? absint((string) $raw['buffer_before_minutes']) : 0;
        $buffer_after = isset($raw['buffer_after_minutes']) ? absint((string) $raw['buffer_after_minutes']) : 0;

        $times = [];
        if (isset($raw['times']) && is_array($raw['times'])) {
            foreach ($raw['times'] as $time) {
                $sanitized_time = trim(sanitize_text_field((string) $time));
                if ('' !== $sanitized_time) {
                    $times[] = $sanitized_time;
                }
            }
        }

        $days = [];
        if (isset($raw['days_of_week']) && is_array($raw['days_of_week'])) {
            foreach ($raw['days_of_week'] as $day) {
                $day_key = sanitize_key((string) $day);
                if (array_key_exists($day_key, $this->get_week_days()) && ! in_array($day_key, $days, true)) {
                    $days[] = $day_key;
                }
            }
        }

        $custom_slots = [];
        if (isset($raw['custom_slots']) && is_array($raw['custom_slots'])) {
            foreach ($raw['custom_slots'] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $date = isset($slot['date']) ? sanitize_text_field((string) $slot['date']) : '';
                $time = isset($slot['time']) ? sanitize_text_field((string) $slot['time']) : '';

                if ('' === $date && '' === $time) {
                    continue;
                }

                $custom_slots[] = [
                    'date' => $date,
                    'time' => $time,
                ];
            }
        }

        $availability = [
            'frequency' => $frequency,
            'times' => $times,
            'days_of_week' => $days,
            'custom_slots' => $custom_slots,
            'slot_capacity' => $slot_capacity,
            'lead_time_hours' => $lead_time,
            'buffer_before_minutes' => $buffer_before,
            'buffer_after_minutes' => $buffer_after,
        ];

        if ('custom' !== $frequency) {
            $availability['custom_slots'] = [];
        }

        if ('weekly' !== $frequency) {
            $availability['days_of_week'] = [];
        }

        if ('custom' === $frequency) {
            $availability['times'] = [];
        }

        if (
            [] === $availability['times']
            && [] === $availability['custom_slots']
            && 0 === $availability['slot_capacity']
        ) {
            delete_post_meta($post_id, '_fp_exp_availability');
        } else {
            update_post_meta($post_id, '_fp_exp_availability', $availability);
        }

        $recurrence_raw = isset($raw['recurrence']) && is_array($raw['recurrence']) ? $raw['recurrence'] : [];
        $recurrence_meta = Recurrence::sanitize($recurrence_raw);

        if (! empty($recurrence_meta['enabled']) || ! empty($recurrence_meta['time_sets'])) {
            update_post_meta($post_id, '_fp_exp_recurrence', $recurrence_meta);
        } else {
            delete_post_meta($post_id, '_fp_exp_recurrence');
        }

        $this->update_or_delete_meta($post_id, '_fp_lead_time_hours', $lead_time);
        $this->update_or_delete_meta($post_id, '_fp_buffer_before_minutes', $buffer_before);
        $this->update_or_delete_meta($post_id, '_fp_buffer_after_minutes', $buffer_after);
    }
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
        $what_to_bring = isset($raw['what_to_bring']) ? sanitize_text_field((string) $raw['what_to_bring']) : '';
        $notes = isset($raw['notes']) ? sanitize_text_field((string) $raw['notes']) : '';

        $this->update_or_delete_meta($post_id, '_fp_highlights', $highlights);
        $this->update_or_delete_meta($post_id, '_fp_inclusions', $inclusions);
        $this->update_or_delete_meta($post_id, '_fp_exclusions', $exclusions);
        $this->update_or_delete_meta($post_id, '_fp_what_to_bring', $what_to_bring);
        $this->update_or_delete_meta($post_id, '_fp_notes', $notes);
    }
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
    private function get_details_meta(int $post_id): array
    {
        $languages_meta = get_post_meta($post_id, '_fp_languages', true);
        $languages = is_array($languages_meta) ? array_filter(array_map('sanitize_text_field', $languages_meta)) : [];

        return [
            'short_desc' => sanitize_text_field((string) get_post_meta($post_id, '_fp_short_desc', true)),
            'duration_minutes' => absint((string) get_post_meta($post_id, '_fp_duration_minutes', true)),
            'languages' => implode(', ', $languages),
            'language_badges' => LanguageHelper::build_language_badges($languages),
            'linked_page' => $this->get_linked_page_details($post_id),
            'min_party' => absint((string) get_post_meta($post_id, '_fp_min_party', true)),
            'capacity_slot' => absint((string) get_post_meta($post_id, '_fp_capacity_slot', true)),
            'age_min' => absint((string) get_post_meta($post_id, '_fp_age_min', true)),
            'age_max' => absint((string) get_post_meta($post_id, '_fp_age_max', true)),
            'rules_children' => sanitize_text_field((string) get_post_meta($post_id, '_fp_rules_children', true)),
            'hero_image' => $this->get_hero_image($post_id),
            'cognitive_biases' => [
                'choices' => Helpers::cognitive_bias_choices(),
                'selected' => $this->get_selected_cognitive_biases($post_id),
            ],
            'taxonomies' => [
                'theme' => [
                    'choices' => $this->get_taxonomy_choices('fp_exp_theme'),
                    'selected' => $this->get_assigned_terms($post_id, 'fp_exp_theme'),
                ],
                'language' => [
                    'choices' => $this->get_taxonomy_choices('fp_exp_language'),
                    'selected' => $this->get_assigned_terms($post_id, 'fp_exp_language'),
                ],
                'duration' => [
                    'choices' => $this->get_taxonomy_choices('fp_exp_duration'),
                    'selected' => $this->get_assigned_terms($post_id, 'fp_exp_duration'),
                ],
                'family' => [
                    'choices' => $this->get_taxonomy_choices('fp_exp_family_friendly'),
                    'selected' => $this->get_assigned_terms($post_id, 'fp_exp_family_friendly'),
                ],
            ],
        ];
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
    private function get_pricing_meta(int $post_id): array
    {
        $defaults = [
            'tickets' => [],
            'group' => [],
            'addons' => [],
            'tax_class' => '',
        ];

        $meta = get_post_meta($post_id, '_fp_exp_pricing', true);
        if (! is_array($meta)) {
            return $defaults;
        }

        return array_merge($defaults, $meta);
    }
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

        $stored['enabled'] = ! empty($stored['enabled']);
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

        $stored['time_sets'] = $time_sets;

        return array_merge(Recurrence::defaults(), $stored);
    }
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
    private function get_extras_meta(int $post_id): array
    {
        $highlights = get_post_meta($post_id, '_fp_highlights', true);
        $inclusions = get_post_meta($post_id, '_fp_inclusions', true);
        $exclusions = get_post_meta($post_id, '_fp_exclusions', true);

        return [
            'highlights' => $this->array_to_lines($highlights),
            'inclusions' => $this->array_to_lines($inclusions),
            'exclusions' => $this->array_to_lines($exclusions),
            'what_to_bring' => sanitize_text_field((string) get_post_meta($post_id, '_fp_what_to_bring', true)),
            'notes' => sanitize_text_field((string) get_post_meta($post_id, '_fp_notes', true)),
        ];
    }
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

            update_post_meta($post_id, $key, $value);
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
            return array_values(array_filter(array_map('sanitize_text_field', $value)));
        }

        $lines = preg_split('/\r?\n/', (string) $value);
        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_text_field', $lines)));
    }

    private function array_to_lines($value): string
    {
        if (! is_array($value)) {
            return '';
        }

        $items = array_values(array_filter(array_map('sanitize_text_field', $value)));
        return implode("\n", $items);
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
     */
    private function get_recurrence_frequency_summary(string $frequency, array $days): string
    {
        $template = $this->get_recurrence_frequency_summary_template($frequency);

        if ('' === $template) {
            return '';
        }

        if ('weekly' !== $frequency) {
            return $template;
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

        if (! $selected_labels) {
            return sprintf($template, esc_html__('Nessun giorno selezionato', 'fp-experiences'));
        }

        return sprintf($template, implode(', ', $selected_labels));
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
