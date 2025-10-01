<?php

declare(strict_types=1);

namespace FP_Exp\Migrations\Migrations;

use FP_Exp\Migrations\Migration;
use WP_Query;

use function absint;
use function array_values;
use function get_post_meta;
use function is_array;
use function update_post_meta;
use function wp_reset_postdata;

final class AddAddonImageMeta implements Migration
{
    public function key(): string
    {
        return '20241001_addon_image_meta';
    }

    public function run(): void
    {
        $paged = 1;
        $per_page = 50;

        do {
            $query = new WP_Query([
                'post_type' => 'fp_experience',
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'no_found_rows' => true,
            ]);

            $experience_ids = $query->posts;

            if (! is_array($experience_ids) || empty($experience_ids)) {
                wp_reset_postdata();
                break;
            }

            foreach ($experience_ids as $experience_id) {
                $experience_id = absint($experience_id);

                if ($experience_id <= 0) {
                    continue;
                }

                $raw_addons = get_post_meta($experience_id, '_fp_addons', true);

                if (! is_array($raw_addons) || empty($raw_addons)) {
                    continue;
                }

                $updated = false;

                foreach ($raw_addons as $index => $addon) {
                    if (! is_array($addon)) {
                        continue;
                    }

                    $image_id = isset($addon['image_id']) ? absint((string) $addon['image_id']) : 0;

                    if (! isset($addon['image_id']) || $image_id !== (int) ($addon['image_id'] ?? 0)) {
                        $raw_addons[$index]['image_id'] = $image_id;
                        $updated = true;
                    }

                    if (isset($raw_addons[$index]['image'])) {
                        unset($raw_addons[$index]['image']);
                        $updated = true;
                    }

                    if (isset($raw_addons[$index]['image_url'])) {
                        unset($raw_addons[$index]['image_url']);
                        $updated = true;
                    }
                }

                if ($updated) {
                    update_post_meta($experience_id, '_fp_addons', array_values($raw_addons));
                }
            }

            wp_reset_postdata();
            $paged++;
        } while (! empty($experience_ids) && count($experience_ids) === $per_page);
    }
}
