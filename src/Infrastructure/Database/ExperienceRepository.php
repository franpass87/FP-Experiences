<?php

declare(strict_types=1);

namespace FP_Exp\Infrastructure\Database;

use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;

use function get_post_meta;
use function update_post_meta;
use function delete_post_meta;

/**
 * Experience repository implementation.
 * Uses WordPress post meta API.
 */
final class ExperienceRepository implements ExperienceRepositoryInterface
{
    public function findById(int $experience_id): ?array
    {
        $post = get_post($experience_id);
        
        if ($post === null || $post->post_type !== 'fp_experience') {
            return null;
        }

        return [
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post->post_status,
            'post_type' => $post->post_type,
        ];
    }

    public function getMeta(int $experience_id, string $meta_key, $default = null)
    {
        return get_post_meta($experience_id, $meta_key, true) ?: $default;
    }

    public function updateMeta(int $experience_id, string $meta_key, $meta_value): bool
    {
        return update_post_meta($experience_id, $meta_key, $meta_value) !== false;
    }

    public function deleteMeta(int $experience_id, string $meta_key): bool
    {
        return delete_post_meta($experience_id, $meta_key);
    }
}










