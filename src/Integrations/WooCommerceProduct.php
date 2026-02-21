<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

use DateTimeImmutable;
use DateTimeZone;
use FP_Exp\Utils\Helpers;

use function absint;
use function add_filter;
use function add_action;
use function add_meta_box;
use function esc_attr;
use function esc_html;
use function esc_url;
use function get_current_screen;
use function get_edit_post_link;
use function get_post_meta;
use function get_the_post_thumbnail;
use function get_the_title;
use function has_post_thumbnail;
use function is_admin;
use function is_array;
use function is_numeric;
use function sanitize_key;
use function sanitize_text_field;
use function wc_get_order;
use function wp_kses_post;
use function __;
use function esc_html__;
use function ucfirst;
use function wc_price;
use function wp_doing_ajax;
use function wp_date;
use function wp_timezone;
use function get_option;

/**
 * Customizes WooCommerce cart/checkout display for experience items
 */
final class WooCommerceProduct implements HookableInterface
{
    public function register_hooks(): void
    {
        $this->register();
    }

    public function register(): void
    {
        // Set dynamic price for experience items (CRITICAL for checkout)
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price'], 10, 1);
        
        // STORE API: Set price when item is added to cart (for Blocks compatibility)
        add_filter('woocommerce_add_cart_item', [$this, 'set_price_on_add_to_cart'], 10, 2);
        
        // STORE API: Set price when cart is loaded from session (for Blocks compatibility)
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'set_price_on_add_to_cart'], 10, 2);
        
        // Customize cart item display
        add_filter('woocommerce_cart_item_name', [$this, 'customize_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'customize_cart_item_price'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        
        // Use experience image instead of virtual product placeholder
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'customize_cart_item_thumbnail'], 10, 3);
        
        // Save order item meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
        
        // Customize order item display
        add_filter('woocommerce_order_item_name', [$this, 'customize_order_item_name'], 10, 2);
        
        // Display order item meta with timezone conversion
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'format_order_item_meta_value'], 10, 3);

        // Hide technical meta keys from order display (admin)
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hide_technical_order_meta']);

        // Hide technical meta keys from order display (frontend - thank you page, order details, order-pay)
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'filter_order_item_meta_frontend'], 99, 2);

        // Format order item meta keys with human-readable labels
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'format_order_item_meta_key'], 10, 3);

        // Show RTB notice on thank you page
        add_action('woocommerce_thankyou', [$this, 'show_rtb_thankyou_notice'], 5, 1);

        // Customize order totals display for RTB
        add_filter('woocommerce_get_order_item_totals', [$this, 'customize_rtb_order_totals'], 10, 3);

        // Disable WooCommerce emails for experience orders (FP-Experiences sends its own emails)
        add_filter('woocommerce_email_enabled_new_order', [$this, 'maybe_disable_wc_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this, 'maybe_disable_wc_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [$this, 'maybe_disable_wc_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', [$this, 'maybe_disable_wc_email'], 10, 2);

        // Meta box with booking details on the WooCommerce order page
        add_action('add_meta_boxes', [$this, 'register_booking_meta_box']);
    }

    /**
     * Register meta box on WooCommerce order edit page.
     */
    public function register_booking_meta_box(): void
    {
        $screen = get_current_screen();

        if (! $screen) {
            return;
        }

        $allowed = ['shop_order', 'woocommerce_page_wc-orders'];

        if (! in_array($screen->id, $allowed, true) && 'shop_order' !== $screen->post_type) {
            return;
        }

        add_meta_box(
            'fp_exp_booking_details',
            __('Dettagli Prenotazione Esperienza', 'fp-experiences'),
            [$this, 'render_booking_meta_box'],
            $screen->id,
            'side',
            'high'
        );
    }

    /**
     * Render the booking details meta box.
     *
     * @param \WP_Post|object $post_or_order
     */
    public function render_booking_meta_box($post_or_order): void
    {
        $order_id = 0;

        if ($post_or_order instanceof \WC_Order) {
            $order_id = $post_or_order->get_id();
        } elseif ($post_or_order instanceof \WP_Post) {
            $order_id = $post_or_order->ID;
        } elseif (isset($_GET['id'])) {
            $order_id = absint($_GET['id']);
        }

        if ($order_id <= 0) {
            echo '<p>' . esc_html__('Ordine non trovato.', 'fp-experiences') . '</p>';
            return;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof \WC_Order) {
            echo '<p>' . esc_html__('Ordine non valido.', 'fp-experiences') . '</p>';
            return;
        }

        $isolated = $order->get_meta('_fp_exp_isolated_checkout');

        if ('yes' !== $isolated && 'fp-exp' !== $order->get_created_via() && 'fp-exp-rtb' !== $order->get_created_via()) {
            echo '<p style="color:#64748b;">' . esc_html__('Questo ordine non contiene esperienze.', 'fp-experiences') . '</p>';
            return;
        }

        $reservation_ids = \FP_Exp\Booking\Reservations::get_ids_by_order($order_id);

        if (empty($reservation_ids)) {
            echo '<p style="color:#64748b;">' . esc_html__('Nessuna prenotazione collegata a questo ordine.', 'fp-experiences') . '</p>';
            return;
        }

        $status_labels = [
            'pending' => __('In attesa pagamento', 'fp-experiences'),
            'pending_request' => __('In attesa conferma (RTB)', 'fp-experiences'),
            'approved_confirmed' => __('Approvata e confermata', 'fp-experiences'),
            'approved_pending_payment' => __('Approvata, pagamento in attesa', 'fp-experiences'),
            'declined' => __('Rifiutata', 'fp-experiences'),
            'paid' => __('Pagata', 'fp-experiences'),
            'cancelled' => __('Cancellata', 'fp-experiences'),
            'checked_in' => __('Check-in effettuato', 'fp-experiences'),
        ];

        $status_colors = [
            'pending' => '#f59e0b',
            'pending_request' => '#f59e0b',
            'approved_confirmed' => '#10b981',
            'approved_pending_payment' => '#3b82f6',
            'declined' => '#ef4444',
            'paid' => '#10b981',
            'cancelled' => '#ef4444',
            'checked_in' => '#8b5cf6',
        ];

        foreach ($reservation_ids as $rid) {
            $reservation = \FP_Exp\Booking\Reservations::get($rid);

            if (! $reservation) {
                continue;
            }

            $experience_id = absint($reservation['experience_id'] ?? 0);
            $slot_id = absint($reservation['slot_id'] ?? 0);
            $status = $reservation['status'] ?? 'pending';
            $pax = is_array($reservation['pax'] ?? null) ? $reservation['pax'] : [];
            $addons = is_array($reservation['addons'] ?? null) ? $reservation['addons'] : [];
            $meta = is_array($reservation['meta'] ?? null) ? $reservation['meta'] : [];

            $experience_title = $experience_id ? get_the_title($experience_id) : __('Esperienza rimossa', 'fp-experiences');
            $slot = $slot_id ? \FP_Exp\Booking\Slots::get_slot($slot_id) : null;

            $color = $status_colors[$status] ?? '#64748b';
            $label = $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status));

            echo '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;background:#f8fafc;">';

            // Status badge
            echo '<div style="margin-bottom:8px;">';
            echo '<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;color:#fff;background:' . esc_attr($color) . ';">';
            echo esc_html($label);
            echo '</span>';
            echo ' <span style="color:#94a3b8;font-size:12px;">#' . esc_html((string) $rid) . '</span>';
            echo '</div>';

            // Experience title
            echo '<p style="margin:0 0 6px;font-weight:600;">';
            if ($experience_id) {
                echo '<a href="' . esc_url(get_edit_post_link($experience_id) ?: '') . '" style="color:#0b7285;text-decoration:none;">';
                echo esc_html($experience_title);
                echo '</a>';
            } else {
                echo esc_html($experience_title);
            }
            echo '</p>';

            // Slot date/time
            if ($slot) {
                $start_utc = $slot['start_datetime'] ?? '';
                $end_utc = $slot['end_datetime'] ?? '';

                if ($start_utc) {
                    $formatted = $this->format_datetime_for_display($start_utc);
                    echo '<p style="margin:0 0 4px;font-size:13px;color:#334155;">';
                    echo '<strong>' . esc_html__('Data:', 'fp-experiences') . '</strong> ' . esc_html($formatted);
                    echo '</p>';
                }

                if ($end_utc && $start_utc !== $end_utc) {
                    try {
                        $end_dt = new DateTimeImmutable($end_utc, new DateTimeZone('UTC'));
                        $local_end = $end_dt->setTimezone(wp_timezone());
                        $time_format = get_option('time_format', 'H:i');
                        echo '<p style="margin:0 0 4px;font-size:13px;color:#334155;">';
                        echo '<strong>' . esc_html__('Fine:', 'fp-experiences') . '</strong> ' . esc_html(wp_date($time_format, $local_end->getTimestamp()));
                        echo '</p>';
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }

            // Meeting point
            $meeting_point = \FP_Exp\MeetingPoints\Repository::get_primary_summary_for_experience($experience_id);
            if ($meeting_point) {
                echo '<p style="margin:0 0 4px;font-size:13px;color:#334155;">';
                echo '<strong>' . esc_html__('Punto ritrovo:', 'fp-experiences') . '</strong> ' . esc_html($meeting_point);
                echo '</p>';
            }

            // Tickets
            if ($pax) {
                $ticket_labels = $this->get_ticket_labels_for_experience($experience_id);
                echo '<p style="margin:8px 0 4px;font-size:13px;font-weight:600;color:#334155;">' . esc_html__('Partecipanti:', 'fp-experiences') . '</p>';
                echo '<ul style="margin:0 0 4px 16px;padding:0;font-size:13px;color:#475569;">';
                foreach ($pax as $type => $qty) {
                    $qty = absint($qty);
                    if ($qty <= 0) {
                        continue;
                    }
                    $type_label = $ticket_labels[sanitize_key($type)] ?? ucfirst(str_replace('_', ' ', $type));
                    echo '<li>' . esc_html($type_label) . ': <strong>' . esc_html((string) $qty) . '</strong></li>';
                }
                echo '</ul>';
            }

            // Addons
            if ($addons) {
                $addon_labels = $this->get_addon_labels_for_experience($experience_id);
                echo '<p style="margin:8px 0 4px;font-size:13px;font-weight:600;color:#334155;">' . esc_html__('Extra:', 'fp-experiences') . '</p>';
                echo '<ul style="margin:0 0 4px 16px;padding:0;font-size:13px;color:#475569;">';
                foreach ($addons as $key => $addon) {
                    $qty = absint(is_array($addon) ? ($addon['quantity'] ?? 0) : $addon);
                    if ($qty <= 0) {
                        continue;
                    }
                    $addon_label = $addon_labels[sanitize_key((string) $key)] ?? ucfirst(str_replace('_', ' ', (string) $key));
                    echo '<li>' . esc_html($addon_label) . ': <strong>' . esc_html((string) $qty) . '</strong></li>';
                }
                echo '</ul>';
            }

            // Total
            echo '<p style="margin:8px 0 4px;font-size:13px;color:#334155;">';
            echo '<strong>' . esc_html__('Totale:', 'fp-experiences') . '</strong> ';
            echo wp_kses_post(wc_price((float) ($reservation['total_gross'] ?? 0)));
            echo '</p>';

            // Contact from meta (RTB reservations store contact in meta)
            $contact = $meta['contact'] ?? null;
            if (is_array($contact) && ! empty($contact['email'])) {
                echo '<p style="margin:8px 0 2px;font-size:12px;font-weight:600;color:#64748b;">' . esc_html__('Contatto:', 'fp-experiences') . '</p>';
                echo '<p style="margin:0 0 2px;font-size:12px;color:#475569;">';
                if (! empty($contact['name'])) {
                    echo esc_html($contact['name']) . '<br>';
                }
                echo '<a href="mailto:' . esc_attr($contact['email']) . '">' . esc_html($contact['email']) . '</a>';
                if (! empty($contact['phone'])) {
                    echo '<br>' . esc_html($contact['phone']);
                }
                echo '</p>';
            }

            // Special requests
            $special = $order->get_meta('_fp_exp_special_requests');
            if ($special) {
                echo '<div style="margin-top:8px;padding:8px;background:#fef3c7;border-radius:4px;font-size:12px;color:#78350f;">';
                echo '<strong>' . esc_html__('Richieste speciali:', 'fp-experiences') . '</strong><br>';
                echo esc_html($special);
                echo '</div>';
            }

            echo '</div>';
        }
    }

    /**
     * @return array<string, string>
     */
    private function get_ticket_labels_for_experience(int $experience_id): array
    {
        $meta = get_post_meta($experience_id, '_fp_ticket_types', true);

        if (! is_array($meta)) {
            return [];
        }

        $labels = [];
        foreach ($meta as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = sanitize_key((string) ($entry['key'] ?? $entry['slug'] ?? $entry['id'] ?? ''));
            if ($key) {
                $labels[$key] = sanitize_text_field((string) ($entry['label'] ?? $entry['name'] ?? ucfirst($key)));
            }
        }
        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function get_addon_labels_for_experience(int $experience_id): array
    {
        $meta = get_post_meta($experience_id, '_fp_addons', true);

        if (! is_array($meta)) {
            return [];
        }

        $labels = [];
        foreach ($meta as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = sanitize_key((string) ($entry['key'] ?? $entry['slug'] ?? $entry['id'] ?? ''));
            if ($key) {
                $labels[$key] = sanitize_text_field((string) ($entry['label'] ?? $entry['name'] ?? ucfirst($key)));
            }
        }
        return $labels;
    }

    /**
     * Disable WooCommerce emails for orders containing experiences
     * FP-Experiences has its own email system with customized templates
     *
     * @param bool $enabled Whether the email is enabled
     * @param \WC_Order|null $order The order object
     * @return bool
     */
    public function maybe_disable_wc_email(bool $enabled, $order): bool
    {
        if (! $enabled || ! $order instanceof \WC_Order) {
            return $enabled;
        }

        if ('fp-exp' === $order->get_created_via() || 'fp-exp-rtb' === $order->get_created_via()) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_fp_exp_item_type')
                || $item->get_meta('experience_id')
                || $item->get_meta('fp_exp_experience_id')
            ) {
                return false;
            }
        }

        return $enabled;
    }

    /**
     * Show informative notice on thank you page for RTB orders
     *
     * @param int $order_id The order ID
     */
    public function show_rtb_thankyou_notice(int $order_id): void
    {
        if (! $order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        // Check if this is an RTB order
        $is_rtb = $order->get_meta('_fp_exp_rtb_mode');
        $created_via = $order->get_created_via();

        if (! $is_rtb && $created_via !== 'fp-exp-rtb') {
            return;
        }

        // Get reservation status
        $reservation_id = $order->get_meta('_fp_exp_reservation_id');
        $status_message = '';

        if ($reservation_id) {
            $reservation = \FP_Exp\Booking\Reservations::get((int) $reservation_id);
            if ($reservation) {
                $status = $reservation['status'] ?? '';
                switch ($status) {
                    case \FP_Exp\Booking\Reservations::STATUS_PENDING_REQUEST:
                        $status_message = __('In attesa di conferma dal nostro team.', 'fp-experiences');
                        break;
                    case \FP_Exp\Booking\Reservations::STATUS_APPROVED_PENDING_PAYMENT:
                        $status_message = __('Approvata! Completa il pagamento per confermare.', 'fp-experiences');
                        break;
                    case \FP_Exp\Booking\Reservations::STATUS_APPROVED_CONFIRMED:
                        $status_message = __('Confermata! Ti aspettiamo.', 'fp-experiences');
                        break;
                }
            }
        }

        echo '<div class="woocommerce-info fp-exp-rtb-notice" style="margin-bottom: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #0073aa;">';
        echo '<strong>' . esc_html__('Richiesta di prenotazione', 'fp-experiences') . '</strong><br>';
        echo esc_html__('La tua richiesta è stata ricevuta. Il nostro team la esaminerà e ti contatterà per confermare la disponibilità.', 'fp-experiences');
        if ($status_message) {
            echo '<br><em>' . esc_html($status_message) . '</em>';
        }
        echo '</div>';
    }

    /**
     * Customize order totals display for RTB orders or experience orders with 0 total
     *
     * @param array $total_rows Order total rows
     * @param \WC_Order $order The order object
     * @param string $tax_display Tax display mode
     * @return array
     */
    public function customize_rtb_order_totals(array $total_rows, $order, string $tax_display): array
    {
        if (! $order) {
            return $total_rows;
        }

        // Check if order contains experience items
        $has_experience = false;
        $experience_id = 0;
        foreach ($order->get_items() as $item) {
            $exp_id = $item->get_meta('fp_exp_experience_id');
            if ($exp_id) {
                $has_experience = true;
                $experience_id = (int) $exp_id;
                break;
            }
        }

        // If not an experience order, return unchanged
        if (! $has_experience) {
            return $total_rows;
        }

        // Check if this is an RTB order (via meta or created_via)
        $is_rtb = $order->get_meta('_fp_exp_rtb_mode');
        $created_via = $order->get_created_via();
        $is_rtb_order = $is_rtb || $created_via === 'fp-exp-rtb';

        // Also check if experience uses RTB globally
        if (! $is_rtb_order && $experience_id > 0) {
            $is_rtb_order = Helpers::experience_uses_rtb($experience_id);
        }

        // Get total from reservation meta if available
        $reservation_id = $order->get_meta('_fp_exp_reservation_id');
        $expected_total = 0;

        if ($reservation_id) {
            $reservation = \FP_Exp\Booking\Reservations::get((int) $reservation_id);
            if ($reservation && isset($reservation['total_gross'])) {
                $expected_total = (float) $reservation['total_gross'];
            }
        }

        // If order total is 0, show appropriate message
        $order_total = (float) $order->get_total();

        if ($order_total <= 0 && $expected_total > 0) {
            // Modify the total row to show expected amount
            if (isset($total_rows['order_total'])) {
                $total_rows['order_total']['value'] = sprintf(
                    '<span style="color: #666;">%s</span>',
                    sprintf(
                        /* translators: %s: expected total amount */
                        __('Da pagare dopo conferma: %s', 'fp-experiences'),
                        wc_price($expected_total)
                    )
                );
            }
        } elseif ($order_total <= 0) {
            // No expected total, just show pending message
            if (isset($total_rows['order_total'])) {
                $total_rows['order_total']['value'] = '<span style="color: #666;">' . 
                    esc_html__('In attesa di conferma', 'fp-experiences') . '</span>';
            }
        }

        return $total_rows;
    }

    /**
     * Hide technical meta keys from order item display (admin)
     * These are internal identifiers that shouldn't be shown to customers
     *
     * @param array $hidden_meta Array of meta keys to hide
     * @return array
     */
    public function hide_technical_order_meta(array $hidden_meta): array
    {
        $fp_hidden_keys = $this->get_hidden_meta_keys();

        return array_merge($hidden_meta, $fp_hidden_keys);
    }

    /**
     * Filter order item meta for frontend display (thank you page, order details)
     * Removes technical meta and formats labels
     *
     * @param array $formatted_meta Array of formatted meta data
     * @param \WC_Order_Item $item The order item
     * @return array
     */
    public function filter_order_item_meta_frontend(array $formatted_meta, $item): array
    {
        $hidden_keys = $this->get_hidden_meta_keys();
        
        // Labels for meta keys that should be shown with nice names
        $key_labels = [
            'fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            'fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
            '_fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            '_fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
        ];

        foreach ($formatted_meta as $meta_id => $meta) {
            $key = $meta->key ?? '';
            
            // Remove hidden meta
            if (in_array($key, $hidden_keys, true)) {
                unset($formatted_meta[$meta_id]);
                continue;
            }
            
            // Format display key with nice label
            if (isset($key_labels[$key])) {
                $meta->display_key = $key_labels[$key];
            }
        }

        return $formatted_meta;
    }

    /**
     * Get list of meta keys that should be hidden from display
     *
     * @return array
     */
    private function get_hidden_meta_keys(): array
    {
        return [
            // With fp_exp_ prefix
            'fp_exp_experience_id',
            'fp_exp_experience_title',
            'fp_exp_slot_id',
            'fp_exp_slot_start',
            'fp_exp_slot_end',
            'fp_exp_item',
            'fp_exp_tickets',
            'fp_exp_addons',
            // With _fp_exp_ prefix
            '_fp_exp_experience_id',
            '_fp_exp_experience_title',
            '_fp_exp_slot_id',
            '_fp_exp_slot_start',
            '_fp_exp_slot_end',
            '_fp_exp_item',
            '_fp_exp_item_type',
            '_fp_exp_tickets',
            '_fp_exp_addons',
            // Without prefix (legacy RTB orders)
            'experience_id',
            'experience_title',
            'slot_id',
            'slot_start',
            'slot_end',
            'tickets',
            'addons',
            // With underscore prefix only (new RTB orders)
            '_experience_id',
            '_experience_title',
            '_slot_id',
            '_slot_start',
            '_slot_end',
            '_tickets',
            '_addons',
            // WooCommerce internal
            '_reduced_stock',
        ];
    }

    /**
     * Format order item meta keys with human-readable labels
     *
     * @param string $display_key The meta key to display
     * @param \WC_Meta_Data $meta The meta data object
     * @param \WC_Order_Item $item The order item
     * @return string
     */
    public function format_order_item_meta_key(string $display_key, $meta, $item): string
    {
        $key_labels = [
            'fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            'fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
            '_fp_exp_slot_start' => __('Data e ora inizio', 'fp-experiences'),
            '_fp_exp_slot_end' => __('Data e ora fine', 'fp-experiences'),
        ];

        $meta_key = $meta->key ?? $display_key;

        return $key_labels[$meta_key] ?? $display_key;
    }

    /**
     * Set dynamic price for experience items in cart
     * This is CRITICAL - without this, the cart total will be 0
     */
    public function set_cart_item_price($cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Only for experience items
            if (empty($cart_item['fp_exp_item'])) {
                continue;
            }

            $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
            
            if ($experience_id <= 0) {
                continue;
            }

            // Get experience price
            $exp_price = get_post_meta($experience_id, '_fp_price', true);
            
            if (!is_numeric($exp_price) || $exp_price <= 0) {
                error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price: ' . $exp_price);
                continue;
            }

            // Set price per person
            // _fp_price è il prezzo per persona
            // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
            $price_per_person = (float) $exp_price;

            // Set the price per person on the product
            $cart_item['data']->set_price($price_per_person);
            
            // Get quantity for logging
            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
            $total = $price_per_person * $quantity;
            
            error_log('[FP-EXP-WC] ✅ Set price for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person x ' . $quantity . ' = ' . $total . ' EUR total');
        }
    }

    /**
     * Set price when item is added to cart or loaded from session
     * This ensures Store API (WooCommerce Blocks) gets correct price
     * 
     * @param array $cart_item Cart item data
     * @param mixed $session_values Session data or cart item key
     * @return array Modified cart item
     */
    public function set_price_on_add_to_cart(array $cart_item, $session_values = null): array
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $cart_item;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id <= 0) {
            return $cart_item;
        }

        // Get experience price
        $exp_price = get_post_meta($experience_id, '_fp_price', true);
        
        if (!is_numeric($exp_price) || $exp_price <= 0) {
            error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price on add_to_cart: ' . $exp_price);
            return $cart_item;
        }

        // Set price per person
        // _fp_price è il prezzo per persona
        // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
        $price_per_person = (float) $exp_price;

        // Set the price per person on the product
        if (isset($cart_item['data']) && is_object($cart_item['data'])) {
            $cart_item['data']->set_price($price_per_person);
            error_log('[FP-EXP-WC-STOREAPI] ✅ Set price on add_to_cart for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person');
        }

        return $cart_item;
    }

    /**
     * Use experience featured image instead of virtual product placeholder
     * 
     * @param string $thumbnail Product thumbnail HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified thumbnail HTML
     */
    public function customize_cart_item_thumbnail(string $thumbnail, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $thumbnail;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id <= 0) {
            return $thumbnail;
        }

        // Get experience featured image
        if (!has_post_thumbnail($experience_id)) {
            return $thumbnail;
        }

        // Get the thumbnail with appropriate size
        $image = get_the_post_thumbnail(
            $experience_id,
            'woocommerce_thumbnail',
            [
                'class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail',
                'alt' => get_the_title($experience_id),
            ]
        );

        return $image ?: $thumbnail;
    }

    /**
     * Customize cart item name to show experience title
     */
    public function customize_cart_item_name(string $name, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $name;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id > 0) {
            return get_the_title($experience_id);
        }

        return $name;
    }

    /**
     * Customize cart item price to show experience price
     */
    public function customize_cart_item_price(string $price, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $price;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id > 0) {
            $exp_price = get_post_meta($experience_id, '_fp_price', true);
            
            if (is_numeric($exp_price) && $exp_price > 0) {
                return wc_price($exp_price);
            }
        }

        return $price;
    }

    /**
     * Customize order item name
     */
    public function customize_order_item_name(string $name, $item): string
    {
        $experience_id = absint($item->get_meta('fp_exp_experience_id'));
        
        if ($experience_id > 0) {
            return get_the_title($experience_id);
        }

        return $name;
    }

    /**
     * Display custom cart item data in cart/checkout
     */
    public function display_cart_item_data(array $item_data, array $cart_item): array
    {
        // Check if this is an experience item
        if (empty($cart_item['fp_exp_item'])) {
            return $item_data;
        }

        // Display slot date/time (convert from UTC to local timezone)
        if (!empty($cart_item['fp_exp_slot_start'])) {
            $slot_start_utc = $cart_item['fp_exp_slot_start'];
            $formatted_time = $this->format_datetime_for_display($slot_start_utc);
            
            $item_data[] = [
                'key' => __('Data e ora', 'fp-experiences'),
                'value' => $formatted_time,
            ];
        }

        // Display tickets
        if (!empty($cart_item['fp_exp_tickets']) && is_array($cart_item['fp_exp_tickets'])) {
            foreach ($cart_item['fp_exp_tickets'] as $type => $qty) {
                if ($qty > 0) {
                    $item_data[] = [
                        'key' => ucfirst(sanitize_text_field($type)),
                        'value' => absint($qty),
                    ];
                }
            }
        }

        return $item_data;
    }

    /**
     * Save experience meta to order item when order is created
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order): void
    {
        // Check if this is an experience item
        if (empty($values['fp_exp_item'])) {
            return;
        }

        // Save all fp_exp_* meta
        $meta_keys = [
            'fp_exp_experience_id',
            'fp_exp_slot_id',
            'fp_exp_slot_start',
            'fp_exp_slot_end',
            'fp_exp_tickets',
            'fp_exp_addons',
        ];

        foreach ($meta_keys as $key) {
            if (isset($values[$key])) {
                $item->add_meta_data($key, $values[$key], true);
            }
        }

        error_log('[FP-EXP-WC] Saved order item meta for experience: ' . ($values['fp_exp_experience_id'] ?? 'unknown'));
    }

    /**
     * Format order item meta value with timezone conversion
     * 
     * @param mixed $value Meta value
     * @param object $meta Meta object
     * @param object $item Order item object
     * @return string Formatted value
     */
    public function format_order_item_meta_value($value, $meta, $item)
    {
        // Only process slot_start and slot_end meta keys
        if (!in_array($meta->key, ['fp_exp_slot_start', 'slot_start', 'fp_exp_slot_end', 'slot_end'], true)) {
            return $value;
        }

        // Convert from UTC to local timezone
        return $this->format_datetime_for_display((string) $value);
    }

    /**
     * Format datetime string from UTC to local timezone for display
     * 
     * @param string $datetime_utc Datetime string in UTC (format: Y-m-d H:i:s)
     * @return string Formatted datetime in local timezone
     */
    private function format_datetime_for_display(string $datetime_utc): string
    {
        if (empty($datetime_utc)) {
            return '';
        }

        try {
            // Parse UTC datetime
            $utc_datetime = new DateTimeImmutable($datetime_utc, new DateTimeZone('UTC'));
            
            // Convert to local timezone
            $timezone = wp_timezone();
            $local_datetime = $utc_datetime->setTimezone($timezone);
            
            // Format using WordPress date/time format settings
            $date_format = get_option('date_format', 'F j, Y');
            $time_format = get_option('time_format', 'H:i');
            
            return wp_date($date_format . ' ' . $time_format, $local_datetime->getTimestamp());
        } catch (\Exception $e) {
            // Fallback: return original value if conversion fails
            return $datetime_utc;
        }
    }
}

