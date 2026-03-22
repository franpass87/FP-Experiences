<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Logger;
use FP_Exp\Booking\Email\Senders\CustomerEmailSender;
use FP_Exp\Booking\Email\Senders\StaffEmailSender;
use FP_Exp\Booking\Email\Services\EmailSchedulerService;
use FP_Exp\Booking\Email\Templates\BookingConfirmationTemplate;
use FP_Exp\Booking\Email\Templates\BookingFollowupTemplate;
use FP_Exp\Booking\Email\Templates\BookingReminderTemplate;
use FP_Exp\Booking\Email\Templates\BookingRescheduledTemplate;
use FP_Exp\Booking\Email\Templates\StaffRescheduledTemplate;
use FP_Exp\Booking\Email\Templates\StaffNotificationTemplate;
use FP_Exp\Checkin\QrTokenService;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Booking\EmailTranslator;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Services\Options\OptionsInterface;
use WC_Order;

use function __;
use function absint;
use function add_action;
use function array_filter;
use function array_map;
use function array_sum;
use function esc_attr;
use function esc_html;
use function esc_url;
use function get_bloginfo;
use function get_locale;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function gmdate;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function nl2br;
use function preg_match;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_replace;
use function strtolower;
use function strpos;
use function strtotime;
use function time;
use function trim;
use function wc_get_order;
use function wp_date;
use function wp_kses_post;
use function wp_strip_all_tags;
use function wp_timezone_string;
use function admin_url;
use function file_exists;
use const DAY_IN_SECONDS;

/**
 * Orchestrates all experience-specific email dispatch.
 *
 * Standard WooCommerce order emails (New Order, Processing, Completed)
 * are now handled by WooCommerce itself for fp-exp bookings.
 * This class focuses on experience-detail emails:
 *   - Staff notification (new booking / cancellation)
 *   - Customer confirmation (experience details + ICS)
 *   - Customer reminder (pre-experience)
 *   - Customer follow-up (post-experience)
 */
final class Emails implements HookableInterface
{
    private const REMINDER_HOOK = 'fp_exp_email_send_reminder';
    private const FOLLOWUP_HOOK = 'fp_exp_email_send_followup';

    private OptionsInterface $options;
    private CustomerEmailSender $customer_sender;
    private StaffEmailSender $staff_sender;
    private EmailSchedulerService $scheduler_service;

    public function __construct(
        OptionsInterface $options,
        CustomerEmailSender $customer_sender,
        StaffEmailSender $staff_sender,
        ?EmailSchedulerService $scheduler_service = null
    ) {
        $this->options = $options;
        $this->customer_sender = $customer_sender;
        $this->staff_sender = $staff_sender;
        $this->scheduler_service = $scheduler_service ?? new EmailSchedulerService();
    }

    public function register_hooks(): void
    {
        add_action('fp_exp_reservation_created', [$this, 'handle_reservation_created'], 10, 2);
        add_action('fp_exp_reservation_paid', [$this, 'handle_reservation_paid'], 10, 2);
        add_action('fp_exp_reservation_cancelled', [$this, 'handle_reservation_cancelled'], 10, 2);
        add_action('fp_exp_reservation_rescheduled', [$this, 'handle_reservation_rescheduled'], 10, 4);
        add_action(self::REMINDER_HOOK, [$this, 'handle_reminder_dispatch'], 10, 2);
        add_action(self::FOLLOWUP_HOOK, [$this, 'handle_followup_dispatch'], 10, 2);
        add_filter('fp_exp_email_branding', [$this, 'apply_branding'], 10, 2);
    }

    // ------------------------------------------------------------------
    // Hook handlers
    // ------------------------------------------------------------------

    /**
     * Sends immediate staff notification so the team knows about the booking
     * even before payment completes.
     */
    public function handle_reservation_created(int $reservation_id, int $order_id): void
    {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            Logger::log('email', sprintf(
                'handle_reservation_created: get_context returned null for reservation %d, order %d — will retry on reservation_paid',
                $reservation_id,
                $order_id
            ));
            return;
        }

        if (! $this->isTypeEnabled('staff_notification')) {
            return;
        }

        $template = new StaffNotificationTemplate(false);
        $sent = $this->staff_sender->send($template, $context);

