<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;
use FP_Exp\Booking\Recurrence;
use FP_Exp\Booking\Slots;

use function absint;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function rest_url;

/**
 * Handler for Calendar/Availability tab in Experience Meta Box.
 * 
 * Handles recurrence patterns, time slots, buffers, capacity, and slot generation.
 * 
 * NOTE: This is a very complex handler due to recurrence patterns, time slot management,
 * capacity rules, and automatic slot generation. The handler manages the core functionality
 * with support for legacy formats.
 */
final class CalendarMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-calendar-panel';
        $frequency = $data['frequency'] ?? 'daily';
        $slot_capacity = $data['slot_capacity'] ?? 0;
        $lead_time_hours = $data['lead_time_hours'] ?? 0;
        $buffer_before_minutes = $data['buffer_before_minutes'] ?? 0;
        $buffer_after_minutes = $data['buffer_after_minutes'] ?? 0;
        $recurrence = $data['recurrence'] ?? Recurrence::defaults();
        $time_slots = $data['time_slots'] ?? [];
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-calendar"
            data-tab-panel="calendar"
            hidden
        >
            <div 
                data-fp-exp-calendar-admin
                data-api-endpoints="<?php echo esc_attr(wp_json_encode([
                    'preview' => rest_url('fp-exp/v1/calendar/recurrence/preview'),
                    'generate' => rest_url('fp-exp/v1/calendar/recurrence/generate'),
                ])); ?>"
                data-experience-id="<?php echo esc_attr((string) $post_id); ?>"
            >
                <fieldset class="fp-exp-fieldset">
                    <legend><?php esc_html_e('Disponibilità Generale', 'fp-experiences'); ?></legend>
                    
                    <div class="fp-exp-field fp-exp-field--columns">
                        <div>
                            <label class="fp-exp-field__label" for="fp-exp-slot-capacity">
                                <?php esc_html_e('Capacità per slot', 'fp-experiences'); ?>
                            </label>
                            <input
                                type="number"
                                id="fp-exp-slot-capacity"
                                name="fp_exp_availability[slot_capacity]"
                                value="<?php echo esc_attr((string) $slot_capacity); ?>"
                                min="0"
                                step="1"
                                class="small-text"
                            />
                            <p class="fp-exp-field__description">
                                <?php esc_html_e('Numero massimo di partecipanti per ogni slot.', 'fp-experiences'); ?>
                            </p>
                        </div>

                        <div>
                            <label class="fp-exp-field__label" for="fp-exp-lead-time">
                                <?php esc_html_e('Tempo di prenotazione anticipata (ore)', 'fp-experiences'); ?>
                            </label>
                            <input
                                type="number"
                                id="fp-exp-lead-time"
                                name="fp_exp_availability[lead_time_hours]"
                                value="<?php echo esc_attr((string) $lead_time_hours); ?>"
                                min="0"
                                step="1"
                                class="small-text"
                            />
                            <p class="fp-exp-field__description">
                                <?php esc_html_e('Tempo minimo prima della partenza per permettere una prenotazione.', 'fp-experiences'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="fp-exp-field fp-exp-field--columns">
                        <div>
                            <label class="fp-exp-field__label" for="fp-exp-buffer-before">
                                <?php esc_html_e('Buffer prima (minuti)', 'fp-experiences'); ?>
                            </label>
                            <input
                                type="number"
                                id="fp-exp-buffer-before"
                                name="fp_exp_availability[buffer_before_minutes]"
                                value="<?php echo esc_attr((string) $buffer_before_minutes); ?>"
                                min="0"
                                step="1"
                                class="small-text"
                            />
                            <p class="fp-exp-field__description">
                                <?php esc_html_e('Tempo di preparazione prima di ogni slot.', 'fp-experiences'); ?>
                            </p>
                        </div>

                        <div>
                            <label class="fp-exp-field__label" for="fp-exp-buffer-after">
                                <?php esc_html_e('Buffer dopo (minuti)', 'fp-experiences'); ?>
                            </label>
                            <input
                                type="number"
                                id="fp-exp-buffer-after"
                                name="fp_exp_availability[buffer_after_minutes]"
                                value="<?php echo esc_attr((string) $buffer_after_minutes); ?>"
                                min="0"
                                step="1"
                                class="small-text"
                            />
                            <p class="fp-exp-field__description">
                                <?php esc_html_e('Tempo di pulizia dopo ogni slot.', 'fp-experiences'); ?>
                            </p>
                        </div>
                    </div>
                </fieldset>

                <?php $this->render_recurrence_section($recurrence, $time_slots); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Render recurrence section with time slots.
     */
    private function render_recurrence_section(array $recurrence, array $time_slots): void
    {
        $duration = $recurrence['duration'] ?? 60;
        $days = $recurrence['days'] ?? [];
        $frequency = $recurrence['frequency'] ?? 'weekly';
        ?>
        <fieldset class="fp-exp-fieldset">
            <legend><?php esc_html_e('Ricorrenza e Slot Orari', 'fp-experiences'); ?></legend>
            
            <div class="fp-exp-field">
                <label class="fp-exp-field__label" for="fp-exp-recurrence-duration">
                    <?php esc_html_e('Durata predefinita slot (minuti)', 'fp-experiences'); ?>
                </label>
                <input
                    type="number"
                    id="fp-exp-recurrence-duration"
                    name="fp_exp_availability[recurrence][duration]"
                    value="<?php echo esc_attr((string) $duration); ?>"
                    min="15"
                    step="5"
                    class="small-text"
                />
                <p class="fp-exp-field__description">
                    <?php esc_html_e('Usata come base per tutti gli slot, salvo override specifici.', 'fp-experiences'); ?>
                </p>
            </div>

            <input type="hidden" name="fp_exp_availability[recurrence][frequency]" value="weekly" />

            <div class="fp-exp-field">
                <label class="fp-exp-field__label">
                    <?php esc_html_e('Giorni della settimana', 'fp-experiences'); ?>
                </label>
                <div class="fp-exp-checkbox-grid">
                    <?php foreach ($this->get_week_days() as $day_key => $day_label) : ?>
                        <label>
                            <input
                                type="checkbox"
                                name="fp_exp_availability[recurrence][days][]"
                                value="<?php echo esc_attr($day_key); ?>"
                                <?php checked(in_array($day_key, $days, true)); ?>
                            />
                            <span><?php echo esc_html($day_label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="fp-exp-field">
                <p class="fp-exp-field__description">
                    <?php esc_html_e('Definisci gli orari in cui l\'esperienza è disponibile.', 'fp-experiences'); ?>
                </p>
                <div class="fp-exp-repeater" data-repeater="time_slots" data-repeater-next-index="<?php echo esc_attr((string) count($time_slots)); ?>">
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($time_slots as $index => $slot) : ?>
                            <?php $this->render_time_slot_row((string) $index, $slot); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_time_slot_row('__INDEX__', [
                            'time' => '',
                            'capacity' => 0,
                            'buffer_before' => 0,
                            'buffer_after' => 0,
                            'days' => [],
                        ], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add>
                            <?php esc_html_e('Aggiungi slot orario', 'fp-experiences'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Get week days for recurrence.
     * 
     * @return array<string, string>
     */
    private function get_week_days(): array
    {
        return [
            'monday' => esc_html__('Lunedì', 'fp-experiences'),
            'tuesday' => esc_html__('Martedì', 'fp-experiences'),
            'wednesday' => esc_html__('Mercoledì', 'fp-experiences'),
            'thursday' => esc_html__('Giovedì', 'fp-experiences'),
            'friday' => esc_html__('Venerdì', 'fp-experiences'),
            'saturday' => esc_html__('Sabato', 'fp-experiences'),
            'sunday' => esc_html__('Domenica', 'fp-experiences'),
        ];
    }

    /**
     * Render a single time slot row.
     */
    private function render_time_slot_row(string $index, array $slot, bool $is_template = false): void
    {
        $field_name = 'fp_exp_availability[recurrence][time_slots]';
        $time = $slot['time'] ?? '';
        $capacity = $slot['capacity'] ?? 0;
        $buffer_before = $slot['buffer_before'] ?? 0;
        $buffer_after = $slot['buffer_after'] ?? 0;
        $days = $slot['days'] ?? [];
        ?>
        <div class="fp-exp-repeater__item" data-repeater-item>
            <div class="fp-exp-repeater__item-header">
                <h4 class="fp-exp-repeater__item-title">
                    <?php esc_html_e('Slot Orario', 'fp-experiences'); ?> <span data-repeater-index><?php echo esc_html($index); ?></span>
                </h4>
                <button type="button" class="button-link fp-exp-repeater__item-remove" data-repeater-remove>
                    <?php esc_html_e('Rimuovi', 'fp-experiences'); ?>
                </button>
            </div>
            <div class="fp-exp-repeater__item-content">
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-time-slot-<?php echo esc_attr($index); ?>">
                        <?php esc_html_e('Orario', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="time"
                        id="fp-exp-time-slot-<?php echo esc_attr($index); ?>"
                        name="<?php echo esc_attr($field_name . '[' . $index . '][time]'); ?>"
                        value="<?php echo esc_attr($time); ?>"
                        class="regular-text"
                        <?php echo $is_template ? 'data-repeater-field="time"' : ''; ?>
                    />
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-slot-capacity-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Capacità', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-slot-capacity-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][capacity]'); ?>"
                            value="<?php echo esc_attr((string) $capacity); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="capacity"' : ''; ?>
                        />
                    </div>

                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-slot-buffer-before-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Buffer prima', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-slot-buffer-before-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][buffer_before]'); ?>"
                            value="<?php echo esc_attr((string) $buffer_before); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="buffer_before"' : ''; ?>
                        />
                    </div>

                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-slot-buffer-after-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Buffer dopo', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-slot-buffer-after-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][buffer_after]'); ?>"
                            value="<?php echo esc_attr((string) $buffer_after); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="buffer_after"' : ''; ?>
                        />
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        // Basic availability settings
        $slot_capacity = isset($raw['slot_capacity']) ? absint((string) $raw['slot_capacity']) : 0;
        $lead_time_hours = isset($raw['lead_time_hours']) ? absint((string) $raw['lead_time_hours']) : 0;
        $buffer_before_minutes = isset($raw['buffer_before_minutes']) ? absint((string) $raw['buffer_before_minutes']) : 0;
        $buffer_after_minutes = isset($raw['buffer_after_minutes']) ? absint((string) $raw['buffer_after_minutes']) : 0;

        $this->update_or_delete_meta($post_id, 'slot_capacity', $slot_capacity > 0 ? $slot_capacity : null);
        $this->update_or_delete_meta($post_id, 'lead_time_hours', $lead_time_hours > 0 ? $lead_time_hours : null);
        $this->update_or_delete_meta($post_id, 'buffer_before_minutes', $buffer_before_minutes > 0 ? $buffer_before_minutes : null);
        $this->update_or_delete_meta($post_id, 'buffer_after_minutes', $buffer_after_minutes > 0 ? $buffer_after_minutes : null);

        // Recurrence data
        $recurrence_raw = $raw['recurrence'] ?? [];
        if (!is_array($recurrence_raw)) {
            $recurrence_raw = [];
        }

        $duration = isset($recurrence_raw['duration']) ? absint((string) $recurrence_raw['duration']) : 60;
        $frequency = isset($recurrence_raw['frequency']) ? sanitize_key((string) $recurrence_raw['frequency']) : 'weekly';
        $days_raw = $recurrence_raw['days'] ?? [];
        $days = is_array($days_raw) ? array_map('sanitize_key', $days_raw) : [];
        $days = array_values(array_unique(array_filter($days)));

        $time_slots_raw = $recurrence_raw['time_slots'] ?? [];
        $time_slots = [];
        if (is_array($time_slots_raw)) {
            foreach ($time_slots_raw as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $time = sanitize_text_field($slot['time'] ?? '');
                if ($time === '') {
                    continue;
                }

                $capacity = isset($slot['capacity']) ? absint((string) $slot['capacity']) : 0;
                $buffer_before = isset($slot['buffer_before']) ? absint((string) $slot['buffer_before']) : 0;
                $buffer_after = isset($slot['buffer_after']) ? absint((string) $slot['buffer_after']) : 0;
                $slot_days = isset($slot['days']) && is_array($slot['days']) 
                    ? array_map('sanitize_key', $slot['days']) 
                    : [];

                $time_slots[] = [
                    'time' => $time,
                    'capacity' => $capacity,
                    'buffer_before' => $buffer_before,
                    'buffer_after' => $buffer_after,
                    'days' => array_values(array_unique(array_filter($slot_days))),
                ];
            }
        }

        // Build recurrence meta (matching existing code structure)
        $recurrence_meta = [
            'frequency' => $frequency,
            'duration' => $duration,
            'days' => $days,
            'time_slots' => $time_slots,
        ];

        // Sanitize recurrence using Recurrence::sanitize()
        $recurrence_meta = Recurrence::sanitize($recurrence_meta);

        // Save basic availability settings
        $availability = [
            'frequency' => $frequency,
            'slot_capacity' => $slot_capacity,
            'lead_time_hours' => $lead_time_hours,
            'buffer_before_minutes' => $buffer_before_minutes,
            'buffer_after_minutes' => $buffer_after_minutes,
        ];
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        if ($repo !== null) {
            $repo->updateMeta($post_id, '_fp_exp_availability', $availability);
        } else {
            // Fallback to direct update_post_meta for backward compatibility
            update_post_meta($post_id, '_fp_exp_availability', $availability);
        }

        // Save individual meta fields for backward compatibility
        $this->update_or_delete_meta($post_id, 'lead_time_hours', $lead_time_hours > 0 ? $lead_time_hours : null);
        $this->update_or_delete_meta($post_id, 'buffer_before_minutes', $buffer_before_minutes > 0 ? $buffer_before_minutes : null);
        $this->update_or_delete_meta($post_id, 'buffer_after_minutes', $buffer_after_minutes > 0 ? $buffer_after_minutes : null);
        $this->update_or_delete_meta($post_id, 'slot_capacity', $slot_capacity > 0 ? $slot_capacity : null);

        // Save recurrence if has actionable data
        $has_data = !empty($recurrence_meta['days']) && !empty($recurrence_meta['time_slots']);
        if ($has_data) {
            // Try to use repository if available
            $repo = $this->getExperienceRepository();
            if ($repo !== null) {
                $repo->updateMeta($post_id, '_fp_exp_recurrence', $recurrence_meta);
            } else {
                // Fallback to direct update_post_meta for backward compatibility
                update_post_meta($post_id, '_fp_exp_recurrence', $recurrence_meta);
            }
            
            // Generate slots if recurrence is actionable
            $this->maybe_generate_recurrence_slots($post_id, [
                'recurrence' => $recurrence_meta,
                'availability' => [
                    'slot_capacity' => $slot_capacity,
                    'buffer_before_minutes' => $buffer_before_minutes,
                    'buffer_after_minutes' => $buffer_after_minutes,
                ],
            ]);
        } else {
            delete_post_meta($post_id, '_fp_exp_recurrence');
        }
    }

    /**
     * Generate recurrence slots if recurrence is actionable.
     */
    private function maybe_generate_recurrence_slots(int $post_id, array $data): void
    {
        $recurrence = $data['recurrence'] ?? [];
        if (empty($recurrence) || !Recurrence::is_actionable($recurrence)) {
            return;
        }

        $availability = $data['availability'] ?? [];
        $slot_capacity = $availability['slot_capacity'] ?? 0;
        $buffer_before = $availability['buffer_before_minutes'] ?? 0;
        $buffer_after = $availability['buffer_after_minutes'] ?? 0;

        $rules = Recurrence::build_rules($recurrence, [
            'slot_capacity' => $slot_capacity,
            'buffer_before_minutes' => $buffer_before,
            'buffer_after_minutes' => $buffer_after,
        ]);

        if (empty($rules)) {
            return;
        }

        $options = [
            'default_duration' => $recurrence['duration'] ?? 60,
            'default_capacity' => $slot_capacity,
            'buffer_before' => $buffer_before,
            'buffer_after' => $buffer_after,
            'replace_existing' => true,
        ];

        Slots::generate_recurring_slots($post_id, $rules, [], $options);
    }

    protected function get_meta_data(int $post_id): array
    {
        $defaults = [
            'frequency' => 'daily',
            'times' => [],
            'days_of_week' => [],
            'custom_slots' => [],
            'slot_capacity' => 0,
            'lead_time_hours' => 0,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'recurrence' => Recurrence::defaults(),
        ];

        // Get basic availability - Try to use repository if available
        $repo = $this->getExperienceRepository();
        $slot_capacity = 0;
        $lead_time_hours = 0;
        $buffer_before_minutes = 0;
        $buffer_after_minutes = 0;
        $recurrence_meta = [];
        
        if ($repo !== null) {
            $slot_capacity = absint((string) $repo->getMeta($post_id, '_fp_slot_capacity', 0));
            $lead_time_hours = absint((string) $repo->getMeta($post_id, '_fp_lead_time_hours', 0));
            $buffer_before_minutes = absint((string) $repo->getMeta($post_id, '_fp_buffer_before_minutes', 0));
            $buffer_after_minutes = absint((string) $repo->getMeta($post_id, '_fp_buffer_after_minutes', 0));
            $recurrence_meta = $repo->getMeta($post_id, '_fp_exp_recurrence', []);
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $slot_capacity = absint((string) get_post_meta($post_id, '_fp_slot_capacity', true));
            $lead_time_hours = absint((string) get_post_meta($post_id, '_fp_lead_time_hours', true));
            $buffer_before_minutes = absint((string) get_post_meta($post_id, '_fp_buffer_before_minutes', true));
            $buffer_after_minutes = absint((string) get_post_meta($post_id, '_fp_buffer_after_minutes', true));
            $recurrence_meta = get_post_meta($post_id, '_fp_exp_recurrence', true);
        }
        if (!is_array($recurrence_meta)) {
            $recurrence_meta = Recurrence::defaults();
        } else {
            $recurrence_meta = array_merge(Recurrence::defaults(), $recurrence_meta);
        }

        // Extract time slots from recurrence
        $time_slots = $recurrence_meta['time_slots'] ?? [];
        
        // Support legacy format: convert time_sets to time_slots
        if (empty($time_slots) && isset($recurrence_meta['time_sets']) && is_array($recurrence_meta['time_sets'])) {
            $time_slots = [];
            foreach ($recurrence_meta['time_sets'] as $set) {
                if (!isset($set['times']) || !is_array($set['times'])) {
                    continue;
                }
                
                foreach ($set['times'] as $time) {
                    $time_slots[] = [
                        'time' => $time,
                        'capacity' => $set['capacity'] ?? 0,
                        'buffer_before' => $set['buffer_before'] ?? 0,
                        'buffer_after' => $set['buffer_after'] ?? 0,
                        'days' => $set['days'] ?? [],
                    ];
                }
            }
            $recurrence_meta['time_slots'] = $time_slots;
        }

        // Return in format expected by render
        return [
            'frequency' => $recurrence_meta['frequency'] ?? 'daily',
            'slot_capacity' => $slot_capacity,
            'lead_time_hours' => $lead_time_hours,
            'buffer_before_minutes' => $buffer_before_minutes,
            'buffer_after_minutes' => $buffer_after_minutes,
            'recurrence' => $recurrence_meta,
            'time_slots' => $time_slots,
        ];
    }
}

