<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Services\Cache\CacheInterface;
use WP_Error;

/**
 * Use case: Get experience details.
 */
final class GetExperienceUseCase
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
     * Get experience by ID.
     *
     * @param int $experience_id Experience ID
     * @return array<string, mixed>|WP_Error Experience data or error
     */
    public function execute(int $experience_id)
    {
        if ($experience_id <= 0) {
            return new WP_Error(
                'fp_exp_invalid_experience_id',
                'Invalid experience ID'
            );
        }

        // Try cache first
        if ($this->cache !== null) {
            $cache_key = 'fp_exp_experience_' . $experience_id;
            $cached = $this->cache->get($cache_key);
            
            if ($cached !== null) {
                return $cached;
            }
        }

        // Get from repository
        $experience = $this->experienceRepository->findById($experience_id);

        if ($experience === null) {
            return new WP_Error(
                'fp_exp_experience_not_found',
                'Experience not found',
                ['experience_id' => $experience_id]
            );
        }

        // Enhance with meta data
        $experience = $this->enrichExperienceData($experience, $experience_id);

        // Cache result
        if ($this->cache !== null) {
            $cache_key = 'fp_exp_experience_' . $experience_id;
            $this->cache->set($cache_key, $experience, 3600); // 1 hour
        }

        return $experience;
    }

    /**
     * Enrich experience data with meta information.
     *
     * @param array<string, mixed> $experience Base experience data
     * @param int $experience_id Experience ID
     * @return array<string, mixed> Enriched experience data
     */
    private function enrichExperienceData(array $experience, int $experience_id): array
    {
        // Get pricing
        $pricing = $this->experienceRepository->getMeta($experience_id, '_fp_exp_pricing', []);

        // Get availability settings
        $availability = $this->experienceRepository->getMeta($experience_id, '_fp_exp_availability', []);

        // Get other relevant meta
        $experience['pricing'] = $pricing;
        $experience['availability'] = $availability;
        $experience['meta'] = [
            'duration' => $this->experienceRepository->getMeta($experience_id, '_fp_exp_duration', ''),
            'max_participants' => $this->experienceRepository->getMeta($experience_id, '_fp_exp_max_participants', 0),
            'min_participants' => $this->experienceRepository->getMeta($experience_id, '_fp_exp_min_participants', 1),
        ];

        return $experience;
    }
}







