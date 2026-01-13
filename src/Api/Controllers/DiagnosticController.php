<?php

declare(strict_types=1);

namespace FP_Exp\Api\Controllers;

use FP_Exp\Api\Middleware\ErrorHandlingMiddleware;
use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Slots;
use WP_REST_Request;
use WP_REST_Response;

use function array_keys;
use function get_post_meta;
use function get_posts;
use function get_the_title;
use function is_wp_error;
use function rest_ensure_response;

/**
 * Controller for diagnostic REST API endpoints.
 */
final class DiagnosticController
{
    /**
     * Diagnostic checkout.
     */
    public function checkout(WP_REST_Request $request): WP_REST_Response
    {
        $result = [];

        // 1. Cart
        $cart = Cart::instance();
        $result['cart'] = [
            'has_items' => $cart->has_items(),
            'items' => $cart->get_items(),
        ];

        // 2. Availability Meta
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $result['experiences'] = [];

        foreach ($experiences as $exp_id) {
            $meta = get_post_meta($exp_id, '_fp_exp_availability', true);
            $result['experiences'][] = [
                'id' => $exp_id,
                'title' => get_the_title($exp_id),
                'meta' => $meta,
            ];
        }

        // 3. Simulate checkout if there's an item
        if (! empty($result['cart']['items'])) {
            $first_item = $result['cart']['items'][0];
            $exp_id = $first_item['experience_id'] ?? 0;
            $start = $first_item['slot_start'] ?? ($first_item['occurrence_start'] ?? '');
            $end = $first_item['slot_end'] ?? ($first_item['occurrence_end'] ?? '');

            if ($exp_id && $start && $end) {
                // Check if slot already exists BEFORE trying to create
                global $wpdb;
                $table = $wpdb->prefix . 'fp_exp_slots';
                $existing_slot = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE experience_id = %d AND start_datetime = %s AND end_datetime = %s",
                        $exp_id,
                        $start,
                        $end
                    ),
                    ARRAY_A
                );

                $result['pre_check'] = [
                    'slot_exists' => ! empty($existing_slot),
                    'existing_slot_id' => $existing_slot['id'] ?? null,
                    'existing_slot_capacity' => $existing_slot['capacity_total'] ?? null,
                ];

                try {
                    // Clear previous errors
                    global $wpdb;
                    $wpdb->last_error = '';

                    $slot = Slots::ensure_slot_for_occurrence($exp_id, $start, $end);

                    if (is_wp_error($slot)) {
                        $result['slot_test'] = [
                            'success' => false,
                            'error' => $slot->get_error_message(),
                            'error_data' => $slot->get_error_data(),
                            'wpdb_last_error' => $wpdb->last_error,
                            'wpdb_last_query' => $wpdb->last_query,
                        ];
                    } else {
                        $result['slot_test'] = [
                            'success' => true,
                            'slot_id' => $slot,
                            'slot' => Slots::get_slot($slot),
                        ];
                    }
                } catch (\Throwable $exception) {
                    $result['slot_test'] = [
                        'success' => false,
                        'exception' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                        'wpdb_last_error' => $wpdb->last_error ?? '',
                    ];
                }
            } else {
                $result['slot_test'] = [
                    'success' => false,
                    'error' => 'Dati mancanti nel carrello',
                    'data' => ['exp_id' => $exp_id, 'start' => $start, 'end' => $end],
                    'item_keys' => array_keys($first_item),
                ];
            }
        }

        return ErrorHandlingMiddleware::success($result);
    }
}















