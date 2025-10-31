<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use function absint;
use function get_post_meta;
use function is_array;

/**
 * Tool per riparare slot esistenti con capacity=0
 */
final class SlotRepairTool
{
    /**
     * Aggiorna capacity di tutti gli slot esistenti con capacity=0
     * 
     * @return array{updated: int, total: int, errors: array<string>}
     */
    public static function repair_slot_capacities(): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_exp_slots';
        
        // Get all slots with capacity=0
        $slots = $wpdb->get_results(
            "SELECT id, experience_id FROM {$table} WHERE capacity_total = 0 ORDER BY id ASC",
            ARRAY_A
        );
        
        $total = count($slots);
        $updated = 0;
        $errors = [];
        
        foreach ($slots as $slot) {
            $slot_id = absint($slot['id']);
            $exp_id = absint($slot['experience_id']);
            
            // Get correct capacity from experience meta
            $availability = get_post_meta($exp_id, '_fp_exp_availability', true);
            $capacity = 10; // default
            
            if (is_array($availability) && isset($availability['slot_capacity'])) {
                $capacity = absint((string) $availability['slot_capacity']);
            }
            
            // If still 0, use default
            if ($capacity === 0) {
                $capacity = 10;
            }
            
            // Update slot
            $result = $wpdb->update(
                $table,
                ['capacity_total' => $capacity],
                ['id' => $slot_id],
                ['%d'],
                ['%d']
            );
            
            if ($result === false) {
                $errors[] = "Slot ID $slot_id: " . $wpdb->last_error;
            } else {
                $updated++;
            }
        }
        
        return [
            'updated' => $updated,
            'total' => $total,
            'errors' => $errors,
        ];
    }
    
    /**
     * Cancella slot vecchi (nel passato e senza prenotazioni)
     * 
     * @return array{deleted: int, total: int, errors: array<string>}
     */
    public static function cleanup_old_slots(int $days_ago = 30): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_exp_slots';
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
        
        // Get old slots without reservations
        $slots = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE end_datetime < %s AND id NOT IN (
                    SELECT DISTINCT slot_id FROM {$wpdb->prefix}fp_exp_reservations WHERE slot_id IS NOT NULL
                )",
                $cutoff
            ),
            ARRAY_A
        );
        
        $total = count($slots);
        $deleted = 0;
        $errors = [];
        
        foreach ($slots as $slot) {
            $result = $wpdb->delete(
                $table,
                ['id' => absint($slot['id'])],
                ['%d']
            );
            
            if ($result === false) {
                $errors[] = "Slot ID {$slot['id']}: " . $wpdb->last_error;
            } else {
                $deleted++;
            }
        }
        
        return [
            'deleted' => $deleted,
            'total' => $total,
            'errors' => $errors,
        ];
    }
}

