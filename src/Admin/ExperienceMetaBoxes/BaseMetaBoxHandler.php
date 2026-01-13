<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes;

use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use WP_Post;

use function absint;
use function get_post_meta;
use function update_post_meta;
use function delete_post_meta;

/**
 * Base class for Experience Meta Box handlers.
 * 
 * Implements Template Method pattern - subclasses implement specific behavior
 * while base class handles common flow.
 */
abstract class BaseMetaBoxHandler
{
    private ?ExperienceRepositoryInterface $experienceRepository = null;

    /**
     * Get the meta key for this handler.
     */
    abstract protected function get_meta_key(): string;

    /**
     * Render the tab content for this meta box.
     * 
     * @param array<string, mixed> $data Current meta data
     * @param int $post_id Post ID
     */
    abstract protected function render_tab_content(array $data, int $post_id): void;

    /**
     * Save meta data from form submission.
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $raw Raw form data
     */
    abstract protected function save_meta_data(int $post_id, array $raw): void;

    /**
     * Get current meta data for this handler.
     * 
     * @param int $post_id Post ID
     * @return array<string, mixed>
     */
    abstract protected function get_meta_data(int $post_id): array;

    /**
     * Template method: Render the complete tab.
     * 
     * @param array<string, mixed> $data Current meta data
     * @param int $post_id Post ID
     */
    public function render(array $data, int $post_id): void
    {
        $this->render_tab_content($data, $post_id);
    }

    /**
     * Template method: Save meta data.
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $raw Raw form data
     */
    public function save(int $post_id, array $raw): void
    {
        // Skip autosave and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $this->save_meta_data($post_id, $raw);
    }

    /**
     * Template method: Get meta data.
     * 
     * @param int $post_id Post ID
     * @return array<string, mixed>
     */
    public function get(int $post_id): array
    {
        return $this->get_meta_data($post_id);
    }

    /**
     * Helper: Update or delete meta.
     * 
     * @param int $post_id Post ID
     * @param string $key Meta key (will be prefixed with handler's meta key)
     * @param mixed $value Meta value (null to delete)
     */
    protected function update_or_delete_meta(int $post_id, string $key, mixed $value): void
    {
        $full_key = $this->get_meta_key() . '_' . $key;
        
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        if ($repo !== null) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                $repo->deleteMeta($post_id, $full_key);
            } else {
                $repo->updateMeta($post_id, $full_key, $value);
            }
        } else {
            // Fallback to direct WordPress functions for backward compatibility
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                delete_post_meta($post_id, $full_key);
            } else {
                update_post_meta($post_id, $full_key, $value);
            }
        }
    }

    /**
     * Helper: Get meta value.
     * 
     * @param int $post_id Post ID
     * @param string $key Meta key (will be prefixed with handler's meta key)
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_meta_value(int $post_id, string $key, mixed $default = null): mixed
    {
        $full_key = $this->get_meta_key() . '_' . $key;
        
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $value = null;
        if ($repo !== null) {
            $value = $repo->getMeta($post_id, $full_key, $default);
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $value = get_post_meta($post_id, $full_key, true);
        }
        
        return $value !== '' ? $value : $default;
    }

    /**
     * Helper: Sanitize text field.
     */
    protected function sanitize_text(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }

    /**
     * Helper: Sanitize textarea field.
     */
    protected function sanitize_textarea(mixed $value): string
    {
        return sanitize_textarea_field((string) $value);
    }

    /**
     * Helper: Sanitize integer.
     */
    protected function sanitize_int(mixed $value): int
    {
        return absint($value);
    }

    /**
     * Helper: Sanitize array.
     * 
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function sanitize_array(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(
            fn($item) => is_array($item) ? $this->sanitize_array($item) : sanitize_text_field((string) $item),
            $value
        );
    }

    /**
     * Get ExperienceRepository from container if available.
     */
    protected function getExperienceRepository(): ?ExperienceRepositoryInterface
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
}










