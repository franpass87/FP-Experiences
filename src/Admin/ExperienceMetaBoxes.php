<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use function absint;
use function add_action;
use function add_meta_box;
use function checked;
use function current_user_can;
use function delete_post_meta;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function get_current_screen;
use function get_post;
use function get_post_meta;
use function in_array;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_title;
use function selected;
use function update_post_meta;
use function wp_enqueue_script;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Handles pricing and availability meta boxes for experiences.
 */
final class ExperienceMetaBoxes
{
    /**
     * Register hooks for the meta boxes.
     */
    public function register_hooks(): void
    {
        add_action('add_meta_boxes_fp_experience', [$this, 'add_meta_boxes']);
        add_action('save_post_fp_experience', [$this, 'save_meta_boxes'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'maybe_show_pricing_notice']);
    }

    /**
     * Registers pricing and availability meta boxes.
     */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'fp_exp_pricing',
            esc_html__('Biglietti & Prezzi', 'fp-experiences'),
            [$this, 'render_pricing_meta_box'],
            'fp_experience',
            'normal',
            'default'
        );

        add_meta_box(
            'fp_exp_availability',
            esc_html__('Calendario & Slot', 'fp-experiences'),
            [$this, 'render_availability_meta_box'],
            'fp_experience',
            'normal',
            'default'
        );
    }

    /**
     * Enqueue admin assets for the meta boxes.
     */
    public function enqueue_assets(string $hook_suffix): void
    {
        if (! in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || 'fp_experience' !== $screen->post_type) {
            return;
        }

        wp_enqueue_script(
            'fp-exp-experience-meta-boxes',
            FP_EXP_PLUGIN_URL . 'assets/admin/js/experience-meta-boxes.js',
            [],
            FP_EXP_VERSION,
            true
        );
    }

    /**
     * Render pricing meta box content.
     */
    public function render_pricing_meta_box(\WP_Post $post): void
    {
        $pricing = $this->get_pricing_meta($post->ID);

        wp_nonce_field('fp_exp_pricing_nonce', 'fp_exp_pricing_nonce');

        $tickets = $pricing['tickets'];
        if (empty($tickets)) {
            $tickets = [['label' => '', 'price' => '', 'capacity' => '']];
        }

        $addons = $pricing['addons'];
        if (empty($addons)) {
            $addons = [['name' => '', 'price' => '', 'type' => 'person']];
        }

        $group = $pricing['group'];
        $tax_class = $pricing['tax_class'];
        $selected_tax_class = '' === $tax_class ? 'standard' : $tax_class;

        $tax_class_options = $this->get_tax_class_options();
        ?>
        <div class="fp-exp-meta-box fp-exp-meta-box--pricing">
            <p><?php esc_html_e('Configura le opzioni di prezzo per questa esperienza.', 'fp-experiences'); ?></p>

            <h4><?php esc_html_e('🎟️ Biglietti', 'fp-experiences'); ?></h4>
            <div class="fp-exp-repeater" data-next-index="<?php echo esc_attr((string) count($pricing['tickets'])); ?>" data-repeater="tickets">
                <div class="fp-exp-repeater-items">
                    <?php foreach ($tickets as $index => $ticket) : ?>
                        <?php $this->render_ticket_row((string) $index, $ticket); ?>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template>
                    <?php $this->render_ticket_row('__INDEX__', ['label' => '', 'price' => '', 'capacity' => ''], true); ?>
                </template>
                <p>
                    <button type="button" class="button" data-repeater-add><?php esc_html_e('Aggiungi biglietto', 'fp-experiences'); ?></button>
                </p>
            </div>

            <h4><?php esc_html_e('👥 Prezzo gruppo (opzionale)', 'fp-experiences'); ?></h4>
            <div class="fp-exp-group-pricing">
                <p>
                    <label>
                        <?php esc_html_e('Prezzo totale a gruppo (€)', 'fp-experiences'); ?><br />
                        <input type="number" step="0.01" min="0" name="fp_exp_pricing[group][price]" value="<?php echo esc_attr($group['price'] ?? ''); ?>" />
                    </label>
                </p>
                <p>
                    <label>
                        <?php esc_html_e('Capienza max gruppo', 'fp-experiences'); ?><br />
                        <input type="number" min="0" name="fp_exp_pricing[group][capacity]" value="<?php echo esc_attr($group['capacity'] ?? ''); ?>" />
                    </label>
                </p>
            </div>

            <h4><?php esc_html_e('➕ Add-on (opzionali)', 'fp-experiences'); ?></h4>
            <div class="fp-exp-repeater" data-next-index="<?php echo esc_attr((string) count($pricing['addons'])); ?>" data-repeater="addons">
                <div class="fp-exp-repeater-items">
                    <?php foreach ($addons as $index => $addon) : ?>
                        <?php $this->render_addon_row((string) $index, $addon); ?>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template>
                    <?php $this->render_addon_row('__INDEX__', ['name' => '', 'price' => '', 'type' => 'person'], true); ?>
                </template>
                <p>
                    <button type="button" class="button" data-repeater-add><?php esc_html_e('Aggiungi add-on', 'fp-experiences'); ?></button>
                </p>
            </div>

            <h4><?php esc_html_e('🧮 IVA', 'fp-experiences'); ?></h4>
            <p>
                <label for="fp-exp-tax-class">
                    <?php esc_html_e('Classe tassa WooCommerce', 'fp-experiences'); ?>
                </label>
                <select id="fp-exp-tax-class" name="fp_exp_pricing[tax_class]">
                    <option value="">&mdash; <?php esc_html_e('Seleziona classe tassa', 'fp-experiences'); ?> &mdash;</option>
                    <?php foreach ($tax_class_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $selected_tax_class); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
        <?php
    }

    /**
     * Render availability meta box content.
     */
    public function render_availability_meta_box(\WP_Post $post): void
    {
        $availability = $this->get_availability_meta($post->ID);

        wp_nonce_field('fp_exp_availability_nonce', 'fp_exp_availability_nonce');

        $frequencies = [
            'daily' => esc_html__('Giornaliera', 'fp-experiences'),
            'weekly' => esc_html__('Settimanale', 'fp-experiences'),
            'custom' => esc_html__('Personalizzata', 'fp-experiences'),
        ];

        $times = $availability['times'];
        if (empty($times)) {
            $times = [''];
        }

        $custom_slots = $availability['custom_slots'];
        if (empty($custom_slots)) {
            $custom_slots = [['date' => '', 'time' => '']];
        }

        $days = $availability['days_of_week'];

        ?>
        <div class="fp-exp-meta-box fp-exp-meta-box--availability">
            <p><?php esc_html_e('Definisci la disponibilità e gli slot prenotabili.', 'fp-experiences'); ?></p>

            <p>
                <label for="fp-exp-frequency">
                    <?php esc_html_e('Frequenza', 'fp-experiences'); ?>
                </label>
                <select id="fp-exp-frequency" name="fp_exp_availability[frequency]" data-frequency-selector>
                    <?php foreach ($frequencies as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($availability['frequency'], $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <div class="fp-exp-weekly-days" data-frequency-panel="weekly">
                <p><?php esc_html_e('Seleziona i giorni della settimana disponibili.', 'fp-experiences'); ?></p>
                <div class="fp-exp-weekly-days__list">
                    <?php foreach ($this->get_week_days() as $day_key => $day_label) : ?>
                        <label>
                            <input type="checkbox" name="fp_exp_availability[days_of_week][]" value="<?php echo esc_attr($day_key); ?>" <?php checked(in_array($day_key, $days, true)); ?> />
                            <?php echo esc_html($day_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="fp-exp-times" data-frequency-panel="daily" data-frequency-panel-secondary="weekly">
                <p><?php esc_html_e('Orari disponibili', 'fp-experiences'); ?></p>
                <div class="fp-exp-repeater" data-next-index="<?php echo esc_attr((string) count($availability['times'])); ?>" data-repeater="times">
                    <div class="fp-exp-repeater-items">
                        <?php foreach ($times as $index => $time) : ?>
                            <?php $this->render_time_row((string) $index, $time); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_time_row('__INDEX__', '', true); ?>
                    </template>
                    <p>
                        <button type="button" class="button" data-repeater-add><?php esc_html_e('Aggiungi orario', 'fp-experiences'); ?></button>
                    </p>
                </div>
            </div>

            <div class="fp-exp-custom-slots" data-frequency-panel="custom">
                <p><?php esc_html_e('Slot personalizzati', 'fp-experiences'); ?></p>
                <div class="fp-exp-repeater" data-next-index="<?php echo esc_attr((string) count($availability['custom_slots'])); ?>" data-repeater="custom_slots">
                    <div class="fp-exp-repeater-items">
                        <?php foreach ($custom_slots as $index => $slot) : ?>
                            <?php $this->render_custom_slot_row((string) $index, $slot); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_custom_slot_row('__INDEX__', ['date' => '', 'time' => ''], true); ?>
                    </template>
                    <p>
                        <button type="button" class="button" data-repeater-add><?php esc_html_e('Aggiungi slot personalizzato', 'fp-experiences'); ?></button>
                    </p>
                </div>
            </div>

            <p>
                <label>
                    <?php esc_html_e('Capienza slot', 'fp-experiences'); ?><br />
                    <input type="number" min="0" name="fp_exp_availability[slot_capacity]" value="<?php echo esc_attr((string) $availability['slot_capacity']); ?>" />
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta box data.
     */
    public function save_meta_boxes(int $post_id, \WP_Post $post, bool $update): void
    {
        unset($post, $update);

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->save_pricing_meta($post_id);
        $this->save_availability_meta($post_id);
    }

    /**
     * Save pricing data.
     */
    private function save_pricing_meta(int $post_id): void
    {
        if (! isset($_POST['fp_exp_pricing_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fp_exp_pricing_nonce'])), 'fp_exp_pricing_nonce')) {
            return;
        }

        $raw = $_POST['fp_exp_pricing'] ?? null;
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_exp_pricing');
            return;
        }

        $raw = wp_unslash($raw);

        $pricing = [
            'tickets' => [],
            'group' => [],
            'addons' => [],
            'tax_class' => '',
        ];

        if (isset($raw['tickets']) && is_array($raw['tickets'])) {
            foreach ($raw['tickets'] as $ticket) {
                if (! is_array($ticket)) {
                    continue;
                }

                $label = isset($ticket['label']) ? sanitize_text_field((string) $ticket['label']) : '';
                $price = isset($ticket['price']) ? floatval($ticket['price']) : 0.0;
                $capacity = isset($ticket['capacity']) ? absint($ticket['capacity']) : 0;

                if ('' === $label && 0.0 === $price && 0 === $capacity) {
                    continue;
                }

                $pricing['tickets'][] = [
                    'label' => $label,
                    'price' => $price,
                    'capacity' => $capacity,
                ];
            }
        }

        if (isset($raw['group']) && is_array($raw['group'])) {
            $group_price = isset($raw['group']['price']) ? floatval($raw['group']['price']) : 0.0;
            $group_capacity = isset($raw['group']['capacity']) ? absint($raw['group']['capacity']) : 0;

            if (0.0 !== $group_price || 0 !== $group_capacity) {
                $pricing['group'] = [
                    'price' => $group_price,
                    'capacity' => $group_capacity,
                ];
            }
        }

        if (isset($raw['addons']) && is_array($raw['addons'])) {
            foreach ($raw['addons'] as $addon) {
                if (! is_array($addon)) {
                    continue;
                }

                $name = isset($addon['name']) ? sanitize_text_field((string) $addon['name']) : '';
                $price = isset($addon['price']) ? floatval($addon['price']) : 0.0;
                $type = isset($addon['type']) ? sanitize_key((string) $addon['type']) : 'person';

                if (! in_array($type, ['person', 'booking'], true)) {
                    $type = 'person';
                }

                if ('' === $name && 0.0 === $price) {
                    continue;
                }

                $pricing['addons'][] = [
                    'name' => $name,
                    'price' => $price,
                    'type' => $type,
                ];
            }
        }

        if (isset($raw['tax_class'])) {
            $tax_class = sanitize_key((string) $raw['tax_class']);
            if ('standard' === $tax_class) {
                $tax_class = '';
            }

            $pricing['tax_class'] = $tax_class;
        }

        if (empty($pricing['tickets']) && empty($pricing['group']) && empty($pricing['addons']) && '' === $pricing['tax_class']) {
            delete_post_meta($post_id, '_fp_exp_pricing');
            return;
        }

        update_post_meta($post_id, '_fp_exp_pricing', $pricing);
    }

    /**
     * Save availability data.
     */
    private function save_availability_meta(int $post_id): void
    {
        if (! isset($_POST['fp_exp_availability_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fp_exp_availability_nonce'])), 'fp_exp_availability_nonce')) {
            return;
        }

        $raw = $_POST['fp_exp_availability'] ?? null;
        if (! is_array($raw)) {
            delete_post_meta($post_id, '_fp_exp_availability');
            return;
        }

        $raw = wp_unslash($raw);

        $frequency = isset($raw['frequency']) ? sanitize_key((string) $raw['frequency']) : 'daily';
        if (! in_array($frequency, ['daily', 'weekly', 'custom'], true)) {
            $frequency = 'daily';
        }

        $slot_capacity = isset($raw['slot_capacity']) ? absint($raw['slot_capacity']) : 0;

        $times = [];
        if (isset($raw['times']) && is_array($raw['times'])) {
            foreach ($raw['times'] as $time) {
                $sanitized_time = trim(sanitize_text_field((string) $time));
                if ('' !== $sanitized_time) {
                    $times[] = $sanitized_time;
                }
            }
        }

        $days = [];
        if (isset($raw['days_of_week']) && is_array($raw['days_of_week'])) {
            foreach ($raw['days_of_week'] as $day) {
                $day_key = sanitize_key((string) $day);
                if (array_key_exists($day_key, $this->get_week_days()) && ! in_array($day_key, $days, true)) {
                    $days[] = $day_key;
                }
            }
        }

        $custom_slots = [];
        if (isset($raw['custom_slots']) && is_array($raw['custom_slots'])) {
            foreach ($raw['custom_slots'] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $date = isset($slot['date']) ? sanitize_text_field((string) $slot['date']) : '';
                $time = isset($slot['time']) ? sanitize_text_field((string) $slot['time']) : '';

                if ('' === $date && '' === $time) {
                    continue;
                }

                $custom_slots[] = [
                    'date' => $date,
                    'time' => $time,
                ];
            }
        }

        $availability = [
            'frequency' => $frequency,
            'times' => $times,
            'days_of_week' => $days,
            'custom_slots' => $custom_slots,
            'slot_capacity' => $slot_capacity,
        ];

        if ('custom' !== $frequency && empty($times)) {
            $availability['times'] = [];
        }

        if ('weekly' !== $frequency) {
            $availability['days_of_week'] = [];
        }

        if ('custom' !== $frequency) {
            $availability['custom_slots'] = [];
        }

        if (
            [] === $availability['times']
            && [] === $availability['custom_slots']
            && 0 === $availability['slot_capacity']
        ) {
            delete_post_meta($post_id, '_fp_exp_availability');
            return;
        }

        update_post_meta($post_id, '_fp_exp_availability', $availability);
    }

    /**
     * Display warning notice when pricing is missing.
     */
    public function maybe_show_pricing_notice(): void
    {
        $screen = get_current_screen();
        if (! $screen || 'post' !== $screen->base || 'fp_experience' !== $screen->post_type) {
            return;
        }

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! $post_id) {
            return;
        }

        $post = get_post($post_id);
        if (! $post || 'publish' !== $post->post_status) {
            return;
        }

        $pricing = get_post_meta($post_id, '_fp_exp_pricing', true);
        if (! is_array($pricing) || ! $this->has_pricing($pricing)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Questa esperienza è pubblicata senza prezzi configurati. Aggiungi almeno un prezzo prima di accettare prenotazioni.', 'fp-experiences') . '</p></div>';
        }
    }

    /**
     * Determine if the pricing meta contains valid prices.
     *
     * @param array<string, mixed> $pricing Pricing data.
     */
    private function has_pricing(array $pricing): bool
    {
        if (! empty($pricing['tickets'])) {
            foreach ((array) $pricing['tickets'] as $ticket) {
                if (is_array($ticket) && isset($ticket['price']) && floatval($ticket['price']) > 0) {
                    return true;
                }
            }
        }

        if (! empty($pricing['group']) && isset($pricing['group']['price']) && floatval($pricing['group']['price']) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve pricing meta structure.
     *
     * @return array<string, mixed>
     */
    private function get_pricing_meta(int $post_id): array
    {
        $defaults = [
            'tickets' => [],
            'group' => [],
            'addons' => [],
            'tax_class' => '',
        ];

        $meta = get_post_meta($post_id, '_fp_exp_pricing', true);
        if (! is_array($meta)) {
            return $defaults;
        }

        return array_merge($defaults, $meta);
    }

    /**
     * Retrieve availability meta structure.
     *
     * @return array<string, mixed>
     */
    private function get_availability_meta(int $post_id): array
    {
        $defaults = [
            'frequency' => 'daily',
            'times' => [],
            'days_of_week' => [],
            'custom_slots' => [],
            'slot_capacity' => 0,
        ];

        $meta = get_post_meta($post_id, '_fp_exp_availability', true);
        if (! is_array($meta)) {
            return $defaults;
        }

        return array_merge($defaults, $meta);
    }

    /**
     * Render single ticket row.
     *
     * @param array<string, mixed> $ticket Ticket data.
     */
    private function render_ticket_row(string $index, array $ticket, bool $is_template = false): void
    {
        $name_prefix = 'fp_exp_pricing[tickets][' . $index . ']';
        $label_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][label]' : $name_prefix . '[label]';
        $price_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][price]' : $name_prefix . '[price]';
        $capacity_name = $is_template ? 'fp_exp_pricing[tickets][__INDEX__][capacity]' : $name_prefix . '[capacity]';

        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <p>
                <label>
                    <?php esc_html_e('Tipo biglietto', 'fp-experiences'); ?><br />
                    <input type="text" <?php echo $this->name_attribute($label_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['label'] ?? '')); ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Prezzo unitario (€)', 'fp-experiences'); ?><br />
                    <input type="number" step="0.01" min="0" <?php echo $this->name_attribute($price_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['price'] ?? '')); ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Capienza massima', 'fp-experiences'); ?><br />
                    <input type="number" min="0" <?php echo $this->name_attribute($capacity_name, $is_template); ?> value="<?php echo esc_attr((string) ($ticket['capacity'] ?? '')); ?>" />
                </label>
            </p>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    /**
     * Render single addon row.
     *
     * @param array<string, mixed> $addon Addon data.
     */
    private function render_addon_row(string $index, array $addon, bool $is_template = false): void
    {
        $name_prefix = 'fp_exp_pricing[addons][' . $index . ']';
        $name_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][name]' : $name_prefix . '[name]';
        $price_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][price]' : $name_prefix . '[price]';
        $type_name = $is_template ? 'fp_exp_pricing[addons][__INDEX__][type]' : $name_prefix . '[type]';

        $type_value = isset($addon['type']) ? (string) $addon['type'] : 'person';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <p>
                <label>
                    <?php esc_html_e('Nome add-on', 'fp-experiences'); ?><br />
                    <input type="text" <?php echo $this->name_attribute($name_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['name'] ?? '')); ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Prezzo (€)', 'fp-experiences'); ?><br />
                    <input type="number" step="0.01" min="0" <?php echo $this->name_attribute($price_name, $is_template); ?> value="<?php echo esc_attr((string) ($addon['price'] ?? '')); ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Calcolo prezzo', 'fp-experiences'); ?><br />
                    <select <?php echo $this->name_attribute($type_name, $is_template); ?>>
                        <option value="person" <?php selected($type_value, 'person'); ?>><?php esc_html_e('Per persona', 'fp-experiences'); ?></option>
                        <option value="booking" <?php selected($type_value, 'booking'); ?>><?php esc_html_e('Per prenotazione', 'fp-experiences'); ?></option>
                    </select>
                </label>
            </p>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    /**
     * Render time row.
     */
    private function render_time_row(string $index, string $time, bool $is_template = false): void
    {
        $field_name = $is_template ? 'fp_exp_availability[times][__INDEX__]' : 'fp_exp_availability[times][' . $index . ']';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <p>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Orario disponibile', 'fp-experiences'); ?></span>
                    <input type="time" <?php echo $this->name_attribute($field_name, $is_template); ?> value="<?php echo esc_attr($time); ?>" />
                </label>
            </p>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    /**
     * Render custom slot row.
     *
     * @param array<string, string> $slot Slot data.
     */
    private function render_custom_slot_row(string $index, array $slot, bool $is_template = false): void
    {
        $prefix = 'fp_exp_availability[custom_slots][' . $index . ']';
        $date_name = $is_template ? 'fp_exp_availability[custom_slots][__INDEX__][date]' : $prefix . '[date]';
        $time_name = $is_template ? 'fp_exp_availability[custom_slots][__INDEX__][time]' : $prefix . '[time]';
        ?>
        <div class="fp-exp-repeater-row" data-repeater-item>
            <p>
                <label>
                    <?php esc_html_e('Data', 'fp-experiences'); ?><br />
                    <input type="date" <?php echo $this->name_attribute($date_name, $is_template); ?> value="<?php echo esc_attr($slot['date'] ?? ''); ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Orario', 'fp-experiences'); ?><br />
                    <input type="time" <?php echo $this->name_attribute($time_name, $is_template); ?> value="<?php echo esc_attr($slot['time'] ?? ''); ?>" />
                </label>
            </p>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    /**
     * Prepare name attribute for template/non-template fields.
     */
    private function name_attribute(string $name, bool $is_template): string
    {
        if ($is_template) {
            return 'data-name="' . esc_attr($name) . '"';
        }

        return 'name="' . esc_attr($name) . '"';
    }

    /**
     * Get week days list.
     *
     * @return array<string, string>
     */
    private function get_week_days(): array
    {
        return [
            'mon' => esc_html__('Lunedì', 'fp-experiences'),
            'tue' => esc_html__('Martedì', 'fp-experiences'),
            'wed' => esc_html__('Mercoledì', 'fp-experiences'),
            'thu' => esc_html__('Giovedì', 'fp-experiences'),
            'fri' => esc_html__('Venerdì', 'fp-experiences'),
            'sat' => esc_html__('Sabato', 'fp-experiences'),
            'sun' => esc_html__('Domenica', 'fp-experiences'),
        ];
    }

    /**
     * Retrieve tax class options from WooCommerce.
     *
     * @return array<string, string>
     */
    private function get_tax_class_options(): array
    {
        $options = [
            'standard' => esc_html__('Aliquota standard', 'fp-experiences'),
        ];

        if (class_exists('WC_Tax')) {
            $classes = \WC_Tax::get_tax_classes();
            foreach ($classes as $class_name) {
                $slug = sanitize_title($class_name);
                if ('' === $slug) {
                    continue;
                }

                $options[$slug] = $class_name;
            }
        }

        return $options;
    }
}
