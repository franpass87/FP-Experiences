<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use WC_Order;

use function admin_url;
use function current_user_can;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_option;
use function maybe_unserialize;
use function number_format_i18n;
use function wp_die;
use function wp_strip_all_tags;
use function wp_timezone;
use function wc_get_order;
use function wc_get_order_status_name;
use function wc_get_order_statuses;

final class Dashboard
{
    public static function render(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per accedere alla dashboard di FP Experiences.', 'fp-experiences'));
        }

        $metrics = self::collect_metrics();

        echo '<div class="wrap fp-exp-dashboard">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-dashboard fp-exp-admin__layout">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Dashboard', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Dashboard FP Experiences', 'fp-experiences') . '</h1>';
        echo '</header>';

        // Setup Checklist Banner
        self::render_setup_checklist();

        echo '<div class="fp-exp-dashboard__grid">';
        self::render_metric_card(
            esc_html__('Prenotazioni oggi', 'fp-experiences'),
            number_format_i18n($metrics['bookings_today'])
        );

        $fill_rate = $metrics['fill_rate'];
        self::render_metric_card(
            esc_html__('Riempimento settimana', 'fp-experiences'),
            $fill_rate['label'],
            $fill_rate['description']
        );

        if (null !== $metrics['pending_requests']) {
            self::render_metric_card(
                esc_html__('Richieste in attesa', 'fp-experiences'),
                number_format_i18n($metrics['pending_requests'])
            );
        }
        echo '</div>';

        echo '<div class="fp-exp-dashboard__columns">';

