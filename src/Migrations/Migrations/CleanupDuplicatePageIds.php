<?php

declare(strict_types=1);

namespace FP_Exp\Migrations\Migrations;

use FP_Exp\Migrations\Migration;
use FP_Exp\Utils\Helpers;

use function absint;
use function count;
use function delete_post_meta;
use function get_post_meta;
use function get_posts;
use function is_array;

/**
 * Migration to clean up duplicate _fp_exp_page_id meta values.
 * 
 * This prevents issues where multiple experiences share the same page_id,
 * causing them to link to the same URL in listings.
 */
final class CleanupDuplicatePageIds implements Migration
{
    public function key(): string
    {
        return '20251031_cleanup_duplicate_page_ids';
    }

    /**
     * Check if there are any duplicate page_ids without cleaning them.
     * 
     * @return array{has_duplicates: bool, duplicates: array<string, array<int, int>>}
     */
    public static function check_duplicates(): array
    {
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (! is_array($experiences)) {
            return [
                'has_duplicates' => false,
                'duplicates' => [],
            ];
        }

        $page_id_map = [];

        foreach ($experiences as $experience_id) {
            $experience_id = absint($experience_id);
            
            if ($experience_id <= 0) {
                continue;
            }
            
            $page_id = absint((string) get_post_meta($experience_id, '_fp_exp_page_id', true));
            
            if ($page_id > 0) {
                if (! isset($page_id_map[$page_id])) {
                    $page_id_map[$page_id] = [];
                }
                
                $page_id_map[$page_id][] = $experience_id;
            }
        }

        $duplicates = [];
        foreach ($page_id_map as $page_id => $experience_ids) {
            if (count($experience_ids) > 1) {
                $duplicates[(string) $page_id] = $experience_ids;
            }
        }

        return [
            'has_duplicates' => ! empty($duplicates),
            'duplicates' => $duplicates,
        ];
    }

    /**
     * Run the migration (called by Runner).
     */
    public function run(): void
    {
        $result = self::execute_cleanup();
        
        if ($result['cleaned'] > 0) {
            Helpers::log_debug('migrations', 'Cleaned up duplicate page_ids', [
                'total_experiences' => $result['total'],
                'cleaned_count' => $result['cleaned'],
                'duplicate_page_ids' => count($result['duplicates_found']),
                'duplicates' => $result['duplicates_found'],
            ]);
        }
    }

    /**
     * Execute the cleanup and return detailed results.
     * Can be called both by the migration runner and admin tools.
     * 
     * @return array{success: bool, cleaned: int, total: int, duplicates_found: array<string, int>}
     */
    public static function execute_cleanup(): array
    {
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (! is_array($experiences) || empty($experiences)) {
            return [
                'success' => true,
                'cleaned' => 0,
                'total' => 0,
                'duplicates_found' => [],
            ];
        }

        $page_id_map = [];
        $cleaned = 0;

        // First pass: build a map of page_id => [experience_ids]
        foreach ($experiences as $experience_id) {
            $experience_id = absint($experience_id);
            
            if ($experience_id <= 0) {
                continue;
            }
            
            $page_id = absint((string) get_post_meta($experience_id, '_fp_exp_page_id', true));
            
            if ($page_id > 0) {
                if (! isset($page_id_map[$page_id])) {
                    $page_id_map[$page_id] = [];
                }
                
                $page_id_map[$page_id][] = $experience_id;
            }
        }

        // Second pass: remove page_id from experiences that share the same page_id
        $duplicates_found = [];
        foreach ($page_id_map as $page_id => $experience_ids) {
            if (count($experience_ids) > 1) {
                // Multiple experiences share this page_id - remove from all
                $duplicates_found[(string) $page_id] = count($experience_ids);
                
                foreach ($experience_ids as $experience_id) {
                    delete_post_meta($experience_id, '_fp_exp_page_id');
                    $cleaned++;
                }
            }
        }

        return [
            'success' => true,
            'cleaned' => $cleaned,
            'total' => count($experiences),
            'duplicates_found' => $duplicates_found,
        ];
    }
}

