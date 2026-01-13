<?php

declare(strict_types=1);

namespace FP_Exp\Domain\Booking\Repositories;

/**
 * Experience repository interface.
 */
interface ExperienceRepositoryInterface
{
    /**
     * Get an experience by ID.
     *
     * @param int $experience_id Experience ID
     * @return array<string, mixed>|null Experience data or null if not found
     */
    public function findById(int $experience_id): ?array;

    /**
     * Get experience meta value.
     *
     * @param int $experience_id Experience ID
     * @param string $meta_key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value or default
     */
    public function getMeta(int $experience_id, string $meta_key, $default = null);

    /**
     * Update experience meta value.
     *
     * @param int $experience_id Experience ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     * @return bool True on success, false on failure
     */
    public function updateMeta(int $experience_id, string $meta_key, $meta_value): bool;

    /**
     * Delete experience meta value.
     *
     * @param int $experience_id Experience ID
     * @param string $meta_key Meta key
     * @return bool True on success, false on failure
     */
    public function deleteMeta(int $experience_id, string $meta_key): bool;
}