        echo '<section class="fp-exp-dashboard__section" aria-labelledby="fp-exp-dashboard-orders">';
        echo '<h2 id="fp-exp-dashboard-orders">' . esc_html__('Ultimi ordini esperienza', 'fp-experiences') . '</h2>';
        if ($metrics['orders']) {
            echo '<table class="widefat striped fp-exp-dashboard__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('# Ordine', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Data', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Stato', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Totale', 'fp-experiences') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($metrics['orders'] as $order) {
                echo '<tr>';
                echo '<td><a href="' . esc_url($order['url']) . '">#' . esc_html($order['number']) . '</a></td>';
                echo '<td>' . esc_html($order['date']) . '</td>';
                echo '<td>' . esc_html($order['status']) . '</td>';
                echo '<td>' . esc_html($order['total']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            self::render_empty_state(
                'tickets-alt',
                esc_html__('Nessun ordine ancora', 'fp-experiences'),
                esc_html__('Gli ordini delle esperienze appariranno qui quando i clienti completeranno le prenotazioni.', 'fp-experiences'),
                admin_url('edit.php?post_type=fp_experience'),
                esc_html__('Gestisci Esperienze', 'fp-experiences')
            );
        }
        echo '</section>';

        echo '<section class="fp-exp-dashboard__section" aria-labelledby="fp-exp-dashboard-shortcuts">';
        echo '<h2 id="fp-exp-dashboard-shortcuts">' . esc_html__('Azioni rapide', 'fp-experiences') . '</h2>';
        echo '<ul class="fp-exp-dashboard__shortcuts">';
        if (current_user_can('edit_fp_experiences')) {
            echo '<li><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=fp_experience')) . '">' . esc_html__('Crea nuova esperienza', 'fp-experiences') . '</a></li>';
        }
        echo '<li><a class="button" href="' . esc_url(admin_url('edit.php?post_type=fp_experience')) . '">' . esc_html__('Gestisci vetrina', 'fp-experiences') . '</a></li>';
        if (Helpers::can_manage_fp()) {
            echo '<li><a class="button" href="' . esc_url(admin_url('admin.php?page=fp_exp_settings')) . '">' . esc_html__('Apri impostazioni', 'fp-experiences') . '</a></li>';
        }
        echo '</ul>';
        echo '</section>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * @return array{
     *     bookings_today: int,
     *     fill_rate: array{label: string, description: string},
     *     pending_requests: ?int,
     *     orders: array<int, array{number: string, date: string, status: string, total: string, url: string}>
     * }
     */
    private static function collect_metrics(): array
    {
        global $wpdb;

        $reservations_table = Reservations::table_name();
        $slots_table = Slots::table_name();

        $timezone = wp_timezone();
        $today = new DateTimeImmutable('now', $timezone);
        $start_of_day = $today->setTime(0, 0, 0);
        $end_of_day = $start_of_day->setTime(23, 59, 59);
        $end_of_week = $start_of_day->add(new DateInterval('P6D'))->setTime(23, 59, 59);

        $start_utc = $start_of_day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $end_day_utc = $end_of_day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $end_week_utc = $end_of_week->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $bookings_today = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(r.id) FROM {$reservations_table} r " .
                "INNER JOIN {$slots_table} s ON r.slot_id = s.id " .
                "WHERE s.start_datetime BETWEEN %s AND %s AND r.status NOT IN (%s, %s)",
                $start_utc,
                $end_day_utc,
                Reservations::STATUS_CANCELLED,
                Reservations::STATUS_DECLINED
            )
        );

        $reservations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.pax FROM {$reservations_table} r " .
                "INNER JOIN {$slots_table} s ON r.slot_id = s.id " .
                "WHERE s.start_datetime BETWEEN %s AND %s AND r.status NOT IN (%s, %s)",
                $start_utc,
                $end_week_utc,
                Reservations::STATUS_CANCELLED,
                Reservations::STATUS_DECLINED
            ),
            ARRAY_A
        );

        $guests_week = 0;
        foreach ($reservations as $row) {
            $pax = maybe_unserialize($row['pax']);
            if (is_array($pax)) {
                foreach ($pax as $quantity) {
                    if (is_numeric($quantity)) {
                        $guests_week += (int) $quantity;
                    }
                }
            } elseif (is_numeric($row['pax'])) {
                $guests_week += (int) $row['pax'];
            }
        }

        $capacity_total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(capacity_total) FROM {$slots_table} WHERE start_datetime BETWEEN %s AND %s AND status <> %s",
                $start_utc,
                $end_week_utc,
                Slots::STATUS_CANCELLED
            )
        );

        $fill_percentage = 0.0;
        if ($capacity_total > 0) {
            $fill_percentage = min(100.0, ($guests_week / $capacity_total) * 100.0);
        }

        $fill_rate = [
            'label' => $capacity_total > 0
                ? sprintf('%s%%', number_format_i18n($fill_percentage, 1))
                : esc_html__('n/d', 'fp-experiences'),
            'description' => $capacity_total > 0
                ? sprintf(
                    /* translators: 1: booked guests, 2: available seats. */
                    esc_html__('%1$s ospiti su %2$s posti disponibili nei prossimi 7 giorni.', 'fp-experiences'),
                    number_format_i18n($guests_week),
                    number_format_i18n($capacity_total)
                )
                : esc_html__('Nessun slot programmato per la settimana corrente.', 'fp-experiences'),
        ];

        $pending_requests = null;
        if (Helpers::rtb_mode() !== 'off') {
            $pending_requests = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(id) FROM {$reservations_table} WHERE status = %s",
                    Reservations::STATUS_PENDING_REQUEST
                )
            );
        }

        $orders = self::load_recent_orders();

        return [
            'bookings_today' => $bookings_today,
            'fill_rate' => $fill_rate,
            'pending_requests' => $pending_requests,
            'orders' => $orders,
        ];
    }

    /**
     * @return array<int, array{number: string, date: string, status: string, total: string, url: string}>
     */
    private static function load_recent_orders(): array
    {
        if (! function_exists('wc_get_order')) {
            return [];
        }

        global $wpdb;

        $order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        $posts_table = $wpdb->posts;
        $statuses = array_keys(wc_get_order_statuses());

        if (! $statuses) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$order_items_table} i " .
            "INNER JOIN {$posts_table} p ON i.order_id = p.ID " .
            "WHERE i.order_item_type = %s AND p.post_type = 'shop_order' " .
            "AND p.post_status IN ({$placeholders}) ORDER BY p.post_date DESC LIMIT 5",
            array_merge(['fp_experience_item'], $statuses)
        );

        $order_ids = $wpdb->get_col($sql);

        if (! $order_ids) {
            return [];
        }

        $orders = [];
        foreach ($order_ids as $order_id) {
            $order = wc_get_order((int) $order_id);
            if (! $order instanceof WC_Order) {
                continue;
            }

            $date = $order->get_date_created();
            $orders[] = [
                'number' => $order->get_order_number(),
                'date' => $date ? $date->date_i18n(get_option('date_format', 'Y-m-d H:i')) : esc_html__('n/d', 'fp-experiences'),
                'status' => wc_get_order_status_name($order->get_status()),
                'total' => wp_strip_all_tags($order->get_formatted_order_total()),
                'url' => $order->get_edit_order_url(),
            ];
        }

        return $orders;
    }

    private static function render_metric_card(string $label, string $value, ?string $description = null): void
    {
        echo '<div class="fp-exp-dashboard__card">';
        echo '<p class="fp-exp-dashboard__card-label">' . esc_html($label) . '</p>';
        echo '<p class="fp-exp-dashboard__card-value">' . esc_html($value) . '</p>';
        if ($description) {
            echo '<p class="fp-exp-dashboard__card-hint">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Renders the setup checklist banner
     */
    private static function render_setup_checklist(): void
    {
        // Check setup completion
        $checklist = self::get_setup_checklist();
        $completed = count(array_filter($checklist, fn($item) => $item['done']));
        $total = count($checklist);
        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

        // Don't show if 100% complete
        if ($percentage >= 100) {
            // Store that setup is complete
            if (!get_option('fp_exp_setup_complete', false)) {
                update_option('fp_exp_setup_complete', true, false);
            }
            return;
        }

        echo '<div class="fp-exp-setup-banner">';
        echo '<div class="fp-exp-setup-banner__header">';
        echo '<h3 class="fp-exp-setup-banner__title">';
        echo '<span class="dashicons dashicons-admin-generic"></span> ';
        echo esc_html__('Setup Configurazione', 'fp-experiences');
        echo '</h3>';
        echo '<div class="fp-exp-setup-banner__progress">';
        echo '<span class="fp-exp-setup-banner__percentage">' . esc_html($percentage) . '%</span>';
        echo '<div class="fp-exp-setup-banner__bar">';
        echo '<div class="fp-exp-setup-banner__bar-fill" style="width: ' . esc_attr($percentage) . '%"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<ul class="fp-exp-setup-banner__list">';
        foreach ($checklist as $item) {
            $icon = $item['done'] ? 'yes-alt' : 'marker';
            $class = $item['done'] ? 'done' : 'pending';
            
            echo '<li class="fp-exp-setup-banner__item fp-exp-setup-banner__item--' . esc_attr($class) . '">';
            echo '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span> ';
            echo '<span class="fp-exp-setup-banner__item-text">' . esc_html($item['label']) . '</span>';
            
            if (!$item['done'] && !empty($item['action_url'])) {
                echo ' <a href="' . esc_url($item['action_url']) . '" class="fp-exp-setup-banner__action">' . esc_html($item['action_label']) . ' →</a>';
            }
            
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Gets setup checklist items with completion status
     * 
     * @return array<int, array<string, mixed>>
     */
    private static function get_setup_checklist(): array
    {
        global $wpdb;

        // 1. Check if at least one experience exists
        $has_experience = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'fp_experience' AND post_status = 'publish'") > 0;

        // 2. Check if calendar has slots
        $slots_table = $wpdb->prefix . 'fp_exp_calendar_slots';
        $has_slots = false;
        if ($wpdb->get_var("SHOW TABLES LIKE '$slots_table'") === $slots_table) {
            $has_slots = $wpdb->get_var("SELECT COUNT(*) FROM {$slots_table}") > 0;
        }

        // 3. Check if payment gateway is configured (WooCommerce)
        $has_payment = false;
        if (class_exists('WC_Payment_Gateways')) {
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $has_payment = count($gateways) > 0;
        }

        // 4. Check if Brevo is configured
        $brevo = get_option('fp_exp_brevo', []);
        $has_brevo = !empty($brevo['enabled'] ?? false) && !empty($brevo['api_key'] ?? '');

        // 5. Check if checkout page exists
        $checkout_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => '[fp_exp_checkout]',
        ]);
        $has_checkout_page = !empty($checkout_pages);

        return [
            [
                'label' => esc_html__('Crea la tua prima esperienza', 'fp-experiences'),
                'done' => $has_experience,
                'action_url' => admin_url('post-new.php?post_type=fp_experience'),
                'action_label' => esc_html__('Crea ora', 'fp-experiences'),
            ],
            [
                'label' => esc_html__('Configura calendario disponibilità', 'fp-experiences'),
                'done' => $has_slots,
                'action_url' => admin_url('admin.php?page=fp_exp_calendar'),
                'action_label' => esc_html__('Vai al calendario', 'fp-experiences'),
            ],
            [
                'label' => esc_html__('Configura metodo di pagamento', 'fp-experiences'),
                'done' => $has_payment,
                'action_url' => admin_url('admin.php?page=wc-settings&tab=checkout'),
                'action_label' => esc_html__('Configura', 'fp-experiences'),
            ],
            [
                'label' => esc_html__('Crea pagina Checkout', 'fp-experiences'),
                'done' => $has_checkout_page,
                'action_url' => admin_url('post-new.php?post_type=page'),
                'action_label' => esc_html__('Crea pagina', 'fp-experiences'),
            ],
            [
                'label' => esc_html__('Email conferme (Brevo - opzionale)', 'fp-experiences'),
                'done' => $has_brevo,
                'action_url' => admin_url('admin.php?page=fp_exp_settings&tab=general'),
                'action_label' => esc_html__('Configura', 'fp-experiences'),
            ],
        ];
    }

    /**
     * Renders an empty state with icon, message and CTA
     */
    private static function render_empty_state(
        string $icon,
        string $title,
        string $message,
        ?string $action_url = null,
        ?string $action_label = null
    ): void {
        echo '<div class="fp-exp-empty-state">';
        echo '<div class="fp-exp-empty-state__icon">';
        echo '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
        echo '</div>';
        echo '<h3 class="fp-exp-empty-state__title">' . esc_html($title) . '</h3>';
        echo '<p class="fp-exp-empty-state__message">' . esc_html($message) . '</p>';
        
        if ($action_url && $action_label) {
            echo '<a href="' . esc_url($action_url) . '" class="button button-primary fp-exp-empty-state__button">' . esc_html($action_label) . '</a>';
        }
        
        echo '</div>';
    }
}
