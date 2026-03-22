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
use function in_array;
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
use function check_admin_referer;
use function current_user_can;
use function wp_get_referer;
use function wp_safe_redirect;
use function wp_unslash;

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
        add_action('admin_init', [$this, 'handle_reservation_slot_update']);
        add_action('admin_notices', [$this, 'render_reschedule_notice']);
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

        $reservation_ids = \FP_Exp\Booking\Reservations::get_ids_by_order($order_id);

        if (empty($reservation_ids)) {
            $this->render_booking_meta_box_from_item_meta($order);
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

            $reschedule_slots = $this->get_reschedule_slots_for_experience($experience_id, $slot_id);
            if (! empty($reschedule_slots)) {
                $slot_select_id = 'fp-exp-reschedule-slot-' . $rid;
                $day_select_id = 'fp-exp-reschedule-day-' . $rid;
                $calendar_id = 'fp-exp-reschedule-calendar-' . $rid;
                $selected_day = '';
                $day_options = [];
                $month_options = [];
                $slot_options = [];

                foreach ($reschedule_slots as $candidate) {
                    $candidate_id = absint($candidate['id'] ?? 0);
                    $candidate_start = isset($candidate['start_datetime']) ? (string) $candidate['start_datetime'] : '';
                    if ($candidate_id <= 0 || '' === $candidate_start) {
                        continue;
                    }

                    $candidate_day_key = $this->get_slot_local_day_key($candidate_start);
                    if ('' === $candidate_day_key) {
                        continue;
                    }

                    if (! isset($day_options[$candidate_day_key])) {
                        $day_options[$candidate_day_key] = [
                            'label' => $this->format_slot_local_day_label($candidate_start),
                            'count' => 0,
                        ];
                    }
                    $day_options[$candidate_day_key]['count']++;

                    if ($candidate_id === $slot_id) {
                        $selected_day = $candidate_day_key;
                    }

                    $slot_options[] = [
                        'id' => $candidate_id,
                        'day_key' => $candidate_day_key,
                        'label' => $this->format_datetime_for_display($candidate_start),
                        'selected' => $candidate_id === $slot_id,
                    ];
                }

                if (! empty($day_options)) {
                    ksort($day_options);
                    foreach ($day_options as $day_key => $day_data) {
                        $month_key = substr($day_key, 0, 7);
                        if (! isset($month_options[$month_key])) {
                            $month_options[$month_key] = [
                                'label' => $this->format_slot_local_month_label($day_key),
                                'days' => [],
                            ];
                        }
                        $month_options[$month_key]['days'][$day_key] = $day_data;
                    }
                    ksort($month_options);
                }

                if ('' === $selected_day && ! empty($slot_options)) {
                    $selected_day = (string) ($slot_options[0]['day_key'] ?? '');
                }

                echo '<form method="post" style="margin:10px 0 0;padding:8px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;">';
                wp_nonce_field('fp_exp_update_reservation_slot_' . $rid, 'fp_exp_update_reservation_slot_nonce');
                echo '<input type="hidden" name="fp_exp_update_reservation_slot" value="1" />';
                echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $rid) . '" />';
                echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $order_id) . '" />';
                echo '<p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#1f2937;">' . esc_html__('Modifica data prenotazione', 'fp-experiences') . '</p>';

                if (count($day_options) > 1) {
                    echo '<div id="' . esc_attr($calendar_id) . '" style="margin:0 0 10px;padding:8px;background:#ffffff;border:1px solid #dbe4ff;border-radius:6px;">';
                    echo '<div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 8px;">';
                    echo '<button type="button" data-cal-prev style="min-width:28px;">&lsaquo;</button>';
                    echo '<strong data-cal-month style="font-size:12px;color:#1f2937;"></strong>';
                    echo '<button type="button" data-cal-next style="min-width:28px;">&rsaquo;</button>';
                    echo '</div>';
                    echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;">';
                    foreach ($month_options as $month_key => $month_data) {
                        $month_label = (string) ($month_data['label'] ?? $month_key);
                        echo '<div data-cal-month-panel="' . esc_attr($month_key) . '" data-cal-month-label="' . esc_attr($month_label) . '" style="display:contents;">';
                        foreach ($month_data['days'] as $day_key => $day_data) {
                            $day_count = absint($day_data['count'] ?? 0);
                            $day_btn_label = $this->format_slot_local_day_button_label($day_key);
                            $is_active = $day_key === $selected_day;
                            echo '<button type="button" data-cal-day="' . esc_attr($day_key) . '" style="padding:6px 4px;border:1px solid ' . esc_attr($is_active ? '#3b82f6' : '#cbd5e1') . ';background:' . esc_attr($is_active ? '#dbeafe' : '#f8fafc') . ';border-radius:4px;font-size:11px;line-height:1.2;cursor:pointer;">';
                            echo esc_html($day_btn_label);
                            echo '<br><span style="color:#64748b;">' . esc_html((string) max(1, $day_count)) . '</span>';
                            echo '</button>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '<select id="' . esc_attr($day_select_id) . '" style="display:none;">';
                    foreach ($day_options as $day_key => $day_data) {
                        $day_label = (string) ($day_data['label'] ?? $day_key);
                        echo '<option value="' . esc_attr($day_key) . '" ' . selected($selected_day, $day_key, false) . '>' . esc_html($day_label) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                }

                echo '<label for="' . esc_attr($slot_select_id) . '" style="display:block;margin:0 0 4px;font-size:12px;color:#334155;">' . esc_html__('Orario', 'fp-experiences') . '</label>';
                echo '<select id="' . esc_attr($slot_select_id) . '" name="new_slot_id" style="width:100%;margin:0 0 8px;">';
                foreach ($slot_options as $option) {
                    $candidate_id = (int) ($option['id'] ?? 0);
                    $candidate_day_key = (string) ($option['day_key'] ?? '');
                    $candidate_label = (string) ($option['label'] ?? (string) $candidate_id);
                    $is_selected = ! empty($option['selected']);
                    echo '<option value="' . esc_attr((string) $candidate_id) . '" data-day="' . esc_attr($candidate_day_key) . '" ' . ($is_selected ? 'selected' : '') . '>' . esc_html($candidate_label) . '</option>';
                }
                echo '</select>';
                echo '<button type="submit" class="button button-secondary" style="width:100%;">' . esc_html__('Salva nuova data', 'fp-experiences') . '</button>';
                echo '</form>';

                if (count($day_options) > 1) {
                    echo '<script>(function(){';
                    echo 'var calendar=document.getElementById(' . wp_json_encode($calendar_id) . ');';
                    echo 'var daySelect=document.getElementById(' . wp_json_encode($day_select_id) . ');';
                    echo 'var slotSelect=document.getElementById(' . wp_json_encode($slot_select_id) . ');';
                    echo 'if(!calendar||!daySelect||!slotSelect){return;}';
                    echo 'var monthPanels=calendar.querySelectorAll("[data-cal-month-panel]");';
                    echo 'var monthLabel=calendar.querySelector("[data-cal-month]");';
                    echo 'var prevBtn=calendar.querySelector("[data-cal-prev]");';
                    echo 'var nextBtn=calendar.querySelector("[data-cal-next]");';
                    echo 'var dayButtons=calendar.querySelectorAll("[data-cal-day]");';
                    echo 'var monthOrder=[];';
                    echo 'for(var i=0;i<monthPanels.length;i++){var key=monthPanels[i].getAttribute("data-cal-month-panel");if(monthOrder.indexOf(key)===-1){monthOrder.push(key);}}';
                    echo 'var selectedDay=daySelect.value||"";';
                    echo 'var activeMonth=selectedDay?selectedDay.substring(0,7):(monthOrder[0]||"");';
                    echo 'var sync=function(){';
                    echo 'var activeDay=daySelect.value||"";';
                    echo 'var options=slotSelect.options;var firstVisible=-1;';
                    echo 'for(var i=0;i<options.length;i++){';
                    echo 'var option=options[i];var visible=!activeDay||option.getAttribute("data-day")===activeDay;';
                    echo 'option.hidden=!visible;';
                    echo 'option.disabled=!visible;';
                    echo 'if(visible&&firstVisible===-1){firstVisible=i;}';
                    echo '}';
                    echo 'if(slotSelect.selectedIndex<0||slotSelect.options[slotSelect.selectedIndex].disabled){';
                    echo 'if(firstVisible>=0){slotSelect.selectedIndex=firstVisible;}';
                    echo '}';
                    echo '};';
                    echo 'var renderMonth=function(){';
                    echo 'for(var i=0;i<monthPanels.length;i++){';
                    echo 'var panel=monthPanels[i];';
                    echo 'var panelMonth=panel.getAttribute("data-cal-month-panel")||"";';
                    echo 'panel.style.display=(panelMonth===activeMonth)?"contents":"none";';
                    echo '}';
                    echo 'if(monthLabel){';
                    echo 'var activePanel=calendar.querySelector("[data-cal-month-panel=\\""+activeMonth+"\\"]");';
                    echo 'monthLabel.textContent=activePanel?(activePanel.getAttribute("data-cal-month-label")||activeMonth):activeMonth;';
                    echo '}';
                    echo '};';
                    echo 'var setDay=function(dayKey){';
                    echo 'daySelect.value=dayKey;';
                    echo 'for(var i=0;i<dayButtons.length;i++){';
                    echo 'var btn=dayButtons[i];var isActive=(btn.getAttribute("data-cal-day")===dayKey);';
                    echo 'btn.style.borderColor=isActive?"#3b82f6":"#cbd5e1";';
                    echo 'btn.style.background=isActive?"#dbeafe":"#f8fafc";';
                    echo '}';
                    echo 'sync();';
                    echo '};';
                    echo 'for(var i=0;i<dayButtons.length;i++){';
                    echo 'dayButtons[i].addEventListener("click",function(){';
                    echo 'var dayKey=this.getAttribute("data-cal-day")||"";';
                    echo 'if(!dayKey){return;}';
                    echo 'activeMonth=dayKey.substring(0,7);';
                    echo 'renderMonth();setDay(dayKey);';
                    echo '});';
                    echo '}';
                    echo 'if(prevBtn){prevBtn.addEventListener("click",function(){var idx=monthOrder.indexOf(activeMonth);if(idx>0){activeMonth=monthOrder[idx-1];renderMonth();}});}';
                    echo 'if(nextBtn){nextBtn.addEventListener("click",function(){var idx=monthOrder.indexOf(activeMonth);if(idx>-1&&idx<monthOrder.length-1){activeMonth=monthOrder[idx+1];renderMonth();}});}';
                    echo 'renderMonth();setDay(daySelect.value||selectedDay||"");';
                    echo '})();</script>';
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
     * Fallback: show booking details from WooCommerce order item meta
     * when the wp_fp_exp_reservations table has no matching records.
     */
    private function render_booking_meta_box_from_item_meta(\WC_Order $order): void
    {
        $found = false;

        $is_fp_order = 'yes' === $order->get_meta('_fp_exp_isolated_checkout');

        foreach ($order->get_items() as $item) {
            $item_type = $item->get_meta('_fp_exp_item_type');
            $is_experience = 'experience' === $item_type;
            $is_rtb = 'rtb' === $item_type;
            $is_gift = 'yes' === $item->get_meta('gift_redemption');

            $experience_id = absint(
                $item->get_meta('experience_id')
                ?: $item->get_meta('fp_exp_experience_id')
                ?: $item->get_meta('_fp_exp_experience_id')
                ?: $item->get_meta('_experience_id')
                ?: 0
            );

            if (! $is_experience && ! $is_rtb && ! $is_gift && ! $experience_id && ! $is_fp_order) {
                continue;
            }

            $found = true;

            $slot_id = absint(
                $item->get_meta('slot_id')
                ?: $item->get_meta('fp_exp_slot_id')
                ?: $item->get_meta('_slot_id')
                ?: 0
            );
            $slot_start = $item->get_meta('slot_start')
                ?: $item->get_meta('fp_exp_slot_start')
                ?: $item->get_meta('_slot_start')
                ?: '';
            $slot_end = $item->get_meta('slot_end')
                ?: $item->get_meta('fp_exp_slot_end')
                ?: $item->get_meta('_slot_end')
                ?: '';

            if ($is_gift) {
                $tickets = $item->get_meta('gift_quantity');
                $addons = $item->get_meta('gift_addons');
            } elseif ($is_rtb) {
                $tickets = $item->get_meta('_tickets')
                    ?: $item->get_meta('tickets')
                    ?: [];
                $addons = $item->get_meta('_addons')
                    ?: $item->get_meta('addons')
                    ?: [];
            } else {
                $tickets = $item->get_meta('tickets')
                    ?: $item->get_meta('_fp_exp_tickets')
                    ?: $item->get_meta('fp_exp_tickets')
                    ?: [];
                $addons = $item->get_meta('addons')
                    ?: $item->get_meta('_fp_exp_addons')
                    ?: $item->get_meta('fp_exp_addons')
                    ?: [];
            }
            $tickets = is_array($tickets) ? $tickets : [];
            $addons = is_array($addons) ? $addons : [];

            $experience_title = $experience_id
                ? get_the_title($experience_id)
                : ($item->get_meta('experience_title') ?: $item->get_name());

            $order_status = $order->get_status();
            $status = in_array($order_status, ['completed', 'processing'], true) ? 'paid' : 'pending';
            $status_labels = [
                'pending' => __('In attesa pagamento', 'fp-experiences'),
                'paid' => __('Pagata', 'fp-experiences'),
            ];
            $status_colors = ['pending' => '#f59e0b', 'paid' => '#10b981'];

            $color = $status_colors[$status] ?? '#64748b';
            $label = $status_labels[$status] ?? ucfirst($status);

            $source_hint = __('(da meta ordine)', 'fp-experiences');
            if ($is_rtb) {
                $source_hint = __('(RTB — da meta ordine)', 'fp-experiences');
            } elseif ($is_gift) {
                $source_hint = __('(Gift Voucher — da meta ordine)', 'fp-experiences');
            }

            echo '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;background:#f8fafc;">';
            echo '<div style="margin-bottom:4px;font-size:11px;color:#94a3b8;">' . esc_html($source_hint) . '</div>';

            echo '<div style="margin-bottom:8px;">';
            echo '<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;color:#fff;background:' . esc_attr($color) . ';">';
            echo esc_html($label);
            echo '</span>';
            echo '</div>';

            echo '<p style="margin:0 0 6px;font-weight:600;">';
            if ($experience_id) {
                echo '<a href="' . esc_url(get_edit_post_link($experience_id) ?: '') . '" style="color:#0b7285;text-decoration:none;">';
                echo esc_html($experience_title);
                echo '</a>';
            } else {
                echo esc_html($experience_title);
            }
            echo '</p>';

            if ($slot_start) {
                $formatted = $this->format_datetime_for_display($slot_start);
                echo '<p style="margin:0 0 4px;font-size:13px;color:#334155;">';
                echo '<strong>' . esc_html__('Data:', 'fp-experiences') . '</strong> ' . esc_html($formatted);
                echo '</p>';
            }

            if ($slot_end && $slot_end !== $slot_start) {
                try {
                    $end_dt = new \DateTimeImmutable($slot_end, new \DateTimeZone('UTC'));
                    $local_end = $end_dt->setTimezone(wp_timezone());
                    $time_format = get_option('time_format', 'H:i');
                    echo '<p style="margin:0 0 4px;font-size:13px;color:#334155;">';
                    echo '<strong>' . esc_html__('Fine:', 'fp-experiences') . '</strong> ' . esc_html(wp_date($time_format, $local_end->getTimestamp()));
                    echo '</p>';
                } catch (\Exception $e) {
                    // ignore
                }
            }

            if ($tickets) {
                $ticket_labels = $experience_id ? $this->get_ticket_labels_for_experience($experience_id) : [];
                echo '<p style="margin:8px 0 4px;font-size:13px;font-weight:600;color:#334155;">' . esc_html__('Partecipanti:', 'fp-experiences') . '</p>';
                echo '<ul style="margin:0 0 4px 16px;padding:0;font-size:13px;color:#475569;">';
                foreach ($tickets as $type => $qty) {
                    $qty = absint($qty);
                    if ($qty <= 0) {
                        continue;
                    }
                    $type_label = $ticket_labels[sanitize_key($type)] ?? ucfirst(str_replace('_', ' ', $type));
                    echo '<li>' . esc_html($type_label) . ': <strong>' . esc_html((string) $qty) . '</strong></li>';
                }
                echo '</ul>';
            }

            if ($addons && is_array($addons)) {
                $addon_labels = $experience_id ? $this->get_addon_labels_for_experience($experience_id) : [];
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

            $total = (float) $item->get_total();
            if ($total > 0) {
                echo '<p style="margin:8px 0 4px;font-size:13px;color:#334155;">';
                echo '<strong>' . esc_html__('Totale:', 'fp-experiences') . '</strong> ';
                echo wp_kses_post(wc_price($total));
                echo '</p>';
            }

            echo '</div>';
        }

        if (! $found) {
            echo '<p style="color:#64748b;">' . esc_html__('Nessuna prenotazione collegata a questo ordine.', 'fp-experiences') . '</p>';
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

        $created_via = $order->get_created_via();

        // RTB and Gift have dedicated email flows — disable WC emails for those.
        if ('fp-exp-rtb' === $created_via || 'fp-exp-gift' === $created_via) {
            return false;
        }

        // Standard booking orders (fp-exp) now receive BOTH the WC order email
        // AND the plugin's experience-specific email, so we let WC emails through.

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
        $reservation_status = '';

        if ($reservation_id) {
            $reservation = \FP_Exp\Booking\Reservations::get((int) $reservation_id);
            if ($reservation && isset($reservation['total_gross'])) {
                $expected_total = (float) $reservation['total_gross'];
            }
            if ($reservation && isset($reservation['status'])) {
                $reservation_status = (string) $reservation['status'];
            }
        }

        $order_total = (float) $order->get_total();
        $order_status = $order->get_status();
        $is_paid = in_array($order_status, ['processing', 'completed'], true)
            || in_array($reservation_status, ['paid', 'approved_confirmed', 'checked_in'], true);

        // If order is paid or reservation is confirmed, show the real total
        if ($is_paid && $order_total <= 0 && $expected_total > 0 && isset($total_rows['order_total'])) {
            $total_rows['order_total']['value'] = wc_price($expected_total);
            return $total_rows;
        }

        // Only modify display for actual RTB orders that are still pending
        if (! $is_rtb_order || $is_paid) {
            return $total_rows;
        }

        // RTB order with pending status
        if ($order_total <= 0 && $expected_total > 0 && isset($total_rows['order_total'])) {
            $total_rows['order_total']['value'] = sprintf(
                '<span style="color: #666;">%s</span>',
                sprintf(
                    /* translators: %s: expected total amount */
                    __('Da pagare dopo conferma: %s', 'fp-experiences'),
                    wc_price($expected_total)
                )
            );
        } elseif ($order_total <= 0 && isset($total_rows['order_total'])) {
            $total_rows['order_total']['value'] = '<span style="color: #666;">' .
                esc_html__('In attesa di conferma', 'fp-experiences') . '</span>';
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
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price: ' . $exp_price);
                }
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
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-EXP-WC] ✅ Set price for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person x ' . $quantity . ' = ' . $total . ' EUR total');
            }
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price on add_to_cart: ' . $exp_price);
            }
            return $cart_item;
        }

        // Set price per person
        // _fp_price è il prezzo per persona
        // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
        $price_per_person = (float) $exp_price;

        // Set the price per person on the product
        if (isset($cart_item['data']) && is_object($cart_item['data'])) {
            $cart_item['data']->set_price($price_per_person);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-EXP-WC-STOREAPI] ✅ Set price on add_to_cart for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person');
            }
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-EXP-WC] Saved order item meta for experience: ' . ($values['fp_exp_experience_id'] ?? 'unknown'));
        }
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

    /**
     * Handle backend reservation date update from order metabox.
     */
    public function handle_reservation_slot_update(): void
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (! isset($_POST['fp_exp_update_reservation_slot'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (! Helpers::can_operate_fp() || ! current_user_can('manage_woocommerce')) {
            return;
        }

        $reservation_id = isset($_POST['reservation_id']) ? absint((string) wp_unslash($_POST['reservation_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $order_id = isset($_POST['order_id']) ? absint((string) wp_unslash($_POST['order_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $new_slot_id = isset($_POST['new_slot_id']) ? absint((string) wp_unslash($_POST['new_slot_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($reservation_id <= 0 || $order_id <= 0 || $new_slot_id <= 0) {
            $this->redirect_with_reschedule_notice($order_id, 'invalid');
            return;
        }

        check_admin_referer('fp_exp_update_reservation_slot_' . $reservation_id, 'fp_exp_update_reservation_slot_nonce');

        $reservation = \FP_Exp\Booking\Reservations::get($reservation_id);
        if (! $reservation) {
            $this->redirect_with_reschedule_notice($order_id, 'not_found');
            return;
        }

        if (absint($reservation['order_id'] ?? 0) !== $order_id) {
            $this->redirect_with_reschedule_notice($order_id, 'invalid');
            return;
        }

        $current_slot_id = absint($reservation['slot_id'] ?? 0);
        if ($current_slot_id === $new_slot_id) {
            $this->redirect_with_reschedule_notice($order_id, 'noop');
            return;
        }

        $new_slot = \FP_Exp\Booking\Slots::get_slot($new_slot_id);
        if (! $new_slot) {
            $this->redirect_with_reschedule_notice($order_id, 'slot_not_found');
            return;
        }

        $experience_id = absint($reservation['experience_id'] ?? 0);
        if (absint($new_slot['experience_id'] ?? 0) !== $experience_id) {
            $this->redirect_with_reschedule_notice($order_id, 'slot_mismatch');
            return;
        }

        $reservation_status = (string) ($reservation['status'] ?? '');
        if (in_array($reservation_status, [\FP_Exp\Booking\Reservations::STATUS_CANCELLED, \FP_Exp\Booking\Reservations::STATUS_DECLINED], true)) {
            $this->redirect_with_reschedule_notice($order_id, 'status_not_allowed');
            return;
        }

        if (! $this->is_slot_selectable_for_reschedule($new_slot, $current_slot_id)) {
            $this->redirect_with_reschedule_notice($order_id, 'slot_not_allowed');
            return;
        }

        $requested_pax = is_array($reservation['pax'] ?? null) ? $reservation['pax'] : [];
        $capacity_check = \FP_Exp\Booking\Slots::check_capacity($new_slot_id, $requested_pax);
        if (empty($capacity_check['allowed'])) {
            $this->redirect_with_reschedule_notice($order_id, 'capacity');
            return;
        }

        $updated = \FP_Exp\Booking\Reservations::update($reservation_id, [
            'order_id' => absint($reservation['order_id'] ?? 0),
            'experience_id' => $experience_id,
            'slot_id' => $new_slot_id,
            'customer_id' => absint($reservation['customer_id'] ?? 0),
            'status' => (string) ($reservation['status'] ?? 'pending'),
            'pax' => is_array($reservation['pax'] ?? null) ? $reservation['pax'] : [],
            'addons' => is_array($reservation['addons'] ?? null) ? $reservation['addons'] : [],
            'utm' => is_array($reservation['utm'] ?? null) ? $reservation['utm'] : [],
            'meta' => is_array($reservation['meta'] ?? null) ? $reservation['meta'] : [],
            'locale' => (string) ($reservation['locale'] ?? ''),
            'total_gross' => (float) ($reservation['total_gross'] ?? 0),
            'tax_total' => (float) ($reservation['tax_total'] ?? 0),
            'hold_expires_at' => $reservation['hold_expires_at'] ?? null,
        ]);

        if (! $updated) {
            $this->redirect_with_reschedule_notice($order_id, 'save_failed');
            return;
        }

        $order = wc_get_order($order_id);
        if ($order instanceof \WC_Order) {
            $this->sync_order_item_slot_meta($order, $experience_id, $current_slot_id, $new_slot);
        }

        do_action(
            'fp_exp_reservation_rescheduled',
            $reservation_id,
            $order_id,
            $current_slot_id,
            $new_slot_id
        );

        $this->redirect_with_reschedule_notice($order_id, 'success');
    }

    /**
     * Show feedback after reservation date change.
     */
    public function render_reschedule_notice(): void
    {
        $status = isset($_GET['fp_exp_reschedule']) ? sanitize_key((string) $_GET['fp_exp_reschedule']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ('' === $status) {
            return;
        }

        $messages = [
            'success' => [
                'type' => 'success',
                'text' => esc_html__('Data prenotazione aggiornata con successo.', 'fp-experiences'),
            ],
            'noop' => [
                'type' => 'info',
                'text' => esc_html__('La prenotazione era gia impostata sulla data selezionata.', 'fp-experiences'),
            ],
            'capacity' => [
                'type' => 'error',
                'text' => esc_html__('Impossibile spostare la prenotazione: capienza slot insufficiente.', 'fp-experiences'),
            ],
            'slot_not_found' => [
                'type' => 'error',
                'text' => esc_html__('Slot selezionato non trovato.', 'fp-experiences'),
            ],
            'slot_mismatch' => [
                'type' => 'error',
                'text' => esc_html__('Lo slot selezionato non appartiene alla stessa esperienza.', 'fp-experiences'),
            ],
            'slot_not_allowed' => [
                'type' => 'error',
                'text' => esc_html__('Lo slot selezionato non è disponibile per la riprogrammazione.', 'fp-experiences'),
            ],
            'status_not_allowed' => [
                'type' => 'error',
                'text' => esc_html__('La prenotazione non può essere riprogrammata nel suo stato attuale.', 'fp-experiences'),
            ],
            'not_found' => [
                'type' => 'error',
                'text' => esc_html__('Prenotazione non trovata.', 'fp-experiences'),
            ],
            'save_failed' => [
                'type' => 'error',
                'text' => esc_html__('Salvataggio non riuscito. Riprova.', 'fp-experiences'),
            ],
            'invalid' => [
                'type' => 'error',
                'text' => esc_html__('Richiesta non valida.', 'fp-experiences'),
            ],
        ];

        if (! isset($messages[$status])) {
            return;
        }

        $type = $messages[$status]['type'];
        $class = 'notice';
        if ('success' === $type) {
            $class .= ' notice-success';
        } elseif ('info' === $type) {
            $class .= ' notice-info';
        } else {
            $class .= ' notice-error';
        }

        echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($messages[$status]['text']) . '</p></div>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_reschedule_slots_for_experience(int $experience_id, int $current_slot_id): array
    {
        $range_start = \gmdate('Y-m-d H:i:s', time() - (7 * \DAY_IN_SECONDS));
        $range_end = \gmdate('Y-m-d H:i:s', time() + (540 * \DAY_IN_SECONDS));
        $slots = \FP_Exp\Booking\Slots::get_slots_in_range($range_start, $range_end, [
            'experience_id' => $experience_id,
            'statuses' => [
                \FP_Exp\Booking\Slots::STATUS_OPEN,
                \FP_Exp\Booking\Slots::STATUS_CLOSED,
            ],
        ]);

        if ($current_slot_id > 0) {
            $has_current = false;
            foreach ($slots as $slot) {
                if (absint($slot['id'] ?? 0) === $current_slot_id) {
                    $has_current = true;
                    break;
                }
            }

            if (! $has_current) {
                $current_slot = \FP_Exp\Booking\Slots::get_slot($current_slot_id);
                if (is_array($current_slot)) {
                    array_unshift($slots, $current_slot);
                }
            }
        }

        if (count($slots) > 800) {
            $slots = array_slice($slots, 0, 800);
        }

        $filtered_slots = [];
        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }
            if ($this->is_slot_selectable_for_reschedule($slot, $current_slot_id)) {
                $filtered_slots[] = $slot;
            }
        }

        return $filtered_slots;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private function is_slot_selectable_for_reschedule(array $slot, int $current_slot_id): bool
    {
        $slot_id = absint($slot['id'] ?? 0);
        if ($slot_id <= 0) {
            return false;
        }

        if ($slot_id === $current_slot_id) {
            return true;
        }

        $status = sanitize_key((string) ($slot['status'] ?? \FP_Exp\Booking\Slots::STATUS_OPEN));
        if (\FP_Exp\Booking\Slots::STATUS_OPEN !== $status) {
            return false;
        }

        $start_datetime = isset($slot['start_datetime']) ? (string) $slot['start_datetime'] : '';
        if ('' === $start_datetime) {
            return false;
        }

        $timestamp = strtotime($start_datetime . ' UTC');
        if (! is_numeric($timestamp) || (int) $timestamp <= (time() - \MINUTE_IN_SECONDS)) {
            return false;
        }

        return true;
    }

    private function get_slot_local_day_key(string $datetime_utc): string
    {
        if ('' === $datetime_utc) {
            return '';
        }

        try {
            $utc_datetime = new DateTimeImmutable($datetime_utc, new DateTimeZone('UTC'));
            $local_datetime = $utc_datetime->setTimezone(wp_timezone());
            return $local_datetime->format('Y-m-d');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function format_slot_local_day_label(string $datetime_utc): string
    {
        if ('' === $datetime_utc) {
            return '';
        }

        try {
            $utc_datetime = new DateTimeImmutable($datetime_utc, new DateTimeZone('UTC'));
            $local_datetime = $utc_datetime->setTimezone(wp_timezone());
            return wp_date('l j F Y', $local_datetime->getTimestamp());
        } catch (\Throwable $exception) {
            return $datetime_utc;
        }
    }

    private function format_slot_local_month_label(string $day_key): string
    {
        if ('' === $day_key) {
            return '';
        }

        try {
            $local_datetime = new DateTimeImmutable($day_key . ' 00:00:00', wp_timezone());
            return wp_date('F Y', $local_datetime->getTimestamp());
        } catch (\Throwable $exception) {
            return $day_key;
        }
    }

    private function format_slot_local_day_button_label(string $day_key): string
    {
        if ('' === $day_key) {
            return '';
        }

        try {
            $local_datetime = new DateTimeImmutable($day_key . ' 00:00:00', wp_timezone());
            return wp_date('D j', $local_datetime->getTimestamp());
        } catch (\Throwable $exception) {
            return $day_key;
        }
    }

    /**
     * Update WooCommerce order item slot metadata after reservation move.
     *
     * @param array<string, mixed> $new_slot
     */
    private function sync_order_item_slot_meta(\WC_Order $order, int $experience_id, int $previous_slot_id, array $new_slot): void
    {
        $new_slot_id = absint($new_slot['id'] ?? 0);
        $new_start = isset($new_slot['start_datetime']) ? (string) $new_slot['start_datetime'] : '';
        $new_end = isset($new_slot['end_datetime']) ? (string) $new_slot['end_datetime'] : '';

        if ($new_slot_id <= 0 || '' === $new_start || '' === $new_end) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $item_experience_id = absint(
                $item->get_meta('experience_id')
                ?: $item->get_meta('fp_exp_experience_id')
                ?: $item->get_meta('_fp_exp_experience_id')
                ?: $item->get_meta('_experience_id')
                ?: 0
            );

            if ($item_experience_id !== $experience_id) {
                continue;
            }

            $item_slot_id = absint(
                $item->get_meta('slot_id')
                ?: $item->get_meta('fp_exp_slot_id')
                ?: $item->get_meta('_fp_exp_slot_id')
                ?: $item->get_meta('_slot_id')
                ?: 0
            );

            if ($previous_slot_id > 0 && $item_slot_id > 0 && $item_slot_id !== $previous_slot_id) {
                continue;
            }

            $item->update_meta_data('slot_id', $new_slot_id);
            $item->update_meta_data('fp_exp_slot_id', $new_slot_id);
            $item->update_meta_data('_fp_exp_slot_id', $new_slot_id);
            $item->update_meta_data('_slot_id', $new_slot_id);

            $item->update_meta_data('slot_start', $new_start);
            $item->update_meta_data('fp_exp_slot_start', $new_start);
            $item->update_meta_data('_fp_exp_slot_start', $new_start);
            $item->update_meta_data('_slot_start', $new_start);

            $item->update_meta_data('slot_end', $new_end);
            $item->update_meta_data('fp_exp_slot_end', $new_end);
            $item->update_meta_data('_fp_exp_slot_end', $new_end);
            $item->update_meta_data('_slot_end', $new_end);

            $item->save();
        }
    }

    private function redirect_with_reschedule_notice(int $order_id, string $status): void
    {
        $fallback = $order_id > 0
            ? add_query_arg(
                [
                    'post' => $order_id,
                    'action' => 'edit',
                ],
                admin_url('post.php')
            )
            : admin_url('edit.php?post_type=shop_order');

        $referer = wp_get_referer();
        $base = $referer && is_string($referer) ? $referer : $fallback;

        $redirect = add_query_arg(
            [
                'fp_exp_reschedule' => sanitize_key($status),
            ],
            $base
        );

        wp_safe_redirect($redirect);
        exit;
    }
}

