<?php

declare(strict_types=1);

namespace FP_Exp\PostTypes;

use function add_action;
use function did_action;
use function register_post_meta;
use function register_post_type;
use function register_taxonomy;
use function sanitize_key;
use function sanitize_text_field;

/**
 * Registers the Experience custom post type, taxonomies, and meta.
 */
final class ExperienceCPT
{
    /**
     * Tracks hook registration status.
     */
    private bool $hooks_registered = false;

    /**
     * Register WordPress hooks for the CPT.
     */
    public function register_hooks(): void
    {
        if ($this->hooks_registered) {
            return;
        }

        $this->hooks_registered = true;

        if (did_action('init')) {
            $this->register_post_type();
            $this->register_taxonomies();
            $this->register_meta();
        } else {
            add_action('init', [$this, 'register_post_type']);
            add_action('init', [$this, 'register_taxonomies']);
            add_action('init', [$this, 'register_meta']);
        }
        add_filter('post_row_actions', [$this, 'add_quick_actions'], 10, 2);
    }

    /**
     * Ensures the CPT and taxonomies are registered immediately (useful during activation).
     */
    public function register_immediately(): void
    {
        $this->register_post_type();
        $this->register_taxonomies();
        $this->register_meta();
    }

    /**
     * Register the fp_experience post type.
     */
    public function register_post_type(): void
    {
        register_post_type(
            'fp_experience',
            [
                'labels' => [
                    'name' => __('Experiences', 'fp-experiences'),
                    'singular_name' => __('Experience', 'fp-experiences'),
                    'add_new_item' => __('Add New Experience', 'fp-experiences'),
                    'edit_item' => __('Edit Experience', 'fp-experiences'),
                    'new_item' => __('New Experience', 'fp-experiences'),
                    'view_item' => __('View Experience', 'fp-experiences'),
                    'search_items' => __('Search Experiences', 'fp-experiences'),
                    'not_found' => __('No experiences found.', 'fp-experiences'),
                    'not_found_in_trash' => __('No experiences found in Trash.', 'fp-experiences'),
                    'all_items' => __('All Experiences', 'fp-experiences'),
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'menu_position' => 20,
                'menu_icon' => 'dashicons-location-alt',
                'show_in_rest' => true,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions'],
                'has_archive' => false,
                'rewrite' => [
                    'slug' => 'experience',
                    'with_front' => false,
                ],
                'exclude_from_search' => true,
                'publicly_queryable' => true,
                'capability_type' => ['fp_experience', 'fp_experiences'],
                'map_meta_cap' => true,
                'capabilities' => $this->get_capabilities(),
            ]
        );
    }

    /**
     * Register taxonomies for experiences.
     */
    public function register_taxonomies(): void
    {
        register_taxonomy(
            'fp_exp_language',
            'fp_experience',
            [
                'labels' => [
                    'name' => __('Experience Languages', 'fp-experiences'),
                    'singular_name' => __('Experience Language', 'fp-experiences'),
                ],
                'hierarchical' => false,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'public' => true,
            ]
        );
    }

    /**
     * Register the metadata schema for fp_experience posts.
     */
    public function register_meta(): void
    {
        foreach ($this->get_meta_definitions() as $key => $definition) {
            // Set appropriate default value based on type if not explicitly defined
            $default = $definition['default'] ?? $this->get_default_for_type($definition['type']);

            $args = [
                'single' => true,
                'type' => $definition['type'],
                'auth_callback' => '__return_true',
                'default' => $default,
                'sanitize_callback' => function ($value) use ($definition) {
                    return $this->sanitize_meta_value($value, $definition['type'], $definition['items'] ?? null);
                },
            ];

            if ('array' === $definition['type']) {
                $schema = ['type' => 'array'];
                if (isset($definition['items'])) {
                    $schema['items'] = ['type' => $this->map_schema_type($definition['items'])];
                }
                $args['show_in_rest'] = [
                    'schema' => $schema,
                ];
            } elseif ('object' === $definition['type']) {
                $args['show_in_rest'] = [
                    'schema' => ['type' => 'object'],
                ];
            } else {
                $args['show_in_rest'] = true;
            }

            register_post_meta('fp_experience', $key, $args);
        }
    }

    /**
     * Returns the appropriate default value for a given meta type.
     *
     * @param string $type The meta field type
     * @return mixed The default value appropriate for the type
     */
    private function get_default_for_type(string $type)
    {
        return match ($type) {
            'string' => '',
            'integer' => 0,
            'number' => 0.0,
            'boolean' => false,
            'array' => [],
            'object' => [],
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function get_capabilities(): array
    {
        // Note: 'edit_post', 'read_post', 'delete_post' are meta-capabilities
        // handled automatically by WordPress when map_meta_cap is true.
        // They should NOT be specified here to avoid "incorrectly called" notices.
        return [
            'edit_posts' => 'edit_fp_experiences',
            'edit_others_posts' => 'edit_others_fp_experiences',
            'publish_posts' => 'publish_fp_experiences',
            'read_private_posts' => 'read_private_fp_experiences',
            'delete_posts' => 'delete_fp_experiences',
            'delete_private_posts' => 'delete_private_fp_experiences',
            'delete_published_posts' => 'delete_published_fp_experiences',
            'delete_others_posts' => 'delete_others_fp_experiences',
            'edit_private_posts' => 'edit_private_fp_experiences',
            'edit_published_posts' => 'edit_published_fp_experiences',
            'create_posts' => 'edit_fp_experiences',
        ];
    }

    /**
     * Meta definitions for the CPT.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_meta_definitions(): array
    {
        return [
            '_fp_short_desc' => [
                'type' => 'string',
            ],
            '_fp_highlights' => [
                'type' => 'array',
                'items' => 'string',
                'default' => [],
            ],
            '_fp_meeting_point' => [
                'type' => 'string',
            ],
            '_fp_meeting_point_id' => [
                'type' => 'integer',
            ],
            '_fp_meeting_point_alt' => [
                'type' => 'array',
                'items' => 'integer',
                'default' => [],
            ],
            '_fp_inclusions' => [
                'type' => 'array',
                'items' => 'string',
                'default' => [],
            ],
            '_fp_exclusions' => [
                'type' => 'array',
                'items' => 'string',
                'default' => [],
            ],
            '_fp_what_to_bring' => [
                'type' => 'string',
            ],
            '_fp_notes' => [
                'type' => 'string',
            ],
            '_fp_faq' => [
                'type' => 'array',
                'items' => 'object',
                'default' => [],
            ],
            '_fp_rules_children' => [
                'type' => 'string',
            ],
            '_fp_age_min' => [
                'type' => 'integer',
            ],
            '_fp_age_max' => [
                'type' => 'integer',
            ],
            '_fp_min_party' => [
                'type' => 'integer',
            ],
            '_fp_capacity_slot' => [
                'type' => 'integer',
            ],
            '_fp_resources' => [
                'type' => 'array',
                'items' => 'integer',
                'default' => [],
            ],
            '_fp_schedule_rules' => [
                'type' => 'array',
                'items' => 'object',
                'default' => [],
            ],
            '_fp_schedule_exceptions' => [
                'type' => 'array',
                'items' => 'object',
                'default' => [],
            ],
            '_fp_lead_time_hours' => [
                'type' => 'integer',
                'default' => 0,
            ],
            '_fp_buffer_before_minutes' => [
                'type' => 'integer',
                'default' => 0,
            ],
            '_fp_buffer_after_minutes' => [
                'type' => 'integer',
                'default' => 0,
            ],
            '_fp_ticket_types' => [
                'type' => 'array',
                'items' => 'object',
                'default' => [],
            ],
            '_fp_addons' => [
                'type' => 'array',
                'items' => 'object',
                'default' => [],
            ],
            '_fp_exp_page_id' => [
                'type' => 'integer',
            ],
            '_fp_exp_pricing' => [
                'type' => 'object',
                'default' => [],
            ],
            '_fp_exp_availability' => [
                'type' => 'object',
                'default' => [],
            ],
            '_fp_base_price' => [
                'type' => 'number',
                'default' => 0.0,
            ],
            '_fp_pricing_rules' => [
                'type' => 'array',
                'items' => 'object',
                'default' => [],
            ],
            '_fp_duration_minutes' => [
                'type' => 'integer',
            ],
            '_fp_languages' => [
                'type' => 'array',
                'items' => 'string',
                'default' => [],
            ],
            '_fp_cognitive_biases' => [
                'type' => 'array',
                'items' => 'string',
                'default' => [],
            ],
            '_fp_policy_cancel' => [
                'type' => 'string',
            ],
            '_fp_meta_title' => [
                'type' => 'string',
            ],
            '_fp_meta_description' => [
                'type' => 'string',
            ],
            '_fp_schema_manual' => [
                'type' => 'string',
            ],
            '_fp_gallery_ids' => [
                'type' => 'array',
                'items' => 'integer',
                'default' => [],
            ],
            '_fp_gallery_video_url' => [
                'type' => 'string',
                'default' => '',
            ],
            '_fp_hero_image_id' => [
                'type' => 'integer',
                'default' => 0,
            ],
            '_fp_use_rtb' => [
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    /**
     * Sanitize metadata values according to their schema.
     *
     * @param mixed       $value Raw value.
     * @param string      $type  Declared type.
     * @param string|null $items Item type for arrays.
     *
     * @return mixed
     */
    private function sanitize_meta_value($value, string $type, ?string $items = null)
    {
        switch ($type) {
            case 'integer':
                return is_numeric($value) ? (int) $value : 0;
            case 'number':
                return is_numeric($value) ? (float) $value : 0.0;
            case 'boolean':
                return (bool) $value;
            case 'array':
                $value = $this->ensure_array($value);
                return $this->sanitize_array_items($value, $items);
            case 'object':
                $value = $this->ensure_array($value);
                return $this->sanitize_associative_array($value);
            case 'string':
            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * @param mixed $value Potential array value.
     *
     * @return array<int|string, mixed>
     */
    private function ensure_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value) && '' !== $value) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return array<int|string, mixed>
     */
    private function sanitize_array_items(array $values, ?string $items): array
    {
        $sanitized = [];

        foreach ($values as $key => $item) {
            if (is_string($key)) {
                $key = sanitize_key($key);
            }

            switch ($items) {
                case 'integer':
                    $sanitized[$key] = is_numeric($item) ? (int) $item : 0;
                    break;
                case 'number':
                    $sanitized[$key] = is_numeric($item) ? (float) $item : 0.0;
                    break;
                case 'boolean':
                    $sanitized[$key] = (bool) $item;
                    break;
                case 'object':
                    $sanitized[$key] = $this->sanitize_associative_array($this->ensure_array($item));
                    break;
                case 'string':
                    $sanitized[$key] = sanitize_text_field((string) $item);
                    break;
                default:
                    $sanitized[$key] = $this->sanitize_mixed($item);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * @param array<int|string, mixed> $value
     *
     * @return array<int|string, mixed>
     */
    private function sanitize_associative_array(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            $sanitized_key = is_string($key) ? sanitize_key($key) : $key;
            $sanitized[$sanitized_key] = $this->sanitize_mixed($item);
        }

        return $sanitized;
    }

    /**
     * Sanitize mixed values recursively.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function sanitize_mixed($value)
    {
        if (is_array($value) || is_object($value)) {
            return $this->sanitize_associative_array($this->ensure_array($value));
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        if (is_bool($value)) {
            return (bool) $value;
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Map internal item type definitions to REST schema types.
     */
    private function map_schema_type(string $type): string
    {
        switch ($type) {
            case 'integer':
                return 'integer';
            case 'number':
                return 'number';
            case 'boolean':
                return 'boolean';
            case 'object':
                return 'object';
            case 'string':
            default:
                return 'string';
        }
    }

    /**
     * Add quick actions to experience post rows
     *
     * @param array<string, string> $actions
     * @param \WP_Post $post
     * @return array<string, string>
     */
    public function add_quick_actions(array $actions, $post): array
    {
        if ($post->post_type !== 'fp_experience') {
            return $actions;
        }

        $new_actions = [];

        // Add "View Live" first
        if ($post->post_status === 'publish') {
            $new_actions['view_live'] = '<a href="' . esc_url(get_permalink($post)) . '" target="_blank" aria-label="' . esc_attr__('Visualizza esperienza pubblicata', 'fp-experiences') . '">' . esc_html__('👁️ Vedi Live', 'fp-experiences') . '</a>';
        }

        // Keep default Edit
        if (isset($actions['edit'])) {
            $new_actions['edit'] = $actions['edit'];
        }

        // Add "Calendar" link
        $new_actions['calendar'] = '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar')) . '" aria-label="' . esc_attr__('Vai al calendario', 'fp-experiences') . '">' . esc_html__('📅 Calendario', 'fp-experiences') . '</a>';

        // Add "Duplicate" (if user can)
        if (current_user_can('edit_fp_experiences')) {
            $new_actions['duplicate'] = '<a href="#" data-post-id="' . esc_attr($post->ID) . '" class="fp-exp-duplicate-experience" aria-label="' . esc_attr__('Duplica esperienza', 'fp-experiences') . '">' . esc_html__('📋 Duplica', 'fp-experiences') . '</a>';
        }

        // Keep trash/delete
        if (isset($actions['trash'])) {
            $new_actions['trash'] = $actions['trash'];
        }
        if (isset($actions['delete'])) {
            $new_actions['delete'] = $actions['delete'];
        }

        return $new_actions;
    }
}
