<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Application\Settings\GetSettingsUseCase;
use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\LanguageHelper;
use FP_Exp\Utils\Theme;
use WP_Comment;
use WP_Error;
use WP_Post;

use function absint;
use function array_fill_keys;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function apply_filters;
use function do_shortcode;
use function esc_attr;
use function __;
use function _n;
use function esc_html__;
use function explode;
use function get_bloginfo;
use function get_comment_meta;
use function get_comments;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_post_type;
use function get_the_ID;
use function get_the_terms;
use function home_url;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function number_format_i18n;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function trim;
use function strtolower;
use function wp_get_attachment_image_src;
use function wp_get_attachment_image_srcset;
use function wp_get_attachment_image_url;
use function wp_get_post_terms;
use function wp_json_encode;
use function wp_kses_post;
use function wp_trim_words;

final class ExperienceShortcode extends BaseShortcode
{
    private ?ExperienceRepositoryInterface $experienceRepository = null;
    private ?GetSettingsUseCase $getSettingsUseCase = null;

    private const ALLOWED_SECTIONS = [
        'hero',
        'overview',
        'gallery',
        'highlights',
        'inclusions',
        'meeting',
        'extras',
        'faq',
        'reviews',
    ];

    protected string $tag = 'fp_exp_page';

    protected string $template = 'front/experience.php';

