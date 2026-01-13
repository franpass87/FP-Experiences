<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Services\Cache\CacheInterface;
use WP_Error;

/**
 * Use case: List experiences with filters and pagination.
 */
final class ListExperiencesUseCase
{
    private ExperienceRepositoryInterface $experienceRepository;
    private ?CacheInterface $cache = null;

    public function __construct(ExperienceRepositoryInterface $experienceRepository)
    {
        $this->experienceRepository = $experienceRepository;
    }

    /**
     * Set cache service (optional).
     */
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * List experiences with filters.
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $page Page number (1-based)
     * @param int $per_page Items per page
     * @return array<string, mixed>|WP_Error Experiences list or error
     */
    public function execute(array $filters = [], int $page = 1, int $per_page = 10)
    {
        // Validate pagination
        if ($page < 1) {
            $page = 1;
        }
        if ($per_page < 1 || $per_page > 100) {
            $per_page = 10;
        }

        // Generate cache key
        $cache_key = $this->generateCacheKey($filters, $page, $per_page);

        // Try cache first
        if ($this->cache !== null) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            // Get experiences from repository
            $experiences = $this->experienceRepository->findAll($filters, $page, $per_page);
            $total = $this->experienceRepository->count($filters);

            $result = [
                'items' => $experiences,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $per_page),
                ],
            ];

            // Cache result
            if ($this->cache !== null) {
                $this->cache->set($cache_key, $result, 1800); // 30 minutes
            }

            return $result;
        } catch (\Throwable $e) {
            return new WP_Error(
                'fp_exp_list_experiences_error',
                'Failed to list experiences: ' . $e->getMessage(),
                ['filters' => $filters]
            );
        }
    }

    /**
     * Generate cache key for filters.
     *
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $per_page
     * @return string Cache key
     */
    private function generateCacheKey(array $filters, int $page, int $per_page): string
    {
        $key = 'fp_exp_experiences_list_' . md5(serialize($filters)) . '_' . $page . '_' . $per_page;
        return $key;
    }
}







