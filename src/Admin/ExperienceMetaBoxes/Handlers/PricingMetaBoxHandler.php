<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;

use function absint;
use function esc_attr;
use function esc_html;
use function function_exists;
use function sanitize_key;
use function sanitize_text_field;

/**
 * Handler for Pricing tab in Experience Meta Box.
 * 
 * Handles tickets, addons, group pricing, and tax class.
 * 
 * NOTE: This is a complex handler due to legacy support and multiple pricing structures.
 * The handler manages both new and legacy pricing formats for backward compatibility.
 * 
 * @version 1.2.15
 */
final class PricingMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    /**
     * Get currency symbol with fallback if WooCommerce is not available.
     */
    private function get_currency_symbol(): string
    {
        if (function_exists('get_woocommerce_currency_symbol')) {
            return get_woocommerce_currency_symbol();
        }
        
        // Fallback to Euro if WooCommerce is not available
        return '€';
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-pricing-panel';
        $tickets = $data['tickets'] ?? [];
        $addons = $data['addons'] ?? [];
        $group = $data['group'] ?? [];
        $tax_class = $data['tax_class'] ?? '';
        $base_price = $data['base_price'] ?? '';
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-pricing"
            data-tab-panel="pricing"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Prezzi e Biglietti', 'fp-experiences'); ?></legend>
                
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-base-price">
                        <?php 
                        printf(
                            esc_html__('Prezzo base (%s)', 'fp-experiences'),
                            esc_html($this->get_currency_symbol())
                        ); 
                        ?>
                    </label>
                    <input
                        type="number"
                        id="fp-exp-base-price"
                        name="fp_exp_pricing[base_price]"
                        value="<?php echo esc_attr($base_price); ?>"
                        min="0"
                        step="0.01"
                        class="small-text"
                    />
                    <p class="fp-exp-field__description">
                        <?php esc_html_e('Prezzo base per persona. Se vuoto, verrà usato il prezzo del primo biglietto.', 'fp-experiences'); ?>
                    </p>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-tax-class">
                        <?php esc_html_e('Classe fiscale', 'fp-experiences'); ?>
                    </label>
                    <select id="fp-exp-tax-class" name="fp_exp_pricing[tax_class]">
                        <option value=""><?php esc_html_e('Standard', 'fp-experiences'); ?></option>
                        <?php
                        if (class_exists('\WC_Tax')) {
                            $tax_classes = \WC_Tax::get_tax_classes();
                            foreach ($tax_classes as $class) {
                                $class_slug = sanitize_title($class);
                                ?>
                                <option value="<?php echo esc_attr($class_slug); ?>" <?php selected($tax_class, $class_slug, true); ?>>
                                    <?php echo esc_html($class); ?>
                                </option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </div>

                <?php $this->render_tickets_section($tickets); ?>
                <?php $this->render_group_pricing_section($group); ?>
                <?php $this->render_addons_section($addons); ?>
            </fieldset>
        </section>
        <?php
    }

    /**
     * Render tickets section.
     */
    private function render_tickets_section(array $tickets): void
    {
        ?>
        <div class="fp-exp-field">
            <h3 class="fp-exp-field__subtitle"><?php esc_html_e('Biglietti', 'fp-experiences'); ?></h3>
            <p class="fp-exp-field__description">
                <?php esc_html_e('Definisci i tipi di biglietto disponibili per questa esperienza.', 'fp-experiences'); ?>
            </p>
            <div class="fp-exp-repeater" data-repeater="tickets">
                <div class="fp-exp-repeater__items">
                    <?php foreach ($tickets as $index => $ticket) : ?>
                        <?php $this->render_ticket_row((string) $index, $ticket); ?>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template>
                    <?php $this->render_ticket_row('__INDEX__', [
                        'slug' => '',
                        'label' => '',
                        'price' => '',
                        'capacity' => 0,
                        'min' => 0,
                        'max' => 0,
                    ], true); ?>
                </template>
                <p class="fp-exp-repeater__actions">
                    <button type="button" class="button button-secondary" data-repeater-add>
                        <?php esc_html_e('Aggiungi Biglietto', 'fp-experiences'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single ticket row.
     */
    private function render_ticket_row(string $index, array $ticket, bool $is_template = false): void
    {
        $field_name = 'fp_exp_pricing[tickets]';
        $slug = $ticket['slug'] ?? '';
        $label = $ticket['label'] ?? '';
        $price = $ticket['price'] ?? '';
        $capacity = $ticket['capacity'] ?? 0;
        $min = $ticket['min'] ?? 0;
        $max = $ticket['max'] ?? 0;
        $use_as_price_from = isset($ticket['use_as_price_from']) && !empty($ticket['use_as_price_from']);
        ?>
        <div class="fp-exp-repeater__item" data-repeater-item>
            <div class="fp-exp-repeater__item-header">
                <h4 class="fp-exp-repeater__item-title">
                    <?php esc_html_e('Biglietto', 'fp-experiences'); ?> <span data-repeater-index><?php echo esc_html($index); ?></span>
                </h4>
                <button type="button" class="button-link fp-exp-repeater__item-remove" data-repeater-remove>
                    <?php esc_html_e('Rimuovi', 'fp-experiences'); ?>
                </button>
            </div>
            <div class="fp-exp-repeater__item-content">
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-ticket-label-<?php echo esc_attr($index); ?>">
                        <?php esc_html_e('Etichetta', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="text"
                        id="fp-exp-ticket-label-<?php echo esc_attr($index); ?>"
                        name="<?php echo esc_attr($field_name . '[' . $index . '][label]'); ?>"
                        value="<?php echo esc_attr($label); ?>"
                        class="regular-text"
                        placeholder="<?php echo esc_attr__('Es. Adulto, Bambino', 'fp-experiences'); ?>"
                        <?php echo $is_template ? 'data-repeater-field="label"' : ''; ?>
                    />
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-ticket-price-<?php echo esc_attr($index); ?>">
                            <?php 
                            printf(
                                esc_html__('Prezzo (%s)', 'fp-experiences'),
                                esc_html($this->get_currency_symbol())
                            ); 
                            ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-ticket-price-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][price]'); ?>"
                            value="<?php echo esc_attr($price); ?>"
                            min="0"
                            step="0.01"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="price"' : ''; ?>
                        />
                    </div>

                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-ticket-capacity-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Capacità', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-ticket-capacity-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][capacity]'); ?>"
                            value="<?php echo esc_attr((string) $capacity); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="capacity"' : ''; ?>
                        />
                    </div>
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-ticket-min-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Min', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-ticket-min-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][min]'); ?>"
                            value="<?php echo esc_attr((string) $min); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="min"' : ''; ?>
                        />
                    </div>

                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-ticket-max-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Max', 'fp-experiences'); ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-ticket-max-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][max]'); ?>"
                            value="<?php echo esc_attr((string) $max); ?>"
                            min="0"
                            step="1"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="max"' : ''; ?>
                        />
                    </div>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label">
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][use_as_price_from]'); ?>"
                            value="1"
                            <?php checked($use_as_price_from, true); ?>
                            <?php echo $is_template ? 'data-repeater-field="use_as_price_from"' : ''; ?>
                        />
                        <span><?php esc_html_e('Usa come prezzo "a partire da"', 'fp-experiences'); ?></span>
                        <?php $this->render_tooltip('fp-exp-ticket-primary-help-' . $index, esc_html__('Seleziona questo biglietto come riferimento per il prezzo "a partire da" mostrato nelle liste e nei widget. Se nessun biglietto è selezionato, verrà usato il prezzo più basso.', 'fp-experiences')); ?>
                    </label>
                    <p class="fp-exp-field__description" id="fp-exp-ticket-primary-help-<?php echo esc_attr($index); ?>">
                        <?php esc_html_e('Utile per evitare che il prezzo bambino venga mostrato come principale quando è più basso del prezzo adulto.', 'fp-experiences'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render group pricing section.
     */
    private function render_group_pricing_section(array $group): void
    {
        $group_price = $group['price'] ?? '';
        $group_capacity = $group['capacity'] ?? 0;
        ?>
        <div class="fp-exp-field">
            <h3 class="fp-exp-field__subtitle"><?php esc_html_e('Prezzo Gruppo', 'fp-experiences'); ?></h3>
            <div class="fp-exp-field fp-exp-field--columns">
                <div>
                    <label class="fp-exp-field__label" for="fp-exp-group-price">
                        <?php 
                        printf(
                            esc_html__('Prezzo gruppo (%s)', 'fp-experiences'),
                            esc_html($this->get_currency_symbol())
                        ); 
                        ?>
                    </label>
                    <input
                        type="number"
                        id="fp-exp-group-price"
                        name="fp_exp_pricing[group][price]"
                        value="<?php echo esc_attr($group_price); ?>"
                        min="0"
                        step="0.01"
                        class="small-text"
                    />
                </div>

                <div>
                    <label class="fp-exp-field__label" for="fp-exp-group-capacity">
                        <?php esc_html_e('Capacità gruppo', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="number"
                        id="fp-exp-group-capacity"
                        name="fp_exp_pricing[group][capacity]"
                        value="<?php echo esc_attr((string) $group_capacity); ?>"
                        min="0"
                        step="1"
                        class="small-text"
                    />
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render addons section.
     */
    private function render_addons_section(array $addons): void
    {
        ?>
        <div class="fp-exp-field">
            <h3 class="fp-exp-field__subtitle"><?php esc_html_e('Addon', 'fp-experiences'); ?></h3>
            <p class="fp-exp-field__description">
                <?php esc_html_e('Aggiungi servizi opzionali che possono essere selezionati durante la prenotazione.', 'fp-experiences'); ?>
            </p>
            <div class="fp-exp-repeater" data-repeater="addons">
                <div class="fp-exp-repeater__items">
                    <?php foreach ($addons as $index => $addon) : ?>
                        <?php $this->render_addon_row((string) $index, $addon); ?>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template>
                    <?php $this->render_addon_row('__INDEX__', [
                        'name' => '',
                        'price' => '',
                        'type' => 'person',
                        'slug' => '',
                    ], true); ?>
                </template>
                <p class="fp-exp-repeater__actions">
                    <button type="button" class="button button-secondary" data-repeater-add>
                        <?php esc_html_e('Aggiungi Addon', 'fp-experiences'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single addon row.
     */
    private function render_addon_row(string $index, array $addon, bool $is_template = false): void
    {
        $field_name = 'fp_exp_pricing[addons]';
        $name = $addon['name'] ?? '';
        $price = $addon['price'] ?? '';
        $type = $addon['type'] ?? 'person';
        ?>
        <div class="fp-exp-repeater__item" data-repeater-item>
            <div class="fp-exp-repeater__item-header">
                <h4 class="fp-exp-repeater__item-title">
                    <?php esc_html_e('Addon', 'fp-experiences'); ?> <span data-repeater-index><?php echo esc_html($index); ?></span>
                </h4>
                <button type="button" class="button-link fp-exp-repeater__item-remove" data-repeater-remove>
                    <?php esc_html_e('Rimuovi', 'fp-experiences'); ?>
                </button>
            </div>
            <div class="fp-exp-repeater__item-content">
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-addon-name-<?php echo esc_attr($index); ?>">
                        <?php esc_html_e('Nome', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="text"
                        id="fp-exp-addon-name-<?php echo esc_attr($index); ?>"
                        name="<?php echo esc_attr($field_name . '[' . $index . '][name]'); ?>"
                        value="<?php echo esc_attr($name); ?>"
                        class="regular-text"
                        <?php echo $is_template ? 'data-repeater-field="name"' : ''; ?>
                    />
                </div>

                <div class="fp-exp-field fp-exp-field--columns">
                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-addon-price-<?php echo esc_attr($index); ?>">
                            <?php 
                            printf(
                                esc_html__('Prezzo (%s)', 'fp-experiences'),
                                esc_html($this->get_currency_symbol())
                            ); 
                            ?>
                        </label>
                        <input
                            type="number"
                            id="fp-exp-addon-price-<?php echo esc_attr($index); ?>"
                            name="<?php echo esc_attr($field_name . '[' . $index . '][price]'); ?>"
                            value="<?php echo esc_attr($price); ?>"
                            min="0"
                            step="0.01"
                            class="small-text"
                            <?php echo $is_template ? 'data-repeater-field="price"' : ''; ?>
                        />
                    </div>

                    <div>
                        <label class="fp-exp-field__label" for="fp-exp-addon-type-<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Tipo', 'fp-experiences'); ?>
                        </label>
                        <select id="fp-exp-addon-type-<?php echo esc_attr($index); ?>" name="<?php echo esc_attr($field_name . '[' . $index . '][type]'); ?>">
                            <option value="person" <?php selected($type, 'person', true); ?>>
                                <?php esc_html_e('Per persona', 'fp-experiences'); ?>
                            </option>
                            <option value="booking" <?php selected($type, 'booking', true); ?>>
                                <?php esc_html_e('Per prenotazione', 'fp-experiences'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        // Base price
        $base_price = isset($raw['base_price']) ? sanitize_text_field((string) $raw['base_price']) : '';
        $this->update_or_delete_meta($post_id, 'base_price', $base_price);

        // Tax class
        $tax_class = isset($raw['tax_class']) ? sanitize_key((string) $raw['tax_class']) : '';
        $this->update_or_delete_meta($post_id, 'tax_class', $tax_class);

        // Tickets
        $tickets_raw = $raw['tickets'] ?? [];
        $tickets = [];
        if (is_array($tickets_raw)) {
            foreach ($tickets_raw as $ticket) {
                if (!is_array($ticket)) {
                    continue;
                }

                $label = sanitize_text_field($ticket['label'] ?? '');
                $price = isset($ticket['price']) ? max(0.0, (float) $ticket['price']) : 0.0;
                $capacity = isset($ticket['capacity']) ? absint((string) $ticket['capacity']) : 0;
                $min = isset($ticket['min']) ? absint((string) $ticket['min']) : 0;
                $max = isset($ticket['max']) ? absint((string) $ticket['max']) : 0;
                $use_as_price_from = isset($ticket['use_as_price_from']) && !empty($ticket['use_as_price_from']);

                if ($label === '') {
                    continue;
                }

                $slug = sanitize_key($label);
                $ticket_data = [
                    'slug' => $slug,
                    'label' => $label,
                    'price' => $price,
                    'capacity' => $capacity,
                    'min' => $min,
                    'max' => $max,
                ];
                
                // Add use_as_price_from if checked
                if ($use_as_price_from) {
                    $ticket_data['use_as_price_from'] = true;
                }
                
                $tickets[] = $ticket_data;
            }
        }

        // Group pricing
        $group = $raw['group'] ?? [];
        $group_price = isset($group['price']) ? max(0.0, (float) $group['price']) : 0.0;
        $group_capacity = isset($group['capacity']) ? absint((string) $group['capacity']) : 0;
        $group_data = [];
        if ($group_price > 0 || $group_capacity > 0) {
            $group_data = [
                'price' => $group_price,
                'capacity' => $group_capacity,
            ];
        }

        // Addons
        $addons_raw = $raw['addons'] ?? [];
        $addons = [];
        if (is_array($addons_raw)) {
            foreach ($addons_raw as $index => $addon) {
                if (!is_array($addon)) {
                    continue;
                }

                $name = sanitize_text_field($addon['name'] ?? '');
                $price = isset($addon['price']) ? max(0.0, (float) $addon['price']) : 0.0;
                $type = isset($addon['type']) ? sanitize_key((string) $addon['type']) : 'person';
                $slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';

                if ($name === '' && $slug === '') {
                    continue;
                }

                if ($slug === '' && $name !== '') {
                    $slug = sanitize_key($name);
                }

                if (!in_array($type, ['person', 'booking'], true)) {
                    $type = 'person';
                }

                $addons[] = [
                    'name' => $name,
                    'price' => $price,
                    'type' => $type,
                    'slug' => $slug,
                ];
            }
        }

        // Save pricing data
        $pricing_data = [
            'tickets' => $tickets,
            'group' => $group_data,
            'addons' => $addons,
            'tax_class' => $tax_class,
            'base_price' => $base_price,
        ];

        // Save to _fp_exp_pricing meta
        $this->update_or_delete_meta($post_id, 'exp_pricing', !empty($tickets) || !empty($group_data) || !empty($addons) ? $pricing_data : null);

        // Legacy support: also save to _fp_ticket_types and _fp_addons for backward compatibility
        if (!empty($tickets)) {
            $legacy_tickets = [];
            foreach ($tickets as $ticket) {
                $legacy_ticket = [
                    'slug' => $ticket['slug'],
                    'label' => $ticket['label'],
                    'price' => $ticket['price'],
                    'capacity' => $ticket['capacity'],
                ];
                // Include use_as_price_from flag for legacy compatibility
                if (isset($ticket['use_as_price_from'])) {
                    $legacy_ticket['use_as_price_from'] = $ticket['use_as_price_from'];
                }
                $legacy_tickets[] = $legacy_ticket;
            }
            $this->update_or_delete_meta($post_id, 'ticket_types', $legacy_tickets);
        } else {
            delete_post_meta($post_id, '_fp_ticket_types');
        }

        if (!empty($addons)) {
            $this->update_or_delete_meta($post_id, 'addons', $addons);
        } else {
            delete_post_meta($post_id, '_fp_addons');
        }
    }

    protected function get_meta_data(int $post_id): array
    {
        $defaults = [
            'tickets' => [],
            'group' => [],
            'addons' => [],
            'tax_class' => '',
            'base_price' => '',
        ];

        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $meta = [];
        $base_price = '';
        if ($repo !== null) {
            $meta = $repo->getMeta($post_id, '_fp_exp_pricing', []);
            if (!is_array($meta)) {
                $meta = [];
            }
            // Legacy support: read base_price from _fp_base_price
            $base_price = (string) $repo->getMeta($post_id, '_fp_base_price', '');
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $meta = get_post_meta($post_id, '_fp_exp_pricing', true);
            if (!is_array($meta)) {
                $meta = [];
            }
            // Legacy support: read base_price from _fp_base_price
            $base_price = get_post_meta($post_id, '_fp_base_price', true);
        }
        if ('' !== $base_price && !isset($meta['base_price'])) {
            $meta['base_price'] = (string) $base_price;
        }

        return array_merge($defaults, $meta);
    }
}