    protected array $defaults = [
        'id' => '',
        'sections' => '',
        'sticky_widget' => '1',
        'preset' => '',
        'mode' => '',
        'primary' => '',
        'secondary' => '',
        'accent' => '',
        'background' => '',
        'surface' => '',
        'text' => '',
        'muted' => '',
        'success' => '',
        'warning' => '',
        'danger' => '',
        'radius' => '',
        'shadow' => '',
        'font' => '',
        'container' => '',
        'max_width' => '',
        'gutter' => '',
        'sidebar' => '',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $experience_id = $this->resolve_experience_id($attributes);

        if ($experience_id <= 0) {
            return new WP_Error('fp_exp_page_invalid', esc_html__('Missing experience ID.', 'fp-experiences'));
        }

        $post = get_post($experience_id);

        if (! $post instanceof WP_Post || 'fp_experience' !== $post->post_type) {
            return new WP_Error('fp_exp_page_not_found', esc_html__('Experience not found.', 'fp-experiences'));
        }

        $enabled_sections = $this->resolve_sections((string) $attributes['sections']);

        $theme = Theme::resolve_palette([
            'preset' => (string) $attributes['preset'],
            'mode' => (string) $attributes['mode'],
            'primary' => (string) $attributes['primary'],
            'secondary' => (string) $attributes['secondary'],
            'accent' => (string) $attributes['accent'],
            'background' => (string) $attributes['background'],
            'surface' => (string) $attributes['surface'],
            'text' => (string) $attributes['text'],
            'muted' => (string) $attributes['muted'],
            'success' => (string) $attributes['success'],
            'warning' => (string) $attributes['warning'],
            'danger' => (string) $attributes['danger'],
            'radius' => (string) $attributes['radius'],
            'shadow' => (string) $attributes['shadow'],
            'font' => (string) $attributes['font'],
        ]);

        $duration_minutes = absint((string) get_post_meta($experience_id, '_fp_duration_minutes', true));
        $taxonomy_languages = wp_get_post_terms($experience_id, 'fp_exp_language', ['fields' => 'names']);
        $language_term_names = is_array($taxonomy_languages)
            ? array_values(array_filter(array_map('sanitize_text_field', $taxonomy_languages)))
            : [];

        $languages = Helpers::get_meta_array($experience_id, '_fp_languages');
        if (empty($languages)) {
            $languages = $language_term_names;
        }

        $language_badges = LanguageHelper::build_language_badges($languages);
        $short_desc = trim(sanitize_text_field((string) get_post_meta($experience_id, '_fp_short_desc', true)));
        $content_summary = (string) $post->post_excerpt;
        $schema_description = '' !== $short_desc ? $short_desc : $content_summary;

        $hero_image_id = absint((string) get_post_meta($experience_id, '_fp_hero_image_id', true));
        $gallery_ids = get_post_meta($experience_id, '_fp_gallery_ids', true);
        $gallery = $this->prepare_gallery($experience_id, $gallery_ids, $hero_image_id);
        $gallery_video_url = esc_url((string) get_post_meta($experience_id, '_fp_gallery_video_url', true));

        $highlights = Helpers::get_meta_array($experience_id, '_fp_highlights');
        $inclusions = Helpers::get_meta_array($experience_id, '_fp_inclusions');
        $exclusions = Helpers::get_meta_array($experience_id, '_fp_exclusions');
        $what_to_bring = Helpers::get_meta_array($experience_id, '_fp_what_to_bring');

        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $notes_raw = null;
        if ($repo !== null) {
            $notes_raw = $repo->getMeta($experience_id, '_fp_notes', null);
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $notes_raw = get_post_meta($experience_id, '_fp_notes', true);
        }
        if (is_array($notes_raw)) {
            $notes = array_values(array_filter(array_map('wp_kses_post', array_map('strval', $notes_raw)), static function (string $item): bool {
                $trimmed = trim($item);
                return '' !== $trimmed && strtolower($trimmed) !== 'array';
            }));
        } else {
            $notes_string = (string) $notes_raw;
            // Gestisce il caso di dati corrotti dove è stata salvata la stringa "Array"
            if (strtolower(trim($notes_string)) === 'array') {
                $notes = '';
            } else {
                $notes = '' !== $notes_string ? wp_kses_post($notes_string) : '';
            }
        }

        $policy_raw = (string) get_post_meta($experience_id, '_fp_policy_cancel', true);
        // Gestisce il caso di dati corrotti dove è stata salvata la stringa "Array"
        $policy = (strtolower(trim($policy_raw)) === 'array') ? '' : wp_kses_post($policy_raw);
        $children_rules_raw = (string) get_post_meta($experience_id, '_fp_rules_children', true);
        // Gestisce il caso di dati corrotti dove è stata salvata la stringa "Array"
        $children_rules = (strtolower(trim($children_rules_raw)) === 'array') ? '' : sanitize_textarea_field($children_rules_raw);

        $faq_meta = get_post_meta($experience_id, '_fp_faq', true);
        $faq_items = $this->prepare_faq($faq_meta);

        $reviews = $this->load_reviews($experience_id);

        $meeting_data = $this->prepare_meeting_points($experience_id, $enabled_sections['meeting']);

        $tickets = $this->prepare_tickets(get_post_meta($experience_id, '_fp_ticket_types', true));
        $addons = $this->prepare_addons(get_post_meta($experience_id, '_fp_addons', true));
        $base_price = $this->resolve_price($experience_id, $tickets);
        $currency = $this->resolve_currency();

        $schema = $this->build_schema([
            'post' => $post,
            'gallery' => $gallery,
            'description' => $schema_description,
            'price' => $base_price,
            'currency' => $currency,
            'duration' => $duration_minutes,
            'languages' => $languages,
            'meeting' => $meeting_data,
        ]);

        $data_layer = [
            'event' => 'view_item',
            'ecommerce' => [
                'currency' => $currency,
                'value' => $base_price,
                'items' => [
                    [
                        'item_id' => (string) $experience_id,
                        'item_name' => $post->post_title,
                        'price' => $base_price,
                        'quantity' => 1,
                    ],
                ],
            ],
        ];

        $layout_defaults = $this->get_layout_defaults();
        $layout = $this->resolve_layout($attributes, $layout_defaults);
        $render_widget = 'none' !== $layout['sidebar'];
        $sticky_requested = in_array((string) ($attributes['sticky_widget'] ?? '1'), ['1', 'true', 'yes'], true);
        $is_sticky_widget = $render_widget && $sticky_requested;

        $widget_atts = [
            'id' => (string) $experience_id,
            'sticky' => $is_sticky_widget ? '1' : '0',
            'display_context' => 'page',
        ];

        $theme_keys = ['preset', 'mode', 'primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'success', 'warning', 'danger', 'radius', 'shadow', 'font'];
        foreach ($theme_keys as $key) {
            if (! empty($attributes[$key])) {
                $widget_atts[$key] = (string) $attributes[$key];
            }
        }

        $widget_html = $render_widget
            ? do_shortcode('[fp_exp_widget ' . $this->build_shortcode_atts($widget_atts) . ']')
            : '';

        $experience_badge_slugs = Helpers::get_meta_array($post->ID, '_fp_experience_badges');

        $experience_badges = Helpers::experience_badge_payload($experience_badge_slugs);
        // Applica override titolo/descrizione specifici dell'esperienza e unisci badge personalizzati
        $badge_overrides = get_post_meta($post->ID, '_fp_experience_badge_overrides', true);
        $badge_overrides = is_array($badge_overrides) ? $badge_overrides : [];
        foreach ($experience_badges as &$badge_ref) {
            if (! is_array($badge_ref)) {
                continue;
            }
            $bid = isset($badge_ref['id']) ? sanitize_key((string) $badge_ref['id']) : '';
            if ('' === $bid || ! isset($badge_overrides[$bid]) || ! is_array($badge_overrides[$bid])) {
                continue;
            }
            $ovr = $badge_overrides[$bid];
            if (isset($ovr['label']) && '' !== trim((string) $ovr['label'])) {
                $badge_ref['label'] = sanitize_text_field((string) $ovr['label']);
            }
            if (array_key_exists('description', $ovr)) {
                $badge_ref['description'] = sanitize_text_field((string) $ovr['description']);
            }
        }
        unset($badge_ref);

        $custom_badges = get_post_meta($post->ID, '_fp_experience_badge_custom', true);
        if (is_array($custom_badges)) {
            foreach ($custom_badges as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $cid = isset($entry['id']) ? sanitize_key((string) $entry['id']) : '';
                $clabel = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
                // Accetta badge se ha almeno il label (id può essere vuoto per badge nuovi)
                if ('' === $clabel) {
                    continue;
                }
                // Se id è vuoto, genera uno slug dal label
                if ('' === $cid) {
                    $cid = sanitize_key($clabel);
                }
                $cdesc = isset($entry['description']) ? sanitize_text_field((string) $entry['description']) : '';
                $experience_badges[] = [
                    'id' => $cid,
                    'label' => $clabel,
                    'description' => $cdesc,
                    'icon' => 'default',
                ];
            }
        }
        $cognitive_bias_meta = get_post_meta($post->ID, '_fp_cognitive_biases', true);
        $cognitive_bias_slugs = is_array($cognitive_bias_meta)
            ? array_values(array_filter(array_map('sanitize_key', $cognitive_bias_meta)))
            : [];
        $cognitive_bias_badges = Helpers::cognitive_bias_badges($cognitive_bias_slugs);

        $badges = $this->build_badges($duration_minutes, $language_badges, $experience_badges);

        $primary_meeting = isset($meeting_data['primary']) && is_array($meeting_data['primary'])
            ? $meeting_data['primary']
            : null;
        $meeting_title = '';
        $meeting_address = '';
        $meeting_summary = '';

        if (is_array($primary_meeting)) {
            $meeting_title = sanitize_text_field((string) ($primary_meeting['title'] ?? ''));
            $meeting_address = sanitize_text_field((string) ($primary_meeting['address'] ?? ''));

            if ('' !== $meeting_title && '' !== $meeting_address) {
                $meeting_summary = $meeting_title . ' — ' . $meeting_address;
            } else {
                $meeting_summary = $meeting_title ?: $meeting_address;
            }
        }

        $overview = [
            'language_terms' => $language_term_names,
            'language_badges' => $language_badges,
            'experience_badges' => $experience_badges,
            'short_description' => $short_desc,
            'meeting' => [
                'title' => $meeting_title,
                'address' => $meeting_address,
                'summary' => $meeting_summary,
            ],
            'cognitive_biases' => $cognitive_bias_badges,
        ];

        $overview_has_content = $this->overview_has_content($overview);
        if (! $overview_has_content) {
            $enabled_sections['overview'] = false;
        }

        $missing_meta = [];

        if ($enabled_sections['highlights'] && empty($highlights)) {
            $missing_meta[] = '_fp_highlights';
        }

        if ($enabled_sections['inclusions']) {
            if (empty($inclusions)) {
                $missing_meta[] = '_fp_inclusions';
            }

            if (empty($exclusions)) {
                $missing_meta[] = '_fp_exclusions';
            }
        }

        if ($enabled_sections['extras']) {
            if (empty($what_to_bring)) {
                $missing_meta[] = '_fp_what_to_bring';
            }

            if (empty($notes)) {
                $missing_meta[] = '_fp_notes';
            }

            if (empty($policy)) {
                $missing_meta[] = '_fp_policy_cancel';
            }
        }

        if ($enabled_sections['faq'] && empty($faq_items)) {
            $missing_meta[] = '_fp_faq';
        }

        if ($enabled_sections['meeting'] && Helpers::meeting_points_enabled() && empty($meeting_data['primary'])) {
            $missing_meta[] = '_fp_meeting_point_id';
        }

        if (! empty($missing_meta)) {
            $normalized_keys = array_values(array_unique(array_map(static fn ($key) => sanitize_key((string) $key), $missing_meta)));
            Helpers::log_debug('shortcodes', 'Experience shortcode missing meta', [
                'shortcode' => $this->tag,
                'experience_id' => $experience_id,
                'meta_keys' => $normalized_keys,
            ]);
        }

        $price_from = $this->calculate_price_from_meta($experience_id);
        $price_from_display = null !== $price_from && $price_from > 0 ? number_format_i18n($price_from, 0) : '';

        return [
            'theme' => $theme,
            'experience' => [
                'id' => $experience_id,
                'title' => $post->post_title,
                'permalink' => get_permalink($post),
                'summary' => $content_summary,
                'short_description' => $short_desc,
                'duration_minutes' => $duration_minutes,
                'languages' => $languages,
                'language_badges' => $language_badges,
                'price_from' => $price_from,
                'price_from_display' => $price_from_display,
            ],
            'gallery' => $gallery,
            'gallery_video_url' => $gallery_video_url,
            'badges' => $badges,
            'highlights' => $highlights,
            'inclusions' => $inclusions,
            'exclusions' => $exclusions,
            'what_to_bring' => $what_to_bring,
            'notes' => $notes,
            'policy' => $policy,
            'children_rules' => $children_rules,
            'faq' => $faq_items,
            'reviews' => $reviews,
            'sections' => $enabled_sections,
            'meeting_points' => $meeting_data,
            'sticky_widget' => $is_sticky_widget,
            'widget_html' => $widget_html,
            'schema_json' => $schema,
            'data_layer' => wp_json_encode($data_layer),
            'layout' => $layout,
            'gift' => [
                'enabled' => Helpers::gift_enabled(),
                'experience_id' => $experience_id,
                'experience_title' => $post->post_title,
                'addons' => $addons,
                'validity_days' => Helpers::gift_validity_days(),
                'redeem_page' => Helpers::gift_redeem_page(),
                'currency' => get_option('woocommerce_currency', 'EUR'),
            ],
            'overview' => $overview,
            'overview_has_content' => $overview_has_content,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function resolve_experience_id(array $attributes): int
    {
        $experience_id = absint($attributes['id'] ?? 0);

        if ($experience_id > 0) {
            return $experience_id;
        }

        $current_id = absint(get_the_ID() ?: 0);
        if ($current_id <= 0) {
            Helpers::log_debug('shortcodes', 'Experience shortcode missing explicit ID', [
                'shortcode' => $this->tag,
                'context_post_type' => '',
                'context_post_id' => 0,
            ]);

            return 0;
        }

        $post_type = get_post_type($current_id) ?: '';

        if ('fp_experience' === $post_type) {
            return $current_id;
        }

        $linked_experience_id = absint(get_post_meta($current_id, '_fp_experience_id', true));
        if ($linked_experience_id > 0) {
            return $linked_experience_id;
        }

        Helpers::log_debug('shortcodes', 'Experience shortcode missing explicit ID', [
            'shortcode' => $this->tag,
            'context_post_type' => $post_type,
            'context_post_id' => $current_id,
        ]);

        return 0;
    }

    private function calculate_price_from_meta(int $experience_id): ?float
    {
        // Try to read from _fp_ticket_types first (legacy)
        $tickets = get_post_meta($experience_id, '_fp_ticket_types', true);
        
        // If _fp_ticket_types doesn't have the flag, try reading from _fp_exp_pricing
        $pricing_meta = null;
        if (!is_array($tickets) || empty($tickets)) {
            $pricing_meta = get_post_meta($experience_id, '_fp_exp_pricing', true);
            if (is_array($pricing_meta) && isset($pricing_meta['tickets']) && is_array($pricing_meta['tickets'])) {
                $tickets = $pricing_meta['tickets'];
            }
        }
        
        // Also check if tickets from _fp_ticket_types don't have use_as_price_from flag
        // If so, try to get it from _fp_exp_pricing
        $has_primary_flag = false;
        if (is_array($tickets) && !empty($tickets)) {
            foreach ($tickets as $ticket) {
                if (is_array($ticket) && isset($ticket['use_as_price_from'])) {
                    $has_primary_flag = true;
                    break;
                }
            }
        }
        
        // If no flag found in _fp_ticket_types, try _fp_exp_pricing
        if (!$has_primary_flag && $pricing_meta === null) {
            $pricing_meta = get_post_meta($experience_id, '_fp_exp_pricing', true);
            if (is_array($pricing_meta) && isset($pricing_meta['tickets']) && is_array($pricing_meta['tickets'])) {
                // Merge tickets from _fp_exp_pricing, prioritizing those with use_as_price_from
                $pricing_tickets = $pricing_meta['tickets'];
                if (is_array($tickets) && !empty($tickets)) {
                    // Update existing tickets with flag from pricing meta
                    foreach ($pricing_tickets as $pricing_ticket) {
                        if (!is_array($pricing_ticket)) {
                            continue;
                        }
                        // Find matching ticket by label or slug
                        foreach ($tickets as $index => $ticket) {
                            if (!is_array($ticket)) {
                                continue;
                            }
                            $match = false;
                            if (isset($pricing_ticket['label']) && isset($ticket['label']) && $pricing_ticket['label'] === $ticket['label']) {
                                $match = true;
                            } elseif (isset($pricing_ticket['slug']) && isset($ticket['slug']) && $pricing_ticket['slug'] === $ticket['slug']) {
                                $match = true;
                            }
                            if ($match && isset($pricing_ticket['use_as_price_from'])) {
                                $tickets[$index]['use_as_price_from'] = $pricing_ticket['use_as_price_from'];
                                $has_primary_flag = true;
                            }
                        }
                    }
                } else {
                    // Use tickets from pricing meta directly
                    $tickets = $pricing_tickets;
                }
            }
        }
        
        if (! is_array($tickets) || empty($tickets)) {
            return null;
        }

        // Debug: log tickets data (only if WP_DEBUG is enabled)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-EXP] calculate_price_from_meta - Tickets: ' . print_r($tickets, true));
        }

        // First, look for a ticket marked as "use_as_price_from"
        // Check both boolean true and string "1" for compatibility
        foreach ($tickets as $index => $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            // Check if this ticket is marked as primary price display
            // WordPress may serialize boolean as string, so check multiple formats
            $use_as_price_from = $ticket['use_as_price_from'] ?? false;
            $is_primary = false;
            
            if (isset($ticket['use_as_price_from'])) {
                // Check various formats that WordPress might use
                if ($use_as_price_from === true 
                    || $use_as_price_from === '1' 
                    || $use_as_price_from === 1
                    || (is_string($use_as_price_from) && strtolower(trim($use_as_price_from)) === 'true')
                    || (is_string($use_as_price_from) && strtolower(trim($use_as_price_from)) === 'yes')
                ) {
                    $is_primary = true;
                }
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[FP-EXP] Ticket %d: label=%s, price=%s, use_as_price_from=%s (type: %s), is_primary=%s',
                    $index,
                    $ticket['label'] ?? 'N/A',
                    $ticket['price'] ?? 'N/A',
                    var_export($use_as_price_from, true),
                    gettype($use_as_price_from),
                    $is_primary ? 'YES' : 'NO'
                ));
            }

            if ($is_primary) {
                $price = (float) $ticket['price'];
                if ($price > 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf('[FP-EXP] Using primary ticket price: %s (from ticket: %s)', $price, $ticket['label'] ?? 'N/A'));
                    }
                    return $price;
                }
            }
        }

        // If no ticket is marked, fall back to the lowest price
        $min_price = null;
        $min_ticket_label = '';
        foreach ($tickets as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['price'])) {
                continue;
            }

            $price = (float) $ticket['price'];
            if ($price > 0 && (null === $min_price || $price < $min_price)) {
                $min_price = $price;
                $min_ticket_label = $ticket['label'] ?? 'N/A';
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[FP-EXP] No primary ticket found, using min price: %s (from ticket: %s)', $min_price ?? 'N/A', $min_ticket_label));
        }

        return $min_price;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, int|string> $defaults
     *
     * @return array{container: string, max_width: int, gutter: int, sidebar: string}
     */
    private function resolve_layout(array $attributes, array $defaults): array
    {
        $container = $this->normalize_container((string) ($attributes['container'] ?? ''), (string) $defaults['container']);
        $sidebar = $this->normalize_sidebar((string) ($attributes['sidebar'] ?? ''), (string) $defaults['sidebar']);
        $max_width = $this->normalize_dimension($attributes['max_width'] ?? '', (int) $defaults['max_width']);
        $gutter = $this->normalize_dimension($attributes['gutter'] ?? '', (int) $defaults['gutter']);

        return [
            'container' => $container,
            'max_width' => $max_width,
            'gutter' => $gutter,
            'sidebar' => $sidebar,
        ];
    }

    /**
     * @return array{container: string, max_width: int, gutter: int, sidebar: string}
     */
    private function get_layout_defaults(): array
    {
        $defaults = [
            'container' => 'boxed',
            'max_width' => 1200,
            'gutter' => 24,
            'sidebar' => 'right',
        ];

        $option = get_option('fp_exp_experience_layout', []);
        if (! is_array($option)) {
            return $defaults;
        }

        if (isset($option['container'])) {
            $defaults['container'] = $this->normalize_container((string) $option['container'], $defaults['container']);
        }

        if (array_key_exists('max_width', $option)) {
            $defaults['max_width'] = $this->normalize_dimension($option['max_width'], (int) $defaults['max_width']);
        }

        if (array_key_exists('gutter', $option)) {
            $defaults['gutter'] = $this->normalize_dimension($option['gutter'], (int) $defaults['gutter']);
        }

        if (isset($option['sidebar'])) {
            $defaults['sidebar'] = $this->normalize_sidebar((string) $option['sidebar'], $defaults['sidebar']);
        }

        return $defaults;
    }

    private function normalize_container(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $allowed = ['boxed', 'full'];

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return in_array($fallback, $allowed, true) ? $fallback : 'boxed';
    }

    private function normalize_sidebar(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $allowed = ['right', 'left', 'none'];

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return in_array($fallback, $allowed, true) ? $fallback : 'right';
    }

    /**
     * @param mixed $value
     */
    private function normalize_dimension($value, int $fallback): int
    {
        if (is_numeric($value)) {
            $int_value = (int) $value;

            if ($int_value >= 0) {
                return $int_value;
            }
        }

        return max(0, $fallback);
    }

    private function resolve_sections(string $raw): array
    {
        $defaults = array_fill_keys(self::ALLOWED_SECTIONS, true);
        $raw = trim($raw);

        if ('' === $raw) {
            return $defaults;
        }

        $selected = array_map('trim', explode(',', $raw));
        $selected = array_filter($selected);

        if (empty($selected)) {
            return $defaults;
        }

        $normalized = [];
        foreach (self::ALLOWED_SECTIONS as $section) {
            $normalized[$section] = in_array($section, $selected, true);
        }

        return $normalized;
    }

    /**
     * @param mixed $raw
     * @param int   $hero_id
     *
     * @return array<int, array<string, string>>
     */
    private function prepare_gallery(int $experience_id, $raw, int $hero_id): array
    {
        $images = [];
        $ids = [];

        if ($hero_id > 0) {
            $ids[] = $hero_id;
        }

        if (is_array($raw)) {
            foreach ($raw as $value) {
                $id = absint($value);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            $thumbnail_id = get_post_thumbnail_id($experience_id);
            if ($thumbnail_id) {
                $ids[] = (int) $thumbnail_id;
            }
        }

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            $url = wp_get_attachment_image_url($id, 'large');
            if (! $url) {
                continue;
            }

            $src = wp_get_attachment_image_src($id, 'large');
            $srcset = wp_get_attachment_image_srcset($id, 'large');
            $attachment = get_post($id);
            $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
            $caption = '';

            if ($attachment instanceof WP_Post) {
                if ('' === $alt) {
                    $alt = trim((string) $attachment->post_title);
                }

                $caption = trim((string) $attachment->post_excerpt);
            }

            $images[] = [
                'id' => $id,
                'url' => $url,
                'width' => is_array($src) ? ($src[1] ?? '') : '',
                'height' => is_array($src) ? ($src[2] ?? '') : '',
                'srcset' => $srcset ?: '',
                'alt' => $alt,
                'caption' => $caption,
            ];
        }

        return $images;
    }

    /**
     * @param array<string, mixed> $overview
     */
    private function overview_has_content(array $overview): bool
    {
        if (empty($overview)) {
            return false;
        }

        $string_fields = ['short_description'];
        foreach ($string_fields as $key) {
            $value = isset($overview[$key]) ? (string) $overview[$key] : '';
            if ('' !== trim($value)) {
                return true;
            }
        }

        $array_fields = ['themes', 'language_terms', 'duration_terms'];
        foreach ($array_fields as $key) {
            if (! isset($overview[$key]) || ! is_array($overview[$key])) {
                continue;
            }

            foreach ($overview[$key] as $value) {
                if ('' !== trim((string) $value)) {
                    return true;
                }
            }
        }

        if (isset($overview['experience_badges']) && is_array($overview['experience_badges'])) {
            foreach ($overview['experience_badges'] as $badge) {
                if (is_array($badge)) {
                    $label = isset($badge['label']) ? (string) $badge['label'] : '';
                } else {
                    $label = (string) $badge;
                }

                if ('' !== trim($label)) {
                    return true;
                }
            }
        }

        if (isset($overview['meeting']) && is_array($overview['meeting'])) {
            foreach (['title', 'address', 'summary'] as $meeting_key) {
                $meeting_value = isset($overview['meeting'][$meeting_key])
                    ? (string) $overview['meeting'][$meeting_key]
                    : '';

                if ('' !== trim($meeting_value)) {
                    return true;
                }
            }
        }

        $biases = $overview['cognitive_biases'] ?? [];

        if (! is_array($biases)) {
            return false;
        }

        foreach ($biases as $bias) {
            if (is_array($bias)) {
                $label = isset($bias['label']) ? (string) $bias['label'] : '';
            } else {
                $label = (string) $bias;
            }

            if ('' !== trim($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $raw
     *
     * @return array<int, array<string, string>>
     */
    private function prepare_faq($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $question = isset($entry['question']) ? sanitize_text_field((string) $entry['question']) : '';
            $answer = isset($entry['answer']) ? wp_kses_post((string) $entry['answer']) : '';

            if (! $question || ! $answer) {
                continue;
            }

            $items[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $items;
    }

    /**
     * @return array{primary: ?array<string, mixed>, alternatives: array<int, array<string, mixed>>}
     */
    private function prepare_meeting_points(int $experience_id, bool $section_enabled): array
    {
        if (! $section_enabled || ! Helpers::meeting_points_enabled()) {
            return ['primary' => null, 'alternatives' => []];
        }

        return Repository::get_meeting_points_for_experience($experience_id);
    }

    /**
     * @param mixed $raw
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepare_tickets($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $tickets = [];
        foreach ($raw as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            $price = isset($ticket['price']) ? (float) $ticket['price'] : 0.0;
            $tickets[] = [
                'label' => sanitize_text_field((string) ($ticket['label'] ?? '')),
                'price' => $price,
            ];
        }

        return $tickets;
    }

    /**
     * @param mixed $raw
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepare_addons($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $addons = [];

        foreach ($raw as $addon) {
            if (! is_array($addon)) {
                continue;
            }

            $slug = sanitize_key($addon['slug'] ?? ($addon['label'] ?? ''));
            if ('' === $slug) {
                continue;
            }

            $image_id = isset($addon['image_id']) ? absint($addon['image_id']) : 0;
            $image = $image_id > 0 ? wp_get_attachment_image_src($image_id, 'medium') : false;

            $selection_type = isset($addon['selection_type']) ? sanitize_key((string) $addon['selection_type']) : 'checkbox';
            if (! in_array($selection_type, ['checkbox', 'radio'], true)) {
                $selection_type = 'checkbox';
            }

            $addons[] = [
                'slug' => $slug,
                'label' => sanitize_text_field((string) ($addon['label'] ?? '')),
                'description' => sanitize_text_field((string) ($addon['description'] ?? '')),
                'price' => isset($addon['price']) ? (float) $addon['price'] : 0.0,
                'image' => [
                    'id' => $image_id,
                    'url' => $image ? (string) $image[0] : '',
                    'width' => $image ? absint((string) $image[1]) : 0,
                    'height' => $image ? absint((string) $image[2]) : 0,
                ],
                'selection_type' => $selection_type,
                'selection_group' => sanitize_text_field((string) ($addon['selection_group'] ?? '')),
            ];
        }

        return $addons;
    }

    /**
     * @param array<int, array<string, mixed>> $tickets
     */
    private function resolve_price(int $experience_id, array $tickets): float
    {
        // Use the price from the first valid ticket type instead of the minimum
        foreach ($tickets as $ticket) {
            if (! isset($ticket['price'])) {
                continue;
            }

            $price = $ticket['price'];

            if (is_numeric($price) && (float) $price > 0) {
                return (float) $price;
            }
        }

        $base_price = get_post_meta($experience_id, '_fp_base_price', true);

        return is_numeric($base_price) ? (float) $base_price : 0.0;
    }

    private function resolve_currency(): string
    {
        $currency = get_option('woocommerce_currency');

        if (is_string($currency) && $currency) {
            return $currency;
        }

        return 'EUR';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function build_schema(array $context): string
    {
        /** @var WP_Post $post */
        $post = $context['post'];
        $images = array_map(static fn (array $image) => $image['url'], $context['gallery']);
        $duration_minutes = (int) ($context['duration'] ?? 0);
        $duration_iso = $duration_minutes > 0 ? 'PT' . $duration_minutes . 'M' : null;
        $meeting = $context['meeting'];
        $primary_meeting = is_array($meeting) ? ($meeting['primary'] ?? null) : null;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $post->post_title,
            'description' => $context['description'],
            'url' => get_permalink($post),
            'image' => $images,
            'provider' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (float) $context['price'],
                'priceCurrency' => $context['currency'],
                'url' => get_permalink($post),
                'availability' => 'https://schema.org/InStock',
            ],
        ];

        if (! empty($context['languages'])) {
            $schema['inLanguage'] = array_values($context['languages']);
        }

        if ($duration_iso) {
            $schema['eventSchedule'] = [
                '@type' => 'Schedule',
                'duration' => $duration_iso,
            ];
        }

        if ($primary_meeting && is_array($primary_meeting)) {
            $schema['event'] = [
                '@type' => 'Event',
                'name' => $post->post_title,
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'location' => [
                    '@type' => 'Place',
                    'name' => sanitize_text_field((string) ($primary_meeting['title'] ?? '')),
                    'address' => sanitize_text_field((string) ($primary_meeting['address'] ?? '')),
                ],
            ];
        }

        return wp_json_encode($schema);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function load_reviews(int $experience_id): array
    {
        $comments = get_comments([
            'post_id' => $experience_id,
            'status' => 'approve',
            'type' => '',
            'number' => 12,
        ]);

        $reviews = [];

        foreach ($comments as $comment) {
            if (! $comment instanceof WP_Comment) {
                continue;
            }

            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            $reviews[] = [
                'author' => sanitize_text_field($comment->comment_author),
                'date' => sanitize_text_field($comment->comment_date),
                'content' => wp_kses_post($comment->comment_content),
                'rating' => is_numeric($rating) ? (float) $rating : null,
            ];
        }

        /**
         * Allow integrations to override the experience page reviews payload.
         *
         * @param array<int, array<string, mixed>> $reviews
         * @param int                               $experience_id
         */
        $reviews = apply_filters('fp_exp_experience_reviews', $reviews, $experience_id);

        return is_array($reviews) ? $reviews : [];
    }

    /**
     * @param array<int, array<string, string>> $language_badges
     * @param array<int, array<string, mixed>>  $experience_badges
     *
     * @return array<int, array<string, mixed>>
     */
    private function build_badges(int $duration_minutes, array $language_badges, array $experience_badges): array
    {
        $badges = [];

        if ($duration_minutes > 0) {
            $hours = (int) floor($duration_minutes / 60);
            $minutes = $duration_minutes % 60;
            $label = $hours > 0
                ? sprintf(esc_html__('%dh %02dm', 'fp-experiences'), $hours, $minutes)
                : sprintf(esc_html__('%d minutes', 'fp-experiences'), $minutes);

            $badges[] = [
                'icon' => 'clock',
                'label' => $label,
            ];
        }

        foreach ($language_badges as $language) {
            if (! is_array($language)) {
                continue;
            }

            $code = isset($language['code']) ? (string) $language['code'] : '';
            $sprite = isset($language['sprite']) ? (string) $language['sprite'] : '';
            $label = isset($language['label']) ? (string) $language['label'] : '';
            $aria_label = isset($language['aria_label']) ? (string) $language['aria_label'] : $label;

            if ('' === $code || '' === $sprite) {
                continue;
            }

            $badges[] = [
                'icon' => 'language',
                'label' => $code,
                'language' => [
                    'code' => $code,
                    'sprite' => $sprite,
                    'label' => $label,
                    'aria_label' => $aria_label,
                ],
            ];
        }

        foreach ($experience_badges as $badge) {
            if (! is_array($badge)) {
                continue;
            }

            $label = isset($badge['label']) ? (string) $badge['label'] : '';
            if ('' === $label) {
                continue;
            }

            $icon = isset($badge['icon']) ? (string) $badge['icon'] : '';
            $id = isset($badge['id']) ? (string) $badge['id'] : '';
            $description = isset($badge['description']) ? (string) $badge['description'] : '';

            $badges[] = [
                'icon' => $icon ?: 'default',
                'label' => $label,
                'id' => $id,
                'description' => $description,
            ];
        }

        return $badges;
    }

    /**
     * @param array<string, string> $atts
     */
    private function build_shortcode_atts(array $atts): string
    {
        $parts = [];

        foreach ($atts as $key => $value) {
            if ('' === $value) {
                continue;
            }

            $parts[] = $key . '="' . esc_attr($value) . '"';
        }

        return implode(' ', $parts);
    }

    /**
     * Get ExperienceRepository from container if available.
     */
    private function getExperienceRepository(): ?ExperienceRepositoryInterface
    {
        if ($this->experienceRepository !== null) {
            return $this->experienceRepository;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(ExperienceRepositoryInterface::class)) {
                return null;
            }

            $this->experienceRepository = $container->make(ExperienceRepositoryInterface::class);
            return $this->experienceRepository;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get GetSettingsUseCase from container if available.
     */
    private function getGetSettingsUseCase(): ?GetSettingsUseCase
    {
        if ($this->getSettingsUseCase !== null) {
            return $this->getSettingsUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(GetSettingsUseCase::class)) {
                return null;
            }

            $this->getSettingsUseCase = $container->make(GetSettingsUseCase::class);
            return $this->getSettingsUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
