<?php

declare(strict_types=1);

namespace FP_Exp\Tests\Booking;

use FP_Exp\Booking\AvailabilityService;
use PHPUnit\Framework\TestCase;

/**
 * Test per verificare che l'ultimo giorno del calendario sia disponibile.
 */
final class AvailabilityServiceTest extends TestCase
{
    /**
     * Test che verifica che gli slot dell'ultimo giorno del mese siano inclusi
     * anche quando il timezone locale è dietro UTC (es. America/Los_Angeles).
     */
    public function test_last_day_of_month_is_available_with_timezone_behind_utc(): void
    {
        // Simula un experience con ricorrenza settimanale
        $experience_id = $this->create_test_experience_with_weekly_slots();
        
        // Richiedi slot per ottobre 2024
        $slots = AvailabilityService::get_virtual_slots($experience_id, '2024-10-01', '2024-10-31');
        
        // Verifica che ci siano slot per il 31 ottobre
        $last_day_slots = array_filter($slots, function ($slot) {
            return str_starts_with($slot['start'], '2024-10-31');
        });
        
        $this->assertNotEmpty(
            $last_day_slots,
            'L\'ultimo giorno del mese (31 ottobre) dovrebbe avere slot disponibili'
        );
        
        // Cleanup
        $this->cleanup_test_experience($experience_id);
    }
    
    /**
     * Test che verifica che non vengano inclusi slot del mese successivo.
     */
    public function test_no_slots_from_next_month(): void
    {
        $experience_id = $this->create_test_experience_with_weekly_slots();
        
        // Richiedi slot per ottobre 2024
        $slots = AvailabilityService::get_virtual_slots($experience_id, '2024-10-01', '2024-10-31');
        
        // Verifica che non ci siano slot per novembre
        $november_slots = array_filter($slots, function ($slot) {
            return str_starts_with($slot['start'], '2024-11');
        });
        
        $this->assertEmpty(
            $november_slots,
            'Non dovrebbero esserci slot di novembre quando si richiedono slot di ottobre'
        );
        
        // Cleanup
        $this->cleanup_test_experience($experience_id);
    }
    
    /**
     * Crea un'esperienza di test con slot settimanali.
     */
    private function create_test_experience_with_weekly_slots(): int
    {
        // Crea un post di tipo fp_experience
        $post_id = wp_insert_post([
            'post_type' => 'fp_experience',
            'post_title' => 'Test Experience for Last Day Bug',
            'post_status' => 'publish',
        ]);
        
        if (!$post_id || is_wp_error($post_id)) {
            throw new \RuntimeException('Impossibile creare l\'experience di test');
        }
        
        // Configura ricorrenza settimanale con slot alle 10:00 e 14:00
        update_post_meta($post_id, '_fp_exp_recurrence', [
            'frequency' => 'weekly',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'time_slots' => [
                ['time' => '10:00'],
                ['time' => '14:00'],
                ['time' => '18:00'],
                ['time' => '22:00'], // Slot serale per testare timezone shift
            ],
            'start_date' => '2024-10-01',
            'end_date' => '2024-12-31',
        ]);
        
        // Configura capacità
        update_post_meta($post_id, '_fp_exp_availability', [
            'slot_capacity' => 10,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
        ]);
        
        return $post_id;
    }
    
    /**
     * Rimuove l'esperienza di test.
     */
    private function cleanup_test_experience(int $post_id): void
    {
        wp_delete_post($post_id, true);
    }
}