<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use DateTimeZone;
use FP_Exp\Core\Hook\HookableInterface;
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
use function array_map;
use function array_sum;
use function check_admin_referer;
use function checked;
use function count;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_post_type;
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
use function sprintf;
use function strpos;
use function strtotime;
use function submit_button;
use function wp_create_nonce;
use function wp_die;
use function wp_date;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_unslash;
use function wp_kses_post;
use function wp_timezone;
use function current_user_can;

final class CalendarAdmin implements HookableInterface
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
        // Verifica anche il hook e il page parameter per maggiore sicurezza
        $is_calendar_page = (
            false !== strpos($hook, 'fp_exp_calendar') ||
            ($screen && 'fp-exp-dashboard_page_fp_exp_calendar' === $screen->id) ||
            (isset($_GET['page']) && $_GET['page'] === 'fp_exp_calendar')
        );
        
        if (! $is_calendar_page) {
            return;
        }

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);
        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['jquery'],
            Helpers::asset_version($admin_js),
            true
        );

        // Config base per fpExpAdmin
        wp_localize_script('fp-exp-admin', 'fpExpAdmin', [
            'restUrl' => rest_url('fp-exp/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pluginUrl' => FP_EXP_PLUGIN_URL,
            'strings' => [],
        ]);

        // Prepara elenco esperienze per filtro calendario
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 200,
            'orderby' => 'title',
    		'order' => 'ASC',
            'post_status' => ['publish','private'],
            'suppress_filters' => true,
        ]);

        $experience_options = [];
        foreach ($experiences as $post) {
            $experience_options[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title((int) $post->ID),
            ];
        }

        $bootstrap = [
            'endpoints' => [
                'availability' => rest_url('fp-exp/v1/availability'),
                'slots' => rest_url('fp-exp/v1/calendar/slots'),
                'move' => rest_url('fp-exp/v1/calendar/slot'),
                'capacity' => rest_url('fp-exp/v1/calendar/slot/capacity'),
            ],
            'nonce' => wp_create_nonce('wp_rest'),
            'experiences' => $experience_options,
            'has_experiences' => !empty($experience_options),
            'i18n' => [
                'month' => esc_html__('Mese', 'fp-experiences'),
                'week' => esc_html__('Settimana', 'fp-experiences'),
                'day' => esc_html__('Giorno', 'fp-experiences'),
                'previous' => esc_html__('Precedente', 'fp-experiences'),
                'next' => esc_html__('Successivo', 'fp-experiences'),
                'noSlots' => esc_html__('Nessuno slot programmato per questo periodo.', 'fp-experiences'),
                'capacityPrompt' => esc_html__('Nuova capacità totale per questo slot', 'fp-experiences'),
                'perTypePrompt' => esc_html__('Capacità opzionale per %s (lascia vuoto per mantenere corrente)', 'fp-experiences'),
                'moveConfirm' => esc_html__('Spostare lo slot a %s alle %s?', 'fp-experiences'),
                'updateSuccess' => esc_html__('Slot aggiornato con successo.', 'fp-experiences'),
                'updateError' => esc_html__('Impossibile aggiornare lo slot. Riprova.', 'fp-experiences'),
                'seatsAvailable' => esc_html__('posti disponibili', 'fp-experiences'),
                'bookedLabel' => esc_html__('prenotati', 'fp-experiences'),
                'untitledExperience' => esc_html__('Esperienza senza titolo', 'fp-experiences'),
                'loadError' => esc_html__('Impossibile caricare il calendario. Riprova.', 'fp-experiences'),
                'selectExperience' => esc_html__('Seleziona esperienza', 'fp-experiences'),
                'selectExperienceFirst' => esc_html__('Seleziona un\'esperienza per visualizzare la disponibilità', 'fp-experiences'),
                'accessDenied' => esc_html__('Accesso negato. Ricarica la pagina e riprova.', 'fp-experiences'),
                'notFound' => esc_html__('Risorsa non trovata.', 'fp-experiences'),
                'serverError' => esc_html__('Errore del server. Riprova tra qualche minuto.', 'fp-experiences'),
                'listView' => esc_html__('Lista', 'fp-experiences'),
                'calendarView' => esc_html__('Calendario', 'fp-experiences'),
            ],
        ];

        wp_localize_script('fp-exp-admin', 'fpExpCalendar', $bootstrap);
    }

    public function render_page(): void
    {
        if (! Helpers::can_operate_fp()) {
            wp_die(esc_html__('Non hai i permessi per gestire le prenotazioni di FP Experiences.', 'fp-experiences'));
        }

        $active_tab = isset($_GET['view']) ? sanitize_text_field((string) wp_unslash($_GET['view'])) : 'overview';
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
        echo '<div class="fp-exp-admin__layout fp-exp-calendar">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Operazioni', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Operazioni FP Experiences', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Gestisci calendario, disponibilità e prenotazioni manuali da un unico pannello.', 'fp-experiences') . '</p>';
        echo '</header>';
        $this->render_operator_navigation($active_tab);
        echo '<div class="fp-exp-tabs nav-tab-wrapper">';
        $tabs = [
            'overview' => esc_html__('Panoramica Operatore', 'fp-experiences'),
            'calendar' => esc_html__('Calendario', 'fp-experiences'),
            'manual' => esc_html__('Prenotazione Manuale', 'fp-experiences'),
        ];
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg([
                'page' => 'fp_exp_calendar',
                'view' => $slug,
            ], admin_url('admin.php'));
            $classes = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_attr($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';

        if ($message) {
            echo '<div class="notice notice-success"><p>' . wp_kses_post($message) . '</p></div>';
        }

        if ($error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        if ('overview' === $active_tab) {
            $this->render_operator_overview();
        } elseif ('manual' === $active_tab) {
            $this->render_manual_form();
        } else {
            $this->render_calendar();
        }

        echo '</div>';
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

        // Mostra messaggio informativo se non ci sono esperienze pubblicate
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 1,
            'post_status' => ['publish','private'],
            'suppress_filters' => true,
            'fields' => 'ids',
        ]);
        $has_experiences = ! empty($experiences);
        if (! $has_experiences) {
            echo '<div class="fp-exp-calendar__no-experiences">';
            echo '<div class="notice notice-info">';
            echo '<p><strong>' . esc_html__('Nessuna esperienza disponibile', 'fp-experiences') . '</strong></p>';
            echo '<p>' . esc_html__('Per utilizzare il calendario, devi prima creare almeno un\'esperienza.', 'fp-experiences') . '</p>';
            echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=fp_experience')) . '" class="button button-primary">' . esc_html__('Crea la prima esperienza', 'fp-experiences') . '</a></p>';
            echo '</div>';
            echo '</div>';
        }
        
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
                $start_label = $this->format_slot_datetime_label($start);
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
                    $start_label,
                    $total_guests,
                    esc_html__('ospiti', 'fp-experiences')
                );

                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('Nessuna prenotazione imminente trovata.', 'fp-experiences') . '</p>';
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
        echo '<tr><th scope="row"><label for="fp-exp-experience">' . esc_html__('Esperienza', 'fp-experiences') . '</label></th><td>';
        echo '<select id="fp-exp-experience" name="experience_id" onchange="this.form.submit()">';
        foreach ($experiences as $experience) {
            echo '<option value="' . esc_attr((string) $experience->ID) . '" ' . selected($selected_experience, $experience->ID, false) . '>' . esc_html($experience->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Selezionando un\'esperienza diversa verranno ricaricati gli slot disponibili.', 'fp-experiences') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="fp-exp-slot">' . esc_html__('Slot', 'fp-experiences') . '</label></th><td>';
        $selected_slot_id = isset($_POST['slot_id']) ? absint((string) $_POST['slot_id']) : 0;

        if ($slots) {
            if ($selected_slot_id <= 0 && isset($slots[0]['id'])) {
                $selected_slot_id = (int) $slots[0]['id'];
            }

            echo '<select id="fp-exp-slot" name="slot_id">';
            foreach ($slots as $slot) {
                $start_datetime = isset($slot['start_datetime']) ? (string) $slot['start_datetime'] : '';
                $label = $this->format_slot_datetime_label($start_datetime);
                echo '<option value="' . esc_attr((string) $slot['id']) . '" ' . selected($selected_slot_id, $slot['id'] ?? 0, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<p>' . esc_html__('Nessuno slot disponibile per questa esperienza.', 'fp-experiences') . '</p>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label>' . esc_html__('Biglietti', 'fp-experiences') . '</label></th><td>';
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
            echo '<p>' . esc_html__('Nessun extra configurato per questa esperienza.', 'fp-experiences') . '</p>';
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
            submit_button(esc_html__('Crea prenotazione manuale', 'fp-experiences'));
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
            return new WP_Error('fp_exp_manual_permission', esc_html__('Non hai i permessi per creare prenotazioni manuali.', 'fp-experiences'));
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

    private function render_operator_overview(): void
    {
        $filters = $this->get_operator_filters();
        $metrics = $this->get_operator_metrics();
        $rows = $this->get_filtered_operator_reservations($filters, 30);
        $experiences = $this->get_operator_experiences();

        echo '<section class="fp-exp-operator-overview">';
        echo '<h2>' . esc_html__('Dashboard Operatore', 'fp-experiences') . '</h2>';
        echo '<p class="description">' . esc_html__('Controllo rapido di calendario e prenotazioni con filtri operativi.', 'fp-experiences') . '</p>';

        echo '<div class="fp-exp-operator-overview__kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0;">';
        $kpi_items = [
            [
                'label' => esc_html__('Richieste in attesa', 'fp-experiences'),
                'value' => (int) $metrics['pending_requests'],
            ],
            [
                'label' => esc_html__('Partenze oggi', 'fp-experiences'),
                'value' => (int) $metrics['departures_today'],
            ],
            [
                'label' => esc_html__('Check-in oggi', 'fp-experiences'),
                'value' => (int) $metrics['checkins_today'],
            ],
            [
                'label' => esc_html__('Partenze prossime 24h', 'fp-experiences'),
                'value' => (int) $metrics['departures_24h'],
            ],
        ];
        foreach ($kpi_items as $item) {
            echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;">';
            echo '<div style="font-size:12px;color:#6b7280;margin-bottom:4px;">' . esc_html((string) $item['label']) . '</div>';
            echo '<div style="font-size:26px;font-weight:700;line-height:1;">' . esc_html(number_format_i18n((int) $item['value'])) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="fp-exp-operator-overview__quick-actions" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;">';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=fp_exp_requests')) . '">' . esc_html__('Apri Richieste RTB', 'fp-experiences') . '</a>';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=fp_exp_checkin')) . '">' . esc_html__('Apri Check-in', 'fp-experiences') . '</a>';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar&view=calendar')) . '">' . esc_html__('Apri Calendario Slot', 'fp-experiences') . '</a>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar&view=manual')) . '">' . esc_html__('Nuova Prenotazione Manuale', 'fp-experiences') . '</a>';
        echo '</div>';

        echo '<form method="get" style="margin:0 0 12px;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;">';
        echo '<input type="hidden" name="page" value="fp_exp_calendar" />';
        echo '<input type="hidden" name="view" value="overview" />';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end;">';

        echo '<label><span style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">' . esc_html__('Esperienza', 'fp-experiences') . '</span>';
        echo '<select name="experience_id" style="width:100%;">';
        echo '<option value="0">' . esc_html__('Tutte', 'fp-experiences') . '</option>';
        foreach ($experiences as $experience) {
            $exp_id = (int) ($experience['id'] ?? 0);
            $exp_title = (string) ($experience['title'] ?? '');
            if ($exp_id <= 0) {
                continue;
            }

            echo '<option value="' . esc_attr((string) $exp_id) . '" ' . selected($filters['experience_id'], $exp_id, false) . '>' . esc_html($exp_title) . '</option>';
        }
        echo '</select></label>';

        $status_options = [
            'all' => esc_html__('Tutti gli stati', 'fp-experiences'),
            Reservations::STATUS_PENDING => esc_html__('Pending', 'fp-experiences'),
            Reservations::STATUS_PENDING_REQUEST => esc_html__('Pending RTB', 'fp-experiences'),
            Reservations::STATUS_APPROVED_CONFIRMED => esc_html__('Approvata', 'fp-experiences'),
            Reservations::STATUS_APPROVED_PENDING_PAYMENT => esc_html__('Da pagare', 'fp-experiences'),
            Reservations::STATUS_PAID => esc_html__('Pagata', 'fp-experiences'),
            Reservations::STATUS_CHECKED_IN => esc_html__('Check-in', 'fp-experiences'),
            Reservations::STATUS_CANCELLED => esc_html__('Cancellata', 'fp-experiences'),
            Reservations::STATUS_DECLINED => esc_html__('Rifiutata', 'fp-experiences'),
        ];
        echo '<label><span style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">' . esc_html__('Stato', 'fp-experiences') . '</span>';
        echo '<select name="status" style="width:100%;">';
        foreach ($status_options as $status_key => $status_label) {
            echo '<option value="' . esc_attr((string) $status_key) . '" ' . selected($filters['status'], $status_key, false) . '>' . esc_html($status_label) . '</option>';
        }
        echo '</select></label>';

        echo '<label><span style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">' . esc_html__('Da data', 'fp-experiences') . '</span>';
        echo '<input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '" style="width:100%;" />';
        echo '</label>';

        echo '<label><span style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">' . esc_html__('A data', 'fp-experiences') . '</span>';
        echo '<input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '" style="width:100%;" />';
        echo '</label>';

        echo '<div>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__('Applica filtri', 'fp-experiences') . '</button> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar&view=overview')) . '">' . esc_html__('Reset', 'fp-experiences') . '</a>';
        echo '</div>';

        echo '</div>';
        echo '</form>';

        if (! $rows) {
            echo '<p>' . esc_html__('Nessuna prenotazione trovata con i filtri selezionati.', 'fp-experiences') . '</p>';
            echo '</section>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Esperienza', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Data/Ora', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Ospiti', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Stato', 'fp-experiences') . '</th>';
        echo '<th>' . esc_html__('Azioni rapide', 'fp-experiences') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $reservation_id = (int) ($row['id'] ?? 0);
            $experience_id = (int) ($row['experience_id'] ?? 0);
            $experience_title = (string) ($row['experience_title'] ?? '');
            $start_datetime = (string) ($row['start_datetime'] ?? '');
            $status = Reservations::normalize_status((string) ($row['status'] ?? ''));
            $pax = is_array($row['pax'] ?? null) ? $row['pax'] : [];
            $guests = (int) array_sum(array_map('absint', $pax));

            $timestamp = strtotime($start_datetime . ' UTC');
            $start_label = $timestamp ? wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $timestamp) : esc_html__('Sconosciuto', 'fp-experiences');

            echo '<tr>';
            echo '<td>' . esc_html($experience_title ?: sprintf('#%d', $experience_id)) . '</td>';
            echo '<td>' . esc_html($start_label) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($guests)) . '</td>';
            echo '<td>' . esc_html($this->format_operator_status_label($status)) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=fp_exp_checkin')) . '">' . esc_html__('Check-in', 'fp-experiences') . '</a> ';
            if ($reservation_id > 0) {
                $order_id = (int) ($row['order_id'] ?? 0);
                if ($order_id > 0 && 'shop_order' === get_post_type($order_id)) {
                    echo '<a class="button button-small" href="' . esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')) . '">' . esc_html__('Apri ordine', 'fp-experiences') . '</a>';
                } else {
                    echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=fp_exp_requests')) . '">' . esc_html__('Apri richieste', 'fp-experiences') . '</a>';
                }
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</section>';
    }

    /**
     * @return array{experience_id:int,status:string,date_from:string,date_to:string}
     */
    private function get_operator_filters(): array
    {
        $experience_id = isset($_GET['experience_id']) ? absint((string) wp_unslash($_GET['experience_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_from = isset($_GET['date_from']) ? sanitize_text_field((string) wp_unslash($_GET['date_from'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_to = isset($_GET['date_to']) ? sanitize_text_field((string) wp_unslash($_GET['date_to'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return [
            'experience_id' => $experience_id,
            'status' => '' !== $status ? $status : 'all',
            'date_from' => $date_from,
            'date_to' => $date_to,
        ];
    }

    /**
     * @return array{pending_requests:int,departures_today:int,checkins_today:int,departures_24h:int}
     */
    private function get_operator_metrics(): array
    {
        global $wpdb;

        $reservations_table = Reservations::table_name();
        $slots_table = Slots::table_name();
        $timezone = wp_timezone();

        $now_local = new \DateTimeImmutable('now', $timezone);
        $today_start_local = $now_local->setTime(0, 0, 0);
        $today_end_local = $now_local->setTime(23, 59, 59);
        $next_24_local = $now_local->add(new \DateInterval('PT24H'));

        $today_start_utc = $today_start_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $today_end_utc = $today_end_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $now_utc = $now_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $next_24_utc = $next_24_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $pending_requests = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations_table} WHERE status = %s",
                Reservations::STATUS_PENDING_REQUEST
            )
        );

        $departures_today = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations_table} r
                INNER JOIN {$slots_table} s ON r.slot_id = s.id
                WHERE s.start_datetime BETWEEN %s AND %s
                AND r.status NOT IN (%s, %s)",
                $today_start_utc,
                $today_end_utc,
                Reservations::STATUS_CANCELLED,
                Reservations::STATUS_DECLINED
            )
        );

        $checkins_today = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations_table} r
                INNER JOIN {$slots_table} s ON r.slot_id = s.id
                WHERE s.start_datetime BETWEEN %s AND %s
                AND r.status = %s",
                $today_start_utc,
                $today_end_utc,
                Reservations::STATUS_CHECKED_IN
            )
        );

        $departures_24h = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations_table} r
                INNER JOIN {$slots_table} s ON r.slot_id = s.id
                WHERE s.start_datetime BETWEEN %s AND %s
                AND r.status NOT IN (%s, %s)",
                $now_utc,
                $next_24_utc,
                Reservations::STATUS_CANCELLED,
                Reservations::STATUS_DECLINED
            )
        );

        return [
            'pending_requests' => $pending_requests,
            'departures_today' => $departures_today,
            'checkins_today' => $checkins_today,
            'departures_24h' => $departures_24h,
        ];
    }

    /**
     * @param array{experience_id:int,status:string,date_from:string,date_to:string} $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function get_filtered_operator_reservations(array $filters, int $limit = 30): array
    {
        global $wpdb;

        $reservations_table = Reservations::table_name();
        $slots_table = Slots::table_name();
        $posts_table = $wpdb->posts;

        $where = [];
        $params = [];

        $where[] = 'r.status NOT IN (%s, %s)';
        $params[] = Reservations::STATUS_CANCELLED;
        $params[] = Reservations::STATUS_DECLINED;

        if (($filters['experience_id'] ?? 0) > 0) {
            $where[] = 'r.experience_id = %d';
            $params[] = (int) $filters['experience_id'];
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ('all' !== $status && '' !== $status) {
            $where[] = 'r.status = %s';
            $params[] = Reservations::normalize_status($status);
        }

        if (! empty($filters['date_from'])) {
            $where[] = 's.start_datetime >= %s';
            $params[] = sanitize_text_field($filters['date_from']) . ' 00:00:00';
        }

        if (! empty($filters['date_to'])) {
            $where[] = 's.start_datetime <= %s';
            $params[] = sanitize_text_field($filters['date_to']) . ' 23:59:59';
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $limit = max(1, $limit);
        $params[] = $limit;

        $sql = $wpdb->prepare(
            "SELECT r.id, r.order_id, r.experience_id, r.status, r.pax, s.start_datetime, p.post_title AS experience_title
            FROM {$reservations_table} r
            INNER JOIN {$slots_table} s ON r.slot_id = s.id
            LEFT JOIN {$posts_table} p ON p.ID = r.experience_id
            {$where_sql}
            ORDER BY s.start_datetime ASC
            LIMIT %d",
            ...$params
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! $rows) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['pax'] = maybe_unserialize($row['pax']);
                return $row;
            },
            $rows
        );
    }

    /**
     * @return array<int, array{id:int,title:string}>
     */
    private function get_operator_experiences(): array
    {
        $posts = get_posts([
            'post_type' => 'fp_experience',
            'posts_per_page' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'private'],
            'suppress_filters' => true,
        ]);

        $experiences = [];
        foreach ($posts as $post) {
            $experiences[] = [
                'id' => (int) $post->ID,
                'title' => (string) get_the_title((int) $post->ID),
            ];
        }

        return $experiences;
    }

    private function format_operator_status_label(string $status): string
    {
        switch ($status) {
            case Reservations::STATUS_PENDING:
                return esc_html__('Pending', 'fp-experiences');
            case Reservations::STATUS_PENDING_REQUEST:
                return esc_html__('Pending RTB', 'fp-experiences');
            case Reservations::STATUS_APPROVED_CONFIRMED:
                return esc_html__('Approvata', 'fp-experiences');
            case Reservations::STATUS_APPROVED_PENDING_PAYMENT:
                return esc_html__('Da pagare', 'fp-experiences');
            case Reservations::STATUS_PAID:
                return esc_html__('Pagata', 'fp-experiences');
            case Reservations::STATUS_CHECKED_IN:
                return esc_html__('Check-in', 'fp-experiences');
            case Reservations::STATUS_CANCELLED:
                return esc_html__('Cancellata', 'fp-experiences');
            case Reservations::STATUS_DECLINED:
                return esc_html__('Rifiutata', 'fp-experiences');
            default:
                return esc_html__('Stato', 'fp-experiences');
        }
    }

    private function render_operator_navigation(string $active_view): void
    {
        $calendar_view = in_array($active_view, ['overview', 'calendar', 'manual'], true) ? $active_view : 'overview';
        $calendar_url = add_query_arg(
            [
                'page' => 'fp_exp_calendar',
                'view' => $calendar_view,
            ],
            admin_url('admin.php')
        );

        echo '<nav class="fp-exp-operator-nav nav-tab-wrapper" aria-label="' . esc_attr__('Navigazione operatore', 'fp-experiences') . '">';
        echo '<a class="nav-tab nav-tab-active" href="' . esc_url($calendar_url) . '">' . esc_html__('Calendario & Prenotazioni', 'fp-experiences') . '</a>';

        if (Helpers::rtb_mode() !== 'off') {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_requests')) . '">' . esc_html__('Richieste RTB', 'fp-experiences') . '</a>';
        }

        echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_checkin')) . '">' . esc_html__('Check-in', 'fp-experiences') . '</a>';

        if (current_user_can('manage_woocommerce') && Helpers::can_manage_fp()) {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=fp_exp_orders')) . '">' . esc_html__('Ordini', 'fp-experiences') . '</a>';
        }

        echo '</nav>';
    }

    private function format_slot_datetime_label(string $datetime_utc): string
    {
        if ('' === $datetime_utc) {
            return '';
        }

        try {
            $utc_datetime = new \DateTimeImmutable($datetime_utc, new DateTimeZone('UTC'));
            $local_datetime = $utc_datetime->setTimezone(wp_timezone());
            $date_format = get_option('date_format', 'd/m/Y');
            $time_format = get_option('time_format', 'H:i');

            return wp_date($date_format . ' ' . $time_format, $local_datetime->getTimestamp());
        } catch (\Throwable $exception) {
            return $datetime_utc;
        }
    }
}
