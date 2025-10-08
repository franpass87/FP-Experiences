<?php

declare(strict_types=1);

namespace FP_Exp\Tests\Booking;

use FP_Exp\Booking\AvailabilityService;
use PHPUnit\Framework\TestCase;

/**
 * Test per verificare che l'ultimo giorno del calendario sia disponibile.
 * Fix per bug timezone: setTime deve essere applicato PRIMA della conversione timezone.
 */
final class AvailabilityServiceTest extends TestCase
{
    /**
     * Test che verifica che gli slot dell'ultimo giorno del mese siano inclusi
     * quando c'è una recurrence_end_date impostata e il timezone è avanti rispetto a UTC.
     * 
     * Questo era il bug principale: setTime applicato DOPO setTimezone causava
     * lo shift della data al giorno precedente per timezone avanti UTC (Europa, Asia).
     */
    public function test_last_day_of_month_with_recurrence_end_date_europe_timezone(): void
    {
        // Simula timezone Europe/Rome (UTC+1 o UTC+2)
        update_option('timezone_string', 'Europe/Rome');
        
        // Crea esperienza con ricorrenza che finisce il 31 ottobre
        $experience_id = $this->create_test_experience([
            'recurrence_end_date' => '2024-10-31',
            'frequency' => 'weekly',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        
        // Richiedi slot per ottobre 2024
        $slots = AvailabilityService::get_virtual_slots($experience_id, '2024-10-01', '2024-10-31');
        
        // Verifica che ci siano slot per il 31 ottobre
        $last_day_slots = array_filter($slots, function ($slot) {
            $date = substr($slot['start'], 0, 10);
            return $date === '2024-10-31';
        });
        
        $this->assertNotEmpty(
            $last_day_slots,
            'L\'ultimo giorno del mese (31 ottobre) dovrebbe avere slot disponibili quando recurrence_end_date è 2024-10-31'
        );
        
        // Verifica che ci siano almeno 3 slot (10:00, 14:00, 18:00)
        $this->assertGreaterThanOrEqual(
            3,
            count($last_day_slots),
            'Dovrebbero esserci almeno 3 slot per l\'ultimo giorno'
        );
        
        // Cleanup
        $this->cleanup_test_experience($experience_id);
    }
    
    /**
     * Test che verifica che il primo giorno del mese sia incluso
     * quando c'è una recurrence_start_date impostata e il timezone è avanti rispetto a UTC.
     */
    public function test_first_day_of_month_with_recurrence_start_date_europe_timezone(): void
    {
        update_option('timezone_string', 'Europe/Rome');
        
        $experience_id = $this->create_test_experience([
            'recurrence_start_date' => '2024-10-01',
            'recurrence_end_date' => '2024-10-31',
            'frequency' => 'weekly',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        
        $slots = AvailabilityService::get_virtual_slots($experience_id, '2024-10-01', '2024-10-31');
        
        $first_day_slots = array_filter($slots, function ($slot) {
            $date = substr($slot['start'], 0, 10);
            return $date === '2024-10-01';
        });
        
        $this->assertNotEmpty(
            $first_day_slots,
            'Il primo giorno del mese (1 ottobre) dovrebbe avere slot disponibili quando recurrence_start_date è 2024-10-01'
        );
        
        $this->cleanup_test_experience($experience_id);
    }
    
    /**
     * Test che verifica che non vengano inclusi slot oltre la data di fine ricorrenza.
     */
    public function test_no_slots_after_recurrence_end_date(): void
    {
        update_option('timezone_string', 'Europe/Rome');
        
        $experience_id = $this->create_test_experience([
            'recurrence_end_date' => '2024-10-31',
            'frequency' => 'weekly',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        
        // Richiedi slot per ottobre E novembre
        $slots = AvailabilityService::get_virtual_slots($experience_id, '2024-10-01', '2024-11-30');
        
        // Verifica che NON ci siano slot per novembre
        $november_slots = array_filter($slots, function ($slot) {
            $date = substr($slot['start'], 0, 7);
            return $date === '2024-11';
        });
        
        $this->assertEmpty(
            $november_slots,
            'Non dovrebbero esserci slot per novembre quando recurrence_end_date è 2024-10-31'
        );
        
        $this->cleanup_test_experience($experience_id);
    }
    
    /**
     * Test che verifica il funzionamento con timezone Asia (UTC+9).
     */
    public function test_last_day_with_asia_timezone(): void
    {
        update_option('timezone_string', 'Asia/Tokyo');
        
        $experience_id = $this->create_test_experience([
            'recurrence_end_date' => '2024-10-31',
            'frequency' => 'daily',
            'days' => [],
        ]);
        
        $slots = AvailabilityService::get_virtual_slots($experience_id, '2024-10-01', '2024-10-31');
        
        $last_day_slots = array_filter($slots, function ($slot) {
            $date = substr($slot['start'], 0, 10);
            return $date === '2024-10-31';
        });
        
        $this->assertNotEmpty(
            $last_day_slots,
            'L\'ultimo giorno dovrebbe essere incluso anche con timezone Asia/Tokyo (UTC+9)'
        );
        
        $this->cleanup_test_experience($experience_id);
    }
    
    /**
     * Crea un'esperienza di test con ricorrenza configurabile.
     */
    private function create_test_experience(array $recurrence_config): int
    {
        $post_id = wp_insert_post([
            'post_type' => 'fp_experience',
            'post_title' => 'Test Experience for Timezone Bug',
            'post_status' => 'publish',
        ]);
        
        if (!$post_id || is_wp_error($post_id)) {
            throw new \RuntimeException('Impossibile creare l\'experience di test');
        }
        
        // Configura ricorrenza
        $recurrence = array_merge([
            'frequency' => 'weekly',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'time_slots' => [
                ['time' => '10:00'],
                ['time' => '14:00'],
                ['time' => '18:00'],
            ],
        ], $recurrence_config);
        
        update_post_meta($post_id, '_fp_exp_recurrence', $recurrence);
        
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