        if ($sent) {
            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order) {
                $order->update_meta_data('_fp_exp_staff_notified', '1');
                $order->save();
            }
        } else {
            Logger::log('email', sprintf(
                'handle_reservation_created: staff email failed for reservation %d, order %d — will retry on reservation_paid',
                $reservation_id,
                $order_id
            ));
        }
    }

    /**
     * Sends experience-specific customer confirmation and (fallback) staff notification.
     */
    public function handle_reservation_paid(int $reservation_id, int $order_id): void
    {
        $this->ensure_event_checkin_token($reservation_id, $order_id);

        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            Logger::log('email', sprintf(
                'handle_reservation_paid: get_context returned null for reservation %d, order %d',
                $reservation_id,
                $order_id
            ));
            return;
        }

        if ($this->isTypeEnabled('customer_confirmation')) {
            $template = new BookingConfirmationTemplate();
            $this->customer_sender->send($template, $context);
        }

        // Fallback: send staff notification if it wasn't sent on reservation_created.
        if ($this->isTypeEnabled('staff_notification')) {
            $order = wc_get_order($order_id);
            $already_notified = $order instanceof WC_Order && $order->get_meta('_fp_exp_staff_notified') === '1';

            if (! $already_notified) {
                Logger::log('email', sprintf(
                    'handle_reservation_paid: staff not yet notified — sending now for reservation %d, order %d',
                    $reservation_id,
                    $order_id
                ));
                $staff_template = new StaffNotificationTemplate(false);
                $sent = $this->staff_sender->send($staff_template, $context);
                if ($sent && $order instanceof WC_Order) {
                    $order->update_meta_data('_fp_exp_staff_notified', '1');
                    $order->save();
                }
            }
        }

        $this->scheduleAutomations($context);
    }

    public function handle_reservation_cancelled(int $reservation_id, int $order_id): void
    {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            Logger::log('email', sprintf(
                'handle_reservation_cancelled: get_context returned null for reservation %d, order %d',
                $reservation_id,
                $order_id
            ));
            return;
        }

        $language = $this->resolve_language($context);
        $context['language'] = $language;
        $context['status_label'] = EmailTranslator::text('common.status_cancelled', $language);

        if ($this->isTypeEnabled('staff_notification')) {
            $template = new StaffNotificationTemplate(true);
            $this->staff_sender->send($template, $context);
        }

        $this->scheduler_service->cancelNotifications($reservation_id, $order_id);
    }

    public function handle_reservation_rescheduled(
        int $reservation_id,
        int $order_id,
        int $previous_slot_id = 0,
        int $new_slot_id = 0
    ): void {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            Logger::log('email', sprintf(
                'handle_reservation_rescheduled: get_context returned null for reservation %d, order %d',
                $reservation_id,
                $order_id
            ));
            return;
        }

        $current_slot = $new_slot_id > 0 ? Slots::get_slot($new_slot_id) : null;
        $previous_slot = $previous_slot_id > 0 ? Slots::get_slot($previous_slot_id) : null;
        $context['reschedule'] = [
            'previous' => $this->build_reschedule_slot_snapshot($previous_slot),
            'current' => $this->build_reschedule_slot_snapshot($current_slot),
        ];

        if ($this->isTypeEnabled('customer_confirmation')) {
            $template = new BookingRescheduledTemplate();
            $this->customer_sender->send($template, $context, true);
        }

        if ($this->isTypeEnabled('staff_notification')) {
            $template = new StaffRescheduledTemplate();
            $this->staff_sender->send($template, $context);
        }

        // Refresh reminder/follow-up timers after slot change.
        $this->scheduleAutomations($context);
    }

    public function handle_reminder_dispatch(int $reservation_id, int $order_id): void
    {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $brevo = $this->resolveBrevo();
        if ($brevo !== null && $brevo->try_send_transactional('reminder', $context, $reservation_id)) {
            return;
        }

        $template = new BookingReminderTemplate();
        $this->customer_sender->send($template, $context, true);
    }

    public function handle_followup_dispatch(int $reservation_id, int $order_id): void
    {
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $brevo = $this->resolveBrevo();
        if ($brevo !== null && $brevo->try_send_transactional('post_experience', $context, $reservation_id)) {
            return;
        }

        $template = new BookingFollowupTemplate();
        $this->customer_sender->send($template, $context, true);
    }

    /**
     * Brevo calls this when its transactional send fails so the customer
     * still receives a local confirmation email.
     *
     * @param array<string, mixed> $context
     */
    public function send_customer_confirmation_fallback(array $context): void
    {
        $template = new BookingConfirmationTemplate();
        $this->customer_sender->send($template, $context, true);
    }

    // ------------------------------------------------------------------
    // Context building
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function get_context(int $reservation_id, int $order_id): ?array
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation) {
            Logger::log('email', sprintf('get_context: reservation %d not found', $reservation_id));
            return null;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            Logger::log('email', sprintf('get_context: order %d not found for reservation %d', $order_id, $reservation_id));
            return null;
        }

        $experience = get_post(absint($reservation['experience_id'] ?? 0));

        if (! $experience) {
            Logger::log('email', sprintf('get_context: experience %d not found for reservation %d', absint($reservation['experience_id'] ?? 0), $reservation_id));
            return null;
        }

        $slot = Slots::get_slot(absint($reservation['slot_id'] ?? 0));

        if (! $slot) {
            Logger::log('email', sprintf('get_context: slot %d not found for reservation %d', absint($reservation['slot_id'] ?? 0), $reservation_id));
            return null;
        }

        $contact_meta = $order->get_meta('_fp_exp_contact');
        $contact = $this->normalize_contact($contact_meta, $order);
        $billing_meta = $order->get_meta('_fp_exp_billing');
        $billing = $this->normalize_billing($billing_meta, $order);

        $meeting_point = Repository::get_primary_summary_for_experience((int) $experience->ID);
        $short_desc = get_post_meta($experience->ID, '_fp_short_desc', true);
        $tickets = $this->normalize_tickets($experience->ID, $reservation['pax'] ?? []);
        $addons = $this->normalize_addons($experience->ID, $reservation['addons'] ?? []);

        $start_utc = (string) ($slot['start_datetime'] ?? '');
        $end_utc = (string) ($slot['end_datetime'] ?? '');
        $start_timestamp = $start_utc ? strtotime($start_utc . ' UTC') : 0;
        $end_timestamp = $end_utc ? strtotime($end_utc . ' UTC') : 0;
        $booking_created_at = isset($reservation['created_at']) ? (string) $reservation['created_at'] : '';
        $booking_timestamp = $booking_created_at ? strtotime($booking_created_at . ' UTC') : 0;

        $start_iso = $start_timestamp ? gmdate('c', $start_timestamp) : '';
        $end_iso = $end_timestamp ? gmdate('c', $end_timestamp) : '';

        $reminder_timestamp = $start_timestamp ? max(0, $start_timestamp - DAY_IN_SECONDS) : 0;
        $followup_base = $end_timestamp ?: $start_timestamp;
        $followup_timestamp = $followup_base ? max(0, $followup_base + DAY_IN_SECONDS) : 0;

        $reminder_offset = ($start_timestamp && $reminder_timestamp)
            ? max(0, $start_timestamp - $reminder_timestamp)
            : 0;
        $followup_offset = ($followup_timestamp && $followup_base)
            ? max(0, $followup_timestamp - $followup_base)
            : 0;

        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');
        $timezone_string = wp_timezone_string();

        $event = [
            'summary' => sprintf(
                __('Experience: %s', 'fp-experiences'),
                $experience->post_title
            ),
            'description' => $short_desc ? (string) $short_desc : wp_strip_all_tags((string) $experience->post_excerpt),
            'location' => (string) $meeting_point,
            'start' => $start_utc,
            'end' => $end_utc ?: $start_utc,
            'organizer_name' => get_bloginfo('name'),
            'organizer_email' => $this->get_structure_email(),
            'url' => get_permalink($experience),
            'uid' => 'fp-exp-' . $reservation_id,
        ];

        $ics_content = ICS::generate($event);
        $ics_filename = 'fp-experience-' . $reservation_id . '.ics';
        $calendar_link = ICS::google_calendar_link($event);

        $total_pax = array_sum(array_map('absint', $reservation['pax'] ?? []));
        $is_event = '1' === (string) get_post_meta((int) $experience->ID, '_fp_is_event', true);
        $checkin = $this->resolve_event_checkin_context($reservation_id, $order_id, $reservation, (int) $experience->ID, $is_event);
        $marketing_consent = 'yes' === $order->get_meta('_fp_exp_consent_marketing');

        $context = [
            'reservation' => [
                'id' => $reservation_id,
                'status' => $reservation['status'] ?? '',
            ],
            'experience' => [
                'id' => $experience->ID,
                'title' => $experience->post_title,
                'permalink' => get_permalink($experience),
                'meeting_point' => (string) $meeting_point,
                'short_description' => (string) $short_desc,
                'slug' => (string) $experience->post_name,
            ],
            'slot' => [
                'start_utc' => $start_utc,
                'end_utc' => $end_utc,
                'start_iso' => $start_iso,
                'end_iso' => $end_iso,
                'start_local_date' => $start_timestamp ? wp_date($date_format, $start_timestamp) : '',
                'start_local_time' => $start_timestamp ? wp_date($time_format, $start_timestamp) : '',
                'end_local_time' => $end_timestamp ? wp_date($time_format, $end_timestamp) : '',
                'timezone' => $timezone_string,
                'start_timestamp' => $start_timestamp,
                'end_timestamp' => $end_timestamp,
            ],
            'order' => [
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'total' => $order->get_formatted_order_total(),
                'currency' => $order->get_currency(),
                'notes' => $order->get_customer_note(),
                'admin_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            ],
            'customer' => [
                'name' => trim($contact['first_name'] . ' ' . $contact['last_name']),
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'first_name' => $contact['first_name'],
                'last_name' => $contact['last_name'],
            ],
            'billing' => $billing,
            'tickets' => $tickets,
            'addons' => $addons,
            'totals' => [
                'pax_total' => $total_pax,
                'gross' => (float) $reservation['total_gross'],
                'tax' => (float) $reservation['tax_total'],
            ],
            'checkin' => $checkin,
            'consent' => [
                'marketing' => $marketing_consent,
            ],
            'locale' => get_locale(),
            'ics' => [
                'content' => $ics_content,
                'filename' => $ics_filename,
                'google_link' => $calendar_link,
            ],
            'timers' => [
                'booked_timestamp' => $booking_timestamp,
                'booked_iso' => $booking_timestamp ? gmdate('c', $booking_timestamp) : '',
                'reminder_timestamp' => $reminder_timestamp,
                'reminder_iso' => $reminder_timestamp ? gmdate('c', $reminder_timestamp) : '',
                'reminder_local_date' => $reminder_timestamp ? wp_date($date_format, $reminder_timestamp) : '',
                'reminder_local_time' => $reminder_timestamp ? wp_date($time_format, $reminder_timestamp) : '',
                'followup_timestamp' => $followup_timestamp,
                'followup_iso' => $followup_timestamp ? gmdate('c', $followup_timestamp) : '',
                'followup_local_date' => $followup_timestamp ? wp_date($date_format, $followup_timestamp) : '',
                'followup_local_time' => $followup_timestamp ? wp_date($time_format, $followup_timestamp) : '',
                'reminder_offset' => $reminder_offset,
                'followup_offset' => $followup_offset,
            ],
        ];

        $language = $this->detect_language($context);
        $context['language'] = $language;
        $context['language_locale'] = EmailTranslator::LANGUAGE_IT === $language ? 'it_IT' : 'en_US';

        return $context;
    }

    /**
     * Ensure event reservations always have a valid signed check-in token.
     */
    private function ensure_event_checkin_token(int $reservation_id, int $order_id): void
    {
        $reservation = Reservations::get($reservation_id);
        if (! is_array($reservation)) {
            return;
        }

        $experience_id = absint((int) ($reservation['experience_id'] ?? 0));
        if ($experience_id <= 0) {
            return;
        }

        $is_event = '1' === (string) get_post_meta($experience_id, '_fp_is_event', true);
        if (! $is_event) {
            return;
        }

        $this->ensure_and_store_checkin_token($reservation_id, $order_id, $reservation);
    }

    /**
     * @param array<string, mixed> $reservation
     * @return array{is_event:bool,qr_token:string,qr_url:string,expires_at:int}
     */
    private function resolve_event_checkin_context(
        int $reservation_id,
        int $order_id,
        array $reservation,
        int $experience_id,
        bool $is_event
    ): array {
        if (! $is_event || $experience_id <= 0) {
            return [
                'is_event' => false,
                'qr_token' => '',
                'qr_url' => '',
                'expires_at' => 0,
            ];
        }

        $token_data = $this->ensure_and_store_checkin_token($reservation_id, $order_id, $reservation);
        $token = (string) ($token_data['token'] ?? '');
        $expires_at = (int) ($token_data['expires_at'] ?? 0);

        return [
            'is_event' => true,
            'qr_token' => $token,
            'qr_url' => '' !== $token ? QrTokenService::build_qr_url($token) : '',
            'expires_at' => $expires_at,
        ];
    }

    /**
     * @param array<string, mixed> $reservation
     * @return array{token:string,issued_at:int,expires_at:int,version:int}
     */
    private function ensure_and_store_checkin_token(int $reservation_id, int $order_id, array $reservation): array
    {
        $meta = is_array($reservation['meta'] ?? null) ? $reservation['meta'] : [];
        $checkin = is_array($meta['checkin'] ?? null) ? $meta['checkin'] : [];
        $existing_token = is_string($checkin['token'] ?? null) ? $checkin['token'] : '';

        if ('' !== $existing_token) {
            $verification = QrTokenService::verify($existing_token);
            if (! empty($verification['valid'])) {
                return [
                    'token' => $existing_token,
                    'issued_at' => (int) ($checkin['issued_at'] ?? 0),
                    'expires_at' => (int) ($checkin['expires_at'] ?? 0),
                    'version' => (int) ($checkin['version'] ?? 1),
                ];
            }
        }

        $generated = QrTokenService::generate($reservation_id, $order_id);
        Reservations::update_meta($reservation_id, [
            'checkin' => $generated,
        ]);

        return $generated;
    }

    // ------------------------------------------------------------------
    // Rendering / preview
    // ------------------------------------------------------------------

    public function render_preview(string $template, string $language = EmailTranslator::LANGUAGE_IT): string
    {
        $language = EmailTranslator::normalize($language);
        $context = $this->build_preview_context($language);

        return $this->render_template($template, $context, $language);
    }

    public function apply_branding(string $message, string $language): string
    {
        if ('' === trim($message)) {
            return '';
        }

        $emails = $this->options->get('fp_exp_emails', []);
        $emails = is_array($emails) ? $emails : [];
        $branding = isset($emails['branding']) && is_array($emails['branding']) ? $emails['branding'] : [];
        if (! $branding) {
            $branding = $this->options->get('fp_exp_email_branding', []);
            $branding = is_array($branding) ? $branding : [];
        }

        $logo = isset($branding['logo']) ? esc_url((string) $branding['logo']) : '';
        $logo_width  = isset($branding['logo_width']) ? (int) $branding['logo_width'] : 0;
        $logo_height = isset($branding['logo_height']) ? (int) $branding['logo_height'] : 0;
        $accent_color = isset($branding['accent_color']) && '' !== $branding['accent_color']
            ? (string) $branding['accent_color']
            : '#0b7285';
        $header_text = isset($branding['header_text']) ? trim((string) $branding['header_text']) : '';
        $footer_text = isset($branding['footer_text']) ? trim((string) $branding['footer_text']) : '';

        $site_name = (string) get_bloginfo('name');

        if ('' === $header_text) {
            $header_text = $site_name;
        }

        $logo_style = 'margin:0 auto 12px;display:block;';
        $logo_style .= $logo_width > 0 ? 'max-width:' . $logo_width . 'px;' : 'max-width:180px;';
        $logo_style .= $logo_height > 0 ? 'max-height:' . $logo_height . 'px;width:auto;height:auto;' : 'height:auto;';

        $accent_dark = self::darkenHex($accent_color, 30);

        ob_start();
        ?>
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
            <?php echo esc_html($header_text); ?>
        </div>
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;background-color:#eef2f7;">
            <tr>
                <td align="center" style="padding:28px 12px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="width:640px;max-width:640px;background-color:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe3ef;">
                        <tr>
                            <td style="background:linear-gradient(135deg,<?php echo esc_attr($accent_color); ?> 0%,<?php echo esc_attr($accent_dark); ?> 100%);padding:24px 28px;text-align:center;color:#ffffff;font-family:'Helvetica Neue',Arial,sans-serif;">
                                <?php if ($logo) : ?>
                                    <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($header_text); ?>" style="<?php echo esc_attr($logo_style); ?>" />
                                <?php endif; ?>
                                <?php if ($header_text) : ?>
                                    <p style="margin:0;font-size:19px;font-weight:700;letter-spacing:0.25px;"><?php echo esc_html($header_text); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:30px 30px 22px;color:#0f172a;font-family:'Helvetica Neue',Arial,sans-serif;line-height:1.75;font-size:15px;background:#ffffff;">
                                <?php echo wp_kses_post($message); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:18px 30px;background-color:#f8fafc;color:#475569;font-size:13px;text-align:center;font-family:'Helvetica Neue',Arial,sans-serif;border-top:1px solid #e2e8f0;">
                                <?php if ($footer_text) : ?>
                                    <p style="margin:0;"><?php echo nl2br(esc_html($footer_text)); ?></p>
                                <?php else : ?>
                                    <p style="margin:0;"><?php echo esc_html(EmailTranslator::text('common.default_footer', $language)); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <?php

        return trim((string) ob_get_clean());
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function darkenHex(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = max(0, (int) round(hexdec(substr($hex, 0, 2)) * (1 - $percent / 100)));
        $g = max(0, (int) round(hexdec(substr($hex, 2, 2)) * (1 - $percent / 100)));
        $b = max(0, (int) round(hexdec(substr($hex, 4, 2)) * (1 - $percent / 100)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function isTypeEnabled(string $type): bool
    {
        $emails_settings = $this->options->get('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];
        $types = isset($emails_settings['types']) && is_array($emails_settings['types']) ? $emails_settings['types'] : [];

        $value = $types[$type] ?? 'yes';

        return $value !== 'no' && $value !== '' && $value !== null;
    }

    /**
     * @param array<string, mixed>|null $slot
     * @return array<string, mixed>
     */
    private function build_reschedule_slot_snapshot(?array $slot): array
    {
        if (! is_array($slot)) {
            return [
                'start_local_date' => '',
                'start_local_time' => '',
                'end_local_time' => '',
                'timezone' => wp_timezone_string(),
            ];
        }

        $start_utc = isset($slot['start_datetime']) ? (string) $slot['start_datetime'] : '';
        $end_utc = isset($slot['end_datetime']) ? (string) $slot['end_datetime'] : '';
        $start_timestamp = $start_utc ? strtotime($start_utc . ' UTC') : 0;
        $end_timestamp = $end_utc ? strtotime($end_utc . ' UTC') : 0;
        $date_format = get_option('date_format', 'F j, Y');
        $time_format = get_option('time_format', 'H:i');

        return [
            'start_local_date' => $start_timestamp ? wp_date($date_format, $start_timestamp) : '',
            'start_local_time' => $start_timestamp ? wp_date($time_format, $start_timestamp) : '',
            'end_local_time' => $end_timestamp ? wp_date($time_format, $end_timestamp) : '',
            'timezone' => wp_timezone_string(),
        ];
    }

    private function scheduleAutomations(array $context): void
    {
        $reservation_id = absint((int) ($context['reservation']['id'] ?? 0));
        $order_id = absint((int) ($context['order']['id'] ?? 0));

        if ($reservation_id <= 0 || $order_id <= 0) {
            return;
        }

        // Brevo automation events (reminder/followup scheduling on Brevo side)
        $brevo = $this->resolveBrevo();
        if ($brevo !== null) {
            $brevo->queue_automation_events($context, $reservation_id);
        }

        $this->scheduler_service->scheduleNotifications($reservation_id, $order_id, $context);
    }

    /**
     * Lazy-resolve Brevo from the DI container (for reminder/followup transactional emails).
     */
    private function resolveBrevo(): ?Brevo
    {
        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel !== null) {
                $container = $kernel->container();
                if ($container->has(Brevo::class)) {
                    $brevo = $container->make(Brevo::class);
                    if ($brevo instanceof Brevo && $brevo->is_enabled()) {
                        return $brevo;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Brevo not available
        }

        return null;
    }

    /**
     * @param mixed $meta
     * @return array<string, string>
     */
    private function normalize_contact($meta, WC_Order $order): array
    {
        $meta = is_array($meta) ? $meta : [];

        return [
            'email' => sanitize_email((string) ($meta['email'] ?? $order->get_billing_email())),
            'first_name' => sanitize_text_field((string) ($meta['first_name'] ?? $order->get_billing_first_name())),
            'last_name' => sanitize_text_field((string) ($meta['last_name'] ?? $order->get_billing_last_name())),
            'phone' => sanitize_text_field((string) ($meta['phone'] ?? $order->get_billing_phone())),
        ];
    }

    /**
     * @param mixed $meta
     * @return array<string, string>
     */
    private function normalize_billing($meta, WC_Order $order): array
    {
        $meta = is_array($meta) ? $meta : [];

        return [
            'first_name' => sanitize_text_field((string) ($meta['first_name'] ?? $order->get_billing_first_name())),
            'last_name' => sanitize_text_field((string) ($meta['last_name'] ?? $order->get_billing_last_name())),
            'address' => sanitize_text_field((string) ($meta['address_1'] ?? $order->get_billing_address_1())),
            'city' => sanitize_text_field((string) ($meta['city'] ?? $order->get_billing_city())),
            'postcode' => sanitize_text_field((string) ($meta['postcode'] ?? $order->get_billing_postcode())),
            'country' => sanitize_text_field((string) ($meta['country'] ?? $order->get_billing_country())),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalize_tickets(int $experience_id, $pax): array
    {
        $pax = is_array($pax) ? $pax : [];
        $labels = $this->get_ticket_labels($experience_id);
        $output = [];

        foreach ($pax as $type => $quantity) {
            $type_key = sanitize_key((string) $type);
            $output[] = [
                'type' => $type_key,
                'label' => $labels[$type_key] ?? ucfirst(str_replace('_', ' ', $type_key)),
                'quantity' => absint($quantity),
            ];
        }

        return $output;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalize_addons(int $experience_id, $addons): array
    {
        $addons = is_array($addons) ? $addons : [];
        $labels = $this->get_addon_labels($experience_id);
        $output = [];

        foreach ($addons as $key => $addon) {
            $addon_key = sanitize_key((string) $key);
            $quantity = absint($addon['quantity'] ?? (is_numeric($addon) ? $addon : 0));

            if ($quantity <= 0) {
                continue;
            }

            $output[] = [
                'key' => $addon_key,
                'label' => $labels[$addon_key] ?? ucfirst(str_replace('_', ' ', $addon_key)),
                'quantity' => $quantity,
            ];
        }

        return $output;
    }

    /** @return array<string, string> */
    private function get_ticket_labels(int $experience_id): array
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

    /** @return array<string, string> */
    private function get_addon_labels(int $experience_id): array
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

    private function get_structure_email(): string
    {
        $emails = $this->options->get('fp_exp_emails', []);
        if (is_array($emails) && !empty($emails['sender']['structure'])) {
            $candidate = sanitize_email((string) $emails['sender']['structure']);
            if ($candidate) {
                return $candidate;
            }
        }

        $option = (string) $this->options->get('fp_exp_structure_email', '');
        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    private function render_template(string $template, array $context, ?string $language = null): string
    {
        $path = FP_EXP_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

        if (! file_exists($path)) {
            return '';
        }

        $language = $this->resolve_language($context, $language);

        ob_start();
        $email_context = $context;
        $email_language = $language;
        include $path;

        $message = (string) ob_get_clean();

        return $this->apply_branding($message, $language);
    }

    private function resolve_language(array $context, ?string $language = null): string
    {
        if (is_string($language) && '' !== trim($language)) {
            return EmailTranslator::normalize($language);
        }

        if (isset($context['language'])) {
            return EmailTranslator::normalize((string) $context['language']);
        }

        return $this->detect_language($context);
    }

    private function detect_language(array $context): string
    {
        $experience = isset($context['experience']) && is_array($context['experience']) ? $context['experience'] : [];
        $reservation = isset($context['reservation']) && is_array($context['reservation']) ? $context['reservation'] : [];

        $candidates = [];

        if (isset($experience['slug'])) {
            $candidates[] = (string) $experience['slug'];
        }
        if (isset($reservation['code'])) {
            $candidates[] = (string) $reservation['code'];
        }
        if (isset($experience['title'])) {
            $candidates[] = (string) $experience['title'];
        }

        foreach ($candidates as $candidate) {
            if ($this->has_ita_prefix($candidate)) {
                return EmailTranslator::LANGUAGE_IT;
            }
        }

        $locale = isset($context['locale']) ? strtolower((string) $context['locale']) : '';

        if ('' !== $locale && 0 === strpos($locale, 'it')) {
            return EmailTranslator::LANGUAGE_IT;
        }

        return EmailTranslator::LANGUAGE_EN;
    }

    private function has_ita_prefix(string $value): bool
    {
        $value = strtolower(trim($value));

        if ('' === $value) {
            return false;
        }

        $normalized = str_replace(['_', ' '], '-', $value);

        return 1 === preg_match('/^ita(?:$|[^a-z])/', $normalized);
    }

    /** @return array<string, mixed> */
    private function build_preview_context(string $language): array
    {
        $language = EmailTranslator::normalize($language);
        $is_italian = EmailTranslator::LANGUAGE_IT === $language;

        $start_timestamp = strtotime('+5 days 09:30:00');
        $end_timestamp = strtotime('+5 days 12:30:00');

        return [
            'reservation' => [
                'id' => 0,
                'status' => 'confirmed',
                'code' => $is_italian ? 'ITA-001' : 'EN-001',
            ],
            'experience' => [
                'id' => 0,
                'title' => $is_italian ? 'Degustazione in vigna' : 'Vineyard tasting',
                'permalink' => 'https://example.com/experience/demo',
                'meeting_point' => $is_italian ? 'Piazza del Duomo, Firenze' : 'Duomo Square, Florence',
                'short_description' => $is_italian
                    ? 'Scopri i sapori locali con una guida esperta.'
                    : 'Discover local flavours with an expert guide.',
                'slug' => $is_italian ? 'ita-demo-experience' : 'demo-experience',
            ],
            'slot' => [
                'start_utc' => '',
                'end_utc' => '',
                'start_iso' => $start_timestamp ? gmdate('c', $start_timestamp) : '',
                'end_iso' => $end_timestamp ? gmdate('c', $end_timestamp) : '',
                'start_local_date' => $start_timestamp ? wp_date('F j, Y', $start_timestamp) : '',
                'start_local_time' => $start_timestamp ? wp_date('H:i', $start_timestamp) : '',
                'end_local_time' => $end_timestamp ? wp_date('H:i', $end_timestamp) : '',
                'timezone' => 'Europe/Rome',
                'start_timestamp' => $start_timestamp ?: time(),
                'end_timestamp' => $end_timestamp ?: time(),
            ],
            'order' => [
                'id' => 0,
                'number' => $is_italian ? 'ITA123' : 'EN123',
                'total' => $is_italian ? '&euro;180,00' : '&euro;180.00',
                'currency' => 'EUR',
                'notes' => $is_italian ? 'Allergie da segnalare.' : 'Allergies to note.',
                'admin_url' => admin_url('edit.php?post_type=shop_order'),
            ],
            'customer' => [
                'name' => $is_italian ? 'Giulia Rossi' : 'Julia Ross',
                'email' => 'guest@example.com',
                'phone' => $is_italian ? '+39 055 1234567' : '+44 20 1234 5678',
                'first_name' => $is_italian ? 'Giulia' : 'Julia',
                'last_name' => $is_italian ? 'Rossi' : 'Ross',
            ],
            'tickets' => [
                ['label' => $is_italian ? 'Adulto' : 'Adult', 'quantity' => 2],
                ['label' => $is_italian ? 'Ragazzo' : 'Teen', 'quantity' => 1],
            ],
            'addons' => [
                ['label' => $is_italian ? 'Degustazione extra' : 'Extra tasting', 'quantity' => 1],
            ],
            'totals' => ['pax_total' => 3, 'gross' => 180.0, 'tax' => 0.0, 'total' => 180.0],
            'decline_reason' => $is_italian
                ? 'La data richiesta non è più disponibile.'
                : 'The requested date is no longer available.',
            'payment_url' => 'https://example.com/checkout/pay/?order_id=123',
            'consent' => ['marketing' => true],
            'ics' => ['content' => '', 'filename' => '', 'google_link' => 'https://calendar.google.com/calendar/r/eventedit'],
            'timers' => [
                'booked_timestamp' => time(),
                'booked_iso' => gmdate('c'),
                'reminder_timestamp' => $start_timestamp ? $start_timestamp - DAY_IN_SECONDS : 0,
                'reminder_iso' => $start_timestamp ? gmdate('c', $start_timestamp - DAY_IN_SECONDS) : '',
                'followup_timestamp' => $end_timestamp ? $end_timestamp + DAY_IN_SECONDS : 0,
                'followup_iso' => $end_timestamp ? gmdate('c', $end_timestamp + DAY_IN_SECONDS) : '',
            ],
            'language' => $language,
            'language_locale' => $is_italian ? 'it_IT' : 'en_US',
            'locale' => $is_italian ? 'it_IT' : 'en_US',
        ];
    }
}
