<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Booking\Orders;
use FP_Exp\Booking\Pricing;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use WC_Order;
use WP_Error;

use function absint;
use function add_action;
use function add_query_arg;
use function admin_url;
use function array_filter;
use function check_admin_referer;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function get_option;
use function get_posts;
use function get_the_title;
use function is_array;
use function number_format_i18n;
use function rest_url;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function selected;
use function submit_button;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_unslash;
use function wp_kses_post;

final class CalendarAdmin
{
    private Orders $orders;

    public function __construct(Orders $orders)
    {
        $this->orders = $orders;
    }

    public function register_hooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void
    {
        $screen = get_current_screen();
        if (! $screen || 'fp-exp-dashboard_page_fp_exp_calendar' !== $screen->id) {
            return;
        }

        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            Helpers::asset_version('assets/css/admin.css')
        );

        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/js/admin.js',
            ['wp-api-fetch', 'wp-i18n'],
            Helpers::asset_version('assets/js/admin.js'),
            true
        );

        $bootstrap = [
            'endpoints' => [
                'slots' => rest_url('fp-exp/v1/calendar/slots'),
                'move' => rest_url('fp-exp/v1/calendar/slot'),
                'capacity' => rest_url('fp-exp/v1/calendar/slot/capacity'),
            ],
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'month' => esc_html__('Month', 'fp-experiences'),
                'week' => esc_html__('Week', 'fp-experiences'),
                'day' => esc_html__('Day', 'fp-experiences'),
                'previous' => esc_html__('Previous', 'fp-experiences'),
                'next' => esc_html__('Next', 'fp-experiences'),
                'noSlots' => esc_html__('No slots scheduled for this period.', 'fp-experiences'),
                'capacityPrompt' => esc_html__('New total capacity for this slot', 'fp-experiences'),
                'perTypePrompt' => esc_html__('Optional capacity override for %s (leave blank to keep current)', 'fp-experiences'),
                'moveConfirm' => esc_html__('Move slot to %s at %s?', 'fp-experiences'),
                'updateSuccess' => esc_html__('Slot updated successfully.', 'fp-experiences'),
                'updateError' => esc_html__('Unable to update the slot. Please try again.', 'fp-experiences'),
                'seatsAvailable' => esc_html__('seats available', 'fp-experiences'),
                'bookedLabel' => esc_html__('booked', 'fp-experiences'),
                'untitledExperience' => esc_html__('Untitled experience', 'fp-experiences'),
                'loadError' => esc_html__('Unable to load the calendar. Please try again.', 'fp-experiences'),
            ],
        ];

        wp_localize_script('fp-exp-admin', 'fpExpCalendar', $bootstrap);
    }

    public function render_page(): void
    {
        if (! Helpers::can_operate_fp()) {
            wp_die(esc_html__('You do not have permission to manage FP Experiences bookings.', 'fp-experiences'));
        }

        $active_tab = isset($_GET['view']) ? sanitize_text_field((string) wp_unslash($_GET['view'])) : 'calendar';
        $message = '';
        $error = '';

        if ('manual' === $active_tab && isset($_POST['fp_exp_manual_booking'])) {
            $result = $this->handle_manual_booking();

            if ($result instanceof WP_Error) {
                $error = $result->get_error_message();
            } elseif ($result instanceof WC_Order) {
                $payment_link = $result->get_checkout_payment_url(true);
                $message = sprintf(
                    /* translators: %s payment link URL. */
                    esc_html__('Manual booking created. Share the payment link: %s', 'fp-experiences'),
                    '<a href="' . esc_url($payment_link) . '" target="_blank" rel="noopener">' . esc_html($payment_link) . '</a>'
                );
            }
        }

        echo '<div class="wrap fp-exp-calendar-admin">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<h1>' . esc_html__('FP Experiences — Operations', 'fp-experiences') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = [
            'calendar' => esc_html__('Calendar', 'fp-experiences'),
            'manual' => esc_html__('Manual Booking', 'fp-experiences'),
        ];
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg([
                'page' => 'fp-exp-calendar',
                'view' => $slug,
            ], admin_url('admin.php'));
            $classes = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_attr($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if ($message) {
            echo '<div class="notice notice-success"><p>' . wp_kses_post($message) . '</p></div>';
        }

        if ($error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        if ('manual' === $active_tab) {
            $this->render_manual_form();
        } else {
            $this->render_calendar();
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_calendar(): void
    {
        $bootstrap = [
            'range' => [
                'start' => gmdate('Y-m-01'),
            ],
        ];

        echo '<div id="fp-exp-calendar-app" class="fp-exp-calendar" data-loading-text="' . esc_attr__('Loading…', 'fp-experiences') . '" data-bootstrap="' . esc_attr(wp_json_encode($bootstrap)) . '">';
        echo '<div class="fp-exp-calendar__loading" role="status">' . esc_html__('Loading calendar…', 'fp-experiences') . '</div>';
        echo '<div class="fp-exp-calendar__body" data-calendar-content hidden></div>';
        echo '<div class="fp-exp-calendar__feedback" data-calendar-error hidden></div>';
        echo '<noscript>';
        $reservations = Reservations::upcoming(20);
        if ($reservations) {
            echo '<p>' . esc_html__('Upcoming reservations:', 'fp-experiences') . '</p>';
            echo '<ul>';
            foreach ($reservations as $reservation) {
                $experience_title = get_the_title((int) $reservation['experience_id']);
                $start = isset($reservation['start_datetime']) ? sanitize_text_field((string) $reservation['start_datetime']) : '';
                $total_guests = 0;
                if (is_array($reservation['pax'])) {
                    foreach ($reservation['pax'] as $qty) {
                        $total_guests += absint($qty);
                    }
                }

                $line = sprintf(
                    '#%1$d · %2$s · %3$s · %4$d %5$s',
                    (int) $reservation['id'],
                    $experience_title ?: esc_html__('Untitled', 'fp-experiences'),
                    $start,
                    $total_guests,
                    esc_html__('guests', 'fp-experiences')
                );

                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('No upcoming reservations found.', 'fp-experiences') . '</p>';
        }
        echo '</noscript>';
        echo '</div>';
    }

    private function render_manual_form(): void
    {
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ]);

        $selected_experience = isset($_GET['experience_id']) ? absint((string) $_GET['experience_id']) : 0;
        if ($selected_experience <= 0 && $experiences) {
            $selected_experience = (int) $experiences[0]->ID;
        }

        $slots = $selected_experience ? Slots::get_upcoming_for_experience($selected_experience, 40) : [];
        $ticket_config = Pricing::get_ticket_types($selected_experience);
        $addon_config = Pricing::get_addons($selected_experience);

        $posted_tickets = [];
        if (isset($_POST['tickets']) && is_array($_POST['tickets'])) {
            foreach (wp_unslash($_POST['tickets']) as $slug => $quantity) {
                $slug_key = sanitize_key((string) $slug);
                if ('' === $slug_key) {
                    continue;
                }
                $posted_tickets[$slug_key] = absint((string) $quantity);
            }
        }

        $posted_addons = [];
        if (isset($_POST['addons']) && is_array($_POST['addons'])) {
            foreach (wp_unslash($_POST['addons']) as $slug => $quantity) {
                $slug_key = sanitize_key((string) $slug);
                if ('' === $slug_key) {
                    continue;
                }
                $posted_addons[$slug_key] = $quantity;
            }
        }

        $contact = isset($_POST['contact']) && is_array($_POST['contact']) ? array_map('sanitize_text_field', wp_unslash($_POST['contact'])) : [];

        echo '<form method="post" action="">';
        wp_nonce_field('fp_exp_manual_booking', 'fp_exp_manual_booking_nonce');
        echo '<input type="hidden" name="fp_exp_manual_booking" value="1" />';

        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="fp-exp-experience">' . esc_html__('Experience', 'fp-experiences') . '</label></th><td>';
        echo '<select id="fp-exp-experience" name="experience_id" onchange="this.form.submit()">';
        foreach ($experiences as $experience) {
            echo '<option value="' . esc_attr((string) $experience->ID) . '" ' . selected($selected_experience, $experience->ID, false) . '>' . esc_html($experience->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Selecting a different experience reloads available slots.', 'fp-experiences') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="fp-exp-slot">' . esc_html__('Slot', 'fp-experiences') . '</label></th><td>';
        $selected_slot_id = isset($_POST['slot_id']) ? absint((string) $_POST['slot_id']) : 0;

        if ($slots) {
            if ($selected_slot_id <= 0 && isset($slots[0]['id'])) {
                $selected_slot_id = (int) $slots[0]['id'];
            }

            echo '<select id="fp-exp-slot" name="slot_id">';
            foreach ($slots as $slot) {
                $label = $slot['start_datetime'] ?? '';
                echo '<option value="' . esc_attr((string) $slot['id']) . '" ' . selected($selected_slot_id, $slot['id'] ?? 0, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<p>' . esc_html__('No upcoming slots for this experience.', 'fp-experiences') . '</p>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label>' . esc_html__('Tickets', 'fp-experiences') . '</label></th><td>';
        if ($ticket_config) {
            foreach ($ticket_config as $slug => $ticket) {
                $label = $ticket['label'] ?? $slug;
                $max = isset($ticket['max']) ? absint((string) $ticket['max']) : 0;
                $value = $posted_tickets[$slug] ?? 0;
                $max_attr = $max > 0 ? ' max="' . esc_attr((string) $max) . '"' : '';
                echo '<p><label>' . esc_html($label) . ' <input type="number" min="0"' . $max_attr . ' name="tickets[' . esc_attr($slug) . ']" value="' . esc_attr((string) $value) . '" class="small-text" /></label></p>';
            }
        } else {
            echo '<p>' . esc_html__('Configure ticket types for this experience to enable manual bookings.', 'fp-experiences') . '</p>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label>' . esc_html__('Extra', 'fp-experiences') . '</label></th><td>';
        if ($addon_config) {
            $currency = (string) get_option('woocommerce_currency', 'EUR');
            foreach ($addon_config as $slug => $addon) {
                $label = $addon['label'] ?? $slug;
                $price = isset($addon['price']) ? (float) $addon['price'] : 0.0;
                $price_label = $price > 0 ? ' (' . esc_html(sprintf('%s %s', $currency, number_format_i18n($price, 2))) . ')' : '';
                $description = ! empty($addon['description']) ? '<span class="description">' . esc_html($addon['description']) . '</span>' : '';

                if (! empty($addon['allow_multiple'])) {
                    $value = isset($posted_addons[$slug]) ? (float) $posted_addons[$slug] : 0.0;
                    echo '<p class="fp-exp-addon-field"><label>' . esc_html($label) . $price_label . ' <input type="number" min="0" step="1" name="addons[' . esc_attr($slug) . ']" value="' . esc_attr((string) $value) . '" class="small-text" /></label> ' . $description . '</p>';
                } else {
                    $checked = ! empty($posted_addons[$slug]);
                    echo '<p class="fp-exp-addon-field"><label><input type="checkbox" name="addons[' . esc_attr($slug) . ']" value="1" ' . checked($checked, true, false) . ' /> ' . esc_html($label) . $price_label . '</label> ' . $description . '</p>';
                }
            }
        } else {
            echo '<p>' . esc_html__('No extras configured for this experience.', 'fp-experiences') . '</p>';
        }
        echo '</td></tr>';

        $first_name = $contact['first_name'] ?? '';
        $last_name = $contact['last_name'] ?? '';
        echo '<tr><th scope="row"><label for="fp-exp-first-name">' . esc_html__('Customer name', 'fp-experiences') . '</label></th><td>';
        echo '<input type="text" id="fp-exp-first-name" name="contact[first_name]" placeholder="' . esc_attr__('First name', 'fp-experiences') . '" value="' . esc_attr($first_name) . '" class="regular-text" />';
        echo '<input type="text" name="contact[last_name]" placeholder="' . esc_attr__('Last name', 'fp-experiences') . '" value="' . esc_attr($last_name) . '" class="regular-text" style="margin-left:10px;" />';
        echo '</td></tr>';

        $email = $contact['email'] ?? '';
        echo '<tr><th scope="row"><label for="fp-exp-email">' . esc_html__('Customer email', 'fp-experiences') . '</label></th><td>';
        echo '<input type="email" id="fp-exp-email" name="contact[email]" value="' . esc_attr($email) . '" class="regular-text" />';
        echo '</td></tr>';

        $phone = $contact['phone'] ?? '';
        echo '<tr><th scope="row"><label for="fp-exp-phone">' . esc_html__('Phone', 'fp-experiences') . '</label></th><td>';
        echo '<input type="text" id="fp-exp-phone" name="contact[phone]" value="' . esc_attr($phone) . '" class="regular-text" />';
        echo '</td></tr>';

        echo '</table>';

        if ($slots) {
            submit_button(esc_html__('Create manual booking', 'fp-experiences'));
        }

        echo '</form>';

        $breakdown = $this->preview_breakdown($selected_experience, $selected_slot_id, $posted_tickets, $posted_addons, $slots);
        if ($breakdown) {
            echo '<h3>' . esc_html__('Pricing summary', 'fp-experiences') . '</h3>';
            echo '<table class="widefat fixed">';
            echo '<tbody>';
            echo '<tr><th>' . esc_html__('Subtotal', 'fp-experiences') . '</th><td>' . esc_html(number_format_i18n((float) $breakdown['subtotal'], 2)) . '</td></tr>';
            echo '<tr><th>' . esc_html__('Total', 'fp-experiences') . '</th><td>' . esc_html(number_format_i18n((float) $breakdown['total'], 2)) . '</td></tr>';
            echo '</tbody></table>';
        }
    }

    /**
     * @param array<string, int>            $tickets
     * @param array<string, float|int>      $addons
     * @param array<int, array<string, mixed>> $slots
     *
     * @return array{subtotal:float,total:float,currency?:string}|null
     */
    private function preview_breakdown(int $experience_id, int $slot_id, array $tickets, array $addons, array $slots): ?array
    {
        if ($experience_id <= 0 || empty($tickets) || empty($slots)) {
            return null;
        }

        $slot = null;
        foreach ($slots as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $slot_id) {
                $slot = $candidate;
                break;
            }
        }

        if (null === $slot) {
            $slot = $slots[0];
        }

        if (empty($slot['start_datetime'])) {
            return null;
        }

        return Pricing::calculate_breakdown(
            $experience_id,
            (string) $slot['start_datetime'],
            array_filter($tickets),
            array_filter($addons)
        );
    }

    /**
     * @return WC_Order|WP_Error
     */
    private function handle_manual_booking()
    {
        check_admin_referer('fp_exp_manual_booking', 'fp_exp_manual_booking_nonce');

        if (! Helpers::can_operate_fp()) {
            return new WP_Error('fp_exp_manual_permission', esc_html__('You do not have permission to create manual bookings.', 'fp-experiences'));
        }

        $experience_id = isset($_POST['experience_id']) ? absint((string) $_POST['experience_id']) : 0;
        $slot_id = isset($_POST['slot_id']) ? absint((string) $_POST['slot_id']) : 0;

        if ($experience_id <= 0 || $slot_id <= 0) {
            return new WP_Error('fp_exp_manual_invalid', esc_html__('Select an experience and slot before creating a booking.', 'fp-experiences'));
        }

        $slot = Slots::get_slot($slot_id);

        if (! $slot || (int) $slot['experience_id'] !== $experience_id) {
            return new WP_Error('fp_exp_manual_slot', esc_html__('Selected slot no longer exists for this experience.', 'fp-experiences'));
        }

        $tickets = [];
        if (isset($_POST['tickets']) && is_array($_POST['tickets'])) {
            foreach (wp_unslash($_POST['tickets']) as $slug => $quantity) {
                $slug_key = sanitize_key((string) $slug);
                if ('' === $slug_key) {
                    continue;
                }
                $tickets[$slug_key] = absint((string) $quantity);
            }
        }

        $tickets = array_filter($tickets);

        if (! $tickets) {
            return new WP_Error('fp_exp_manual_tickets', esc_html__('Provide at least one ticket quantity.', 'fp-experiences'));
        }

        $capacity = Slots::check_capacity($slot_id, $tickets);

        if (empty($capacity['allowed'])) {
            $message = ! empty($capacity['message']) ? sanitize_text_field((string) $capacity['message']) : esc_html__('The selected slot cannot accommodate the requested party size.', 'fp-experiences');

            return new WP_Error('fp_exp_manual_capacity', $message);
        }

        $addon_quantities = [];
        $available_addons = Pricing::get_addons($experience_id);

        if (isset($_POST['addons']) && is_array($_POST['addons'])) {
            foreach (wp_unslash($_POST['addons']) as $slug => $quantity) {
                $slug_key = sanitize_key((string) $slug);

                if ('' === $slug_key || ! isset($available_addons[$slug_key])) {
                    continue;
                }

                $addon = $available_addons[$slug_key];

                if (! empty($addon['allow_multiple'])) {
                    $addon_quantities[$slug_key] = max(0.0, (float) $quantity);
                } else {
                    $addon_quantities[$slug_key] = absint((string) $quantity) > 0 ? 1.0 : 0.0;
                }
            }
        }

        $addon_quantities = array_filter($addon_quantities, static fn ($qty) => (float) $qty > 0);

        $breakdown = Pricing::calculate_breakdown(
            $experience_id,
            (string) $slot['start_datetime'],
            $tickets,
            $addon_quantities
        );

        $contact = isset($_POST['contact']) && is_array($_POST['contact']) ? $_POST['contact'] : [];

        $payload = [
            'contact' => [
                'first_name' => sanitize_text_field((string) ($contact['first_name'] ?? '')),
                'last_name' => sanitize_text_field((string) ($contact['last_name'] ?? '')),
                'email' => sanitize_email((string) ($contact['email'] ?? get_option('admin_email'))),
                'phone' => sanitize_text_field((string) ($contact['phone'] ?? '')),
            ],
            'billing' => [],
            'consent' => [
                'marketing' => false,
            ],
        ];

        $cart = [
            'currency' => $breakdown['currency'],
            'items' => [
                [
                    'experience_id' => $experience_id,
                    'title' => get_the_title($experience_id),
                    'slot_id' => $slot_id,
                    'slot_start' => (string) $slot['start_datetime'],
                    'slot_end' => (string) ($slot['end_datetime'] ?? $slot['start_datetime']),
                    'tickets' => $tickets,
                    'addons' => $addon_quantities,
                    'totals' => [
                        'subtotal' => $breakdown['subtotal'],
                        'tax' => 0.0,
                        'total' => $breakdown['total'],
                    ],
                ],
            ],
        ];

        $order = $this->orders->create_order($cart, $payload);

        if ($order instanceof WP_Error) {
            return $order;
        }

        return $order;
    }
}
