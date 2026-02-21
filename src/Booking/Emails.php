<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Booking\Email\Senders\CustomerEmailSender;
use FP_Exp\Booking\Email\Senders\StaffEmailSender;
use FP_Exp\Booking\Email\Services\EmailContextService;
use FP_Exp\Booking\Email\Services\EmailSchedulerService;
use FP_Exp\Booking\Email\Templates\BookingConfirmationTemplate;
use FP_Exp\Booking\Email\Templates\BookingFollowupTemplate;
use FP_Exp\Booking\Email\Templates\BookingReminderTemplate;
use FP_Exp\Booking\Email\Templates\StaffNotificationTemplate;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Booking\EmailTranslator;
use FP_Exp\MeetingPoints\Repository;
use FP_Exp\Services\Options\OptionsInterface;
use WC_Order;

use function __;
use function absint;
use function add_action;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_sum;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function function_exists;
use function get_bloginfo;
use function get_locale;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function strtotime;
use function preg_match;
use function str_replace;
use function strtolower;
use function strpos;
use function trim;
use function wc_get_order;
use function wp_date;
use function wp_mail;
use function wp_kses_post;
use function wp_strip_all_tags;
use function wp_timezone_string;
use function admin_url;
use function file_exists;
use function unlink;
use function esc_url;
use function nl2br;
use function gmdate;
use function max;
use function time;
use function wp_clear_scheduled_hook;
use function wp_schedule_single_event;
use const DAY_IN_SECONDS;
use const MINUTE_IN_SECONDS;
use const HOUR_IN_SECONDS;

final class Emails implements HookableInterface
{
    private const REMINDER_HOOK = 'fp_exp_email_send_reminder';
    private const FOLLOWUP_HOOK = 'fp_exp_email_send_followup';

    private ?Brevo $brevo = null;
    private ?OptionsInterface $options = null;

    // Refactored services
    private ?EmailContextService $context_service = null;
    private ?EmailSchedulerService $scheduler_service = null;
    private ?CustomerEmailSender $customer_sender = null;
    private ?StaffEmailSender $staff_sender = null;

    /**
     * Emails constructor.
     *
     * @param Brevo|null $brevo Optional Brevo integration
     * @param OptionsInterface|null $options Optional OptionsInterface (will try to get from container if not provided)
     */
    public function __construct(?Brevo $brevo = null, ?OptionsInterface $options = null)
    {
        $this->brevo = $brevo;
        $this->options = $options;
    }

    /**
     * Get OptionsInterface instance.
     * Tries container first, falls back to direct instantiation for backward compatibility.
     */
    private function getOptions(): OptionsInterface
    {
        if ($this->options !== null) {
            return $this->options;
        }

        // Try to get from container
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(OptionsInterface::class)) {
                try {
                    $this->options = $container->make(OptionsInterface::class);
                    return $this->options;
                } catch (\Throwable $e) {
                    // Fall through to direct instantiation
                }
            }
        }

        // Fallback to direct instantiation
        $this->options = new \FP_Exp\Services\Options\Options();
        return $this->options;
    }

    /**
     * Initialize services (lazy loading).
     */
    private function initServices(): void
    {
        if ($this->context_service === null) {
            $this->context_service = new EmailContextService();
            $this->scheduler_service = new EmailSchedulerService();
            $this->customer_sender = new CustomerEmailSender($this->brevo);
            $this->staff_sender = new StaffEmailSender($this->brevo);
        }
    }

    public function register_hooks(): void
    {
        add_action('fp_exp_reservation_created', [$this, 'handle_reservation_created'], 10, 2);
        add_action('fp_exp_reservation_paid', [$this, 'handle_reservation_paid'], 10, 2);
        add_action('fp_exp_reservation_cancelled', [$this, 'handle_reservation_cancelled'], 10, 2);
        add_action(self::REMINDER_HOOK, [$this, 'handle_reminder_dispatch'], 10, 2);
        add_action(self::FOLLOWUP_HOOK, [$this, 'handle_followup_dispatch'], 10, 2);
    }

    /**
     * Handle reservation created — sends immediate staff notification so
     * the team knows about the booking even before payment completes.
     */
    public function handle_reservation_created(int $reservation_id, int $order_id): void
    {
        $this->initServices();
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $emails_settings = $this->getOptions()->get('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];
        $types = isset($emails_settings['types']) && is_array($emails_settings['types']) ? $emails_settings['types'] : [];

        foreach (['customer_confirmation', 'staff_notification', 'customer_reminder', 'customer_post_experience'] as $key) {
            if (isset($types[$key]) && ($types[$key] === '' || $types[$key] === null)) {
                $types[$key] = 'no';
            }
        }

        $staff_notification = $types['staff_notification'] ?? 'yes';
        if ($staff_notification !== 'no') {
            $template = new StaffNotificationTemplate(false);
            $this->staff_sender->send($template, $context);
        }
    }

    /**
     * Handle reservation paid (delegated to new email system).
     *
     * @deprecated Use EmailManager::handleReservationPaid() instead
     */
    public function handle_reservation_paid(int $reservation_id, int $order_id): void
    {
        $this->initServices();
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $emails_settings = $this->getOptions()->get('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];
        $types = isset($emails_settings['types']) && is_array($emails_settings['types']) ? $emails_settings['types'] : [];

        // Normalize types: ensure empty strings are treated as 'no'
        foreach (['customer_confirmation', 'staff_notification', 'customer_reminder', 'customer_post_experience'] as $key) {
            if (isset($types[$key]) && ($types[$key] === '' || $types[$key] === null)) {
                $types[$key] = 'no';
            }
        }

        // Send customer confirmation (default: 'yes' if not set)
        $customer_confirmation = $types['customer_confirmation'] ?? 'yes';
        if ($customer_confirmation !== 'no') {
            $template = new BookingConfirmationTemplate();
            $this->customer_sender->send($template, $context);
        }

        // Staff notification is already sent on reservation_created;
        // no duplicate here to avoid double emails on instant payments.

        $this->queue_automations($context);
    }

    /**
     * Handle reservation cancelled (delegated to new email system).
     *
     * @deprecated Use EmailManager::handleReservationCancelled() instead
     */
    public function handle_reservation_cancelled(int $reservation_id, int $order_id): void
    {
        $this->initServices();
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $language = $this->resolve_language($context);
        $context['language'] = $language;
        $context['status_label'] = EmailTranslator::text('common.status_cancelled', $language);

        $emails_settings = $this->getOptions()->get('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];
        $types = isset($emails_settings['types']) && is_array($emails_settings['types']) ? $emails_settings['types'] : [];

        // Normalize types: ensure empty strings are treated as 'no'
        foreach (['customer_confirmation', 'staff_notification', 'customer_reminder', 'customer_post_experience'] as $key) {
            if (isset($types[$key]) && ($types[$key] === '' || $types[$key] === null)) {
                $types[$key] = 'no';
            }
        }

        // Send staff notification for cancellation (default: 'yes' if not set)
        $staff_notification = $types['staff_notification'] ?? 'yes';
        if ($staff_notification !== 'no') {
            $template = new StaffNotificationTemplate(true);
            $this->staff_sender->send($template, $context);
        }

        $this->scheduler_service->cancelNotifications($reservation_id, $order_id);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_customer_confirmation(array $context): void
    {
        $this->dispatch_customer_confirmation($context, false);
    }

    /**
     * Allow Brevo failures to fall back to local Woo mailer delivery.
     *
     * @param array<string, mixed> $context
     */
    /**
     * Send customer confirmation fallback (delegated to CustomerEmailSender).
     *
     * @deprecated Use CustomerEmailSender::send() with force_send=true instead
     *
     * @param array<string, mixed> $context
     */
    public function send_customer_confirmation_fallback(array $context): void
    {
        $this->initServices();
        $template = new BookingConfirmationTemplate();
        $this->customer_sender->send($template, $context, true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function dispatch_customer_confirmation(array $context, bool $force_send): void
    {
        $recipient = $context['customer']['email'] ?? '';

        if (! $recipient) {
            return;
        }

        $language = $this->resolve_language($context);

        if (! $force_send && $this->brevo instanceof Brevo && $this->brevo->is_enabled()) {
            return;
        }

        $subject = $this->resolve_subject_override('customer_confirmation', $context, $language);

        $message = $this->render_template('customer-confirmation', $context, $language);

        if ('' === trim($message)) {
            return;
        }

        $attachments = $this->prepare_attachments($context);

        $this->dispatch([$recipient], $subject, $message, $attachments);

        $this->cleanup_attachments($attachments);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_staff_notification(array $context, bool $is_cancelled): void
    {
        $recipients = $this->resolve_staff_recipients($context);

        if (! $recipients) {
            return;
        }

        $language = $this->resolve_language($context);

        if ($is_cancelled) {
            $subject = $this->resolve_subject_override('staff_notification_cancelled', $context, $language);
        } else {
            $subject = $this->resolve_subject_override('staff_notification_new', $context, $language);
        }

        $message = $this->render_template('staff-notification', $context + [
            'is_cancelled' => $is_cancelled,
        ], $language);

        if ('' === trim($message)) {
            return;
        }

        $attachments = $is_cancelled ? [] : $this->prepare_attachments($context);

        $this->dispatch($recipients, $subject, $message, $attachments);

        $this->cleanup_attachments($attachments);
    }

    /**
     * Queue automations (Brevo or internal scheduling).
     *
     * @param array<string, mixed> $context
     */
    private function queue_automations(array $context): void
    {
        $reservation_id = absint((int) ($context['reservation']['id'] ?? 0));
        $order_id = absint((int) ($context['order']['id'] ?? 0));

        if ($reservation_id <= 0 || $order_id <= 0) {
            return;
        }

        if ($this->brevo instanceof Brevo && $this->brevo->is_enabled()) {
            $this->brevo->queue_automation_events($context, $reservation_id);

            return;
        }

        $this->initServices();
        $this->scheduler_service->scheduleNotifications($reservation_id, $order_id, $context);
    }

    /**
     * Schedule internal notifications (delegated to EmailSchedulerService).
     *
     * @deprecated Use EmailSchedulerService::scheduleNotifications() instead
     *
     * @param array<string, mixed> $context
     */
    private function schedule_internal_notifications(int $reservation_id, int $order_id, array $context): void
    {
        $this->initServices();
        $this->scheduler_service->scheduleNotifications($reservation_id, $order_id, $context);
    }

    /**
     * Handle reminder dispatch (delegated to new email system).
     *
     * @deprecated Use EmailManager::handleReminderDispatch() instead
     */
    public function handle_reminder_dispatch(int $reservation_id, int $order_id): void
    {
        $this->initServices();
        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
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

        $language = $this->resolve_language($context);

        $subject = $this->resolve_subject_override('customer_post_experience', $context, $language);

        $this->send_customer_template($context, 'customer-post-experience', $subject, false, $language);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_customer_template(array $context, string $template, string $subject, bool $with_attachments, ?string $language = null): void
    {
        $recipient = $context['customer']['email'] ?? '';

        if (! $recipient) {
            return;
        }

        $language = $this->resolve_language($context, $language);

        $message = $this->render_template($template, $context, $language);

        if ('' === trim($message)) {
            return;
        }

        $attachments = $with_attachments ? $this->prepare_attachments($context) : [];

        $this->dispatch([(string) $recipient], $subject, $message, $attachments);

        if ($attachments) {
            $this->cleanup_attachments($attachments);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_context(int $reservation_id, int $order_id): ?array
    {
        $reservation = Reservations::get($reservation_id);

        if (! $reservation) {
            return null;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return null;
        }

        $experience = get_post(absint($reservation['experience_id'] ?? 0));

        if (! $experience) {
            return null;
        }

        $slot = Slots::get_slot(absint($reservation['slot_id'] ?? 0));

        if (! $slot) {
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
                /* translators: %s: experience title. */
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
     * @param mixed $meta
     *
     * @return array<string, string>
     */
    private function normalize_contact($meta, WC_Order $order): array
    {
        $meta = is_array($meta) ? $meta : [];

        $email = sanitize_email((string) ($meta['email'] ?? $order->get_billing_email()));
        $first_name = sanitize_text_field((string) ($meta['first_name'] ?? $order->get_billing_first_name()));
        $last_name = sanitize_text_field((string) ($meta['last_name'] ?? $order->get_billing_last_name()));
        $phone = sanitize_text_field((string) ($meta['phone'] ?? $order->get_billing_phone()));

        return [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
        ];
    }

    /**
     * @param mixed $meta
     *
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
     * @param array<string, mixed> $pax
     *
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
     * @param mixed $addons
     *
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

    /**
     * @return array<string, string>
     */
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

            if (! $key) {
                continue;
            }

            $labels[$key] = sanitize_text_field((string) ($entry['label'] ?? $entry['name'] ?? ucfirst($key)));
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
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

            if (! $key) {
                continue;
            }

            $labels[$key] = sanitize_text_field((string) ($entry['label'] ?? $entry['name'] ?? ucfirst($key)));
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    private function resolve_staff_recipients(array $context): array
    {
        $structure = $this->get_structure_email();
        $webmaster = $this->get_webmaster_email();

        $recipients = array_filter([
            $structure,
            $webmaster,
        ]);

        // Aggiungi destinatari extra da impostazioni
        $emails_settings = $this->getOptions()->get('fp_exp_emails', []);
        if (is_array($emails_settings) && ! empty($emails_settings['recipients']['staff_extra']) && is_array($emails_settings['recipients']['staff_extra'])) {
            $extra = array_values(array_filter(array_map('sanitize_email', $emails_settings['recipients']['staff_extra'])));
            $recipients = array_merge($recipients, $extra);
        }

        /** @var array<int, string> $filtered */
        $filtered = apply_filters('fp_exp_email_recipients', $recipients, $context, 'staff');

        $filtered = array_map('sanitize_email', $filtered);

        return array_values(array_filter($filtered));
    }

    private function get_structure_email(): string
    {
        $emails = $this->getOptions()->get('fp_exp_emails', []);
        if (is_array($emails) && ! empty($emails['sender']['structure'])) {
            $candidate = sanitize_email((string) $emails['sender']['structure']);
            if ($candidate) {
                return $candidate;
            }
        }

        $option = (string) $this->getOptions()->get('fp_exp_structure_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    private function get_webmaster_email(): string
    {
        $emails = $this->getOptions()->get('fp_exp_emails', []);
        if (is_array($emails) && ! empty($emails['sender']['webmaster'])) {
            $candidate = sanitize_email((string) $emails['sender']['webmaster']);
            if ($candidate) {
                return $candidate;
            }
        }

        $option = (string) $this->getOptions()->get('fp_exp_webmaster_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<int, string>
     */
    private function prepare_attachments(array $context): array
    {
        $ics = $context['ics']['content'] ?? '';
        $filename = $context['ics']['filename'] ?? 'fp-experience.ics';

        if (! is_string($ics) || '' === trim($ics)) {
            return [];
        }

        $path = ICS::create_file($ics, $filename);

        if (! $path) {
            return [];
        }

        return [$path];
    }

    /**
     * @param array<int, string> $attachments
     */
    private function cleanup_attachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                @unlink($attachment);
            }
        }
    }

    public function render_preview(string $template, string $language = EmailTranslator::LANGUAGE_IT): string
    {
        $language = EmailTranslator::normalize($language);
        $context = $this->build_preview_context($language);

        return $this->render_template($template, $context, $language);
    }

    /**
     * @return array<string, mixed>
     */
    private function build_preview_context(string $language): array
    {
        $language = EmailTranslator::normalize($language);
        $is_italian = EmailTranslator::LANGUAGE_IT === $language;

        $experience_title = $is_italian ? 'Degustazione in vigna' : 'Vineyard tasting';
        $meeting_point = $is_italian ? 'Piazza del Duomo, Firenze' : 'Duomo Square, Florence';
        $short_description = $is_italian
            ? 'Scopri i sapori locali con una guida esperta.'
            : 'Discover local flavours with an expert guide.';
        $reservation_code = $is_italian ? 'ITA-001' : 'EN-001';
        $locale = $is_italian ? 'it_IT' : 'en_US';

        $start_timestamp = strtotime('+5 days 09:30:00');
        $end_timestamp = strtotime('+5 days 12:30:00');

        return [
            'reservation' => [
                'id' => 0,
                'status' => 'confirmed',
                'code' => $reservation_code,
            ],
            'experience' => [
                'id' => 0,
                'title' => $experience_title,
                'permalink' => 'https://example.com/experience/demo',
                'meeting_point' => $meeting_point,
                'short_description' => $short_description,
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
                [
                    'label' => $is_italian ? 'Adulto' : 'Adult',
                    'quantity' => 2,
                ],
                [
                    'label' => $is_italian ? 'Ragazzo' : 'Teen',
                    'quantity' => 1,
                ],
            ],
            'addons' => [
                [
                    'label' => $is_italian ? 'Degustazione extra' : 'Extra tasting',
                    'quantity' => 1,
                ],
            ],
            'totals' => [
                'pax_total' => 3,
                'gross' => 180.0,
                'tax' => 0.0,
            ],
            'consent' => [
                'marketing' => true,
            ],
            'ics' => [
                'content' => '',
                'filename' => '',
                'google_link' => 'https://calendar.google.com/calendar/r/eventedit',
            ],
            'timers' => [
                'booked_timestamp' => time(),
                'booked_iso' => gmdate('c'),
                'reminder_timestamp' => $start_timestamp ? $start_timestamp - DAY_IN_SECONDS : 0,
                'reminder_iso' => $start_timestamp ? gmdate('c', $start_timestamp - DAY_IN_SECONDS) : '',
                'followup_timestamp' => $end_timestamp ? $end_timestamp + DAY_IN_SECONDS : 0,
                'followup_iso' => $end_timestamp ? gmdate('c', $end_timestamp + DAY_IN_SECONDS) : '',
            ],
            'language' => $language,
            'language_locale' => $locale,
            'locale' => $locale,
        ];
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

    /**
     * @param array<int, string> $recipients
     * @param array<int, string> $attachments
     */
    private function dispatch(array $recipients, string $subject, string $message, array $attachments = []): void
    {
        $recipients = array_values(array_filter(array_map('sanitize_email', $recipients)));

        if (! $recipients) {
            return;
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $to = implode(',', $recipients);

        if (function_exists('wc')) {
            $mailer = \wc()->mailer();
            $mailer->send($to, $subject, $message, $headers, $attachments);

            return;
        }

        wp_mail($to, $subject, $message, $headers, $attachments);
    }

    private function render_template(string $template, array $context, ?string $language = null): string
    {
        // Debug logging per verificare il rendering dei template
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp Emails] render_template() called for: ' . $template);
        }

        $path = FP_EXP_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

        if (! file_exists($path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-Exp Emails] Template NOT FOUND: ' . $path);
            }
            return '';
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp Emails] Template found: ' . $path);
        }

        $language = $this->resolve_language($context, $language);

        ob_start();
        $email_context = $context;
        $email_language = $language;
        include $path;

        $message = (string) ob_get_clean();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp Emails] Message length after include: ' . strlen($message));
        }

        return $this->apply_branding($message, $language);
    }

    private function apply_branding(string $message, string $language): string
    {
        if ('' === trim($message)) {
            return '';
        }

        $emails = $this->getOptions()->get('fp_exp_emails', []);
        $emails = is_array($emails) ? $emails : [];
        $branding = isset($emails['branding']) && is_array($emails['branding']) ? $emails['branding'] : [];
        if (! $branding) {
            $branding = $this->getOptions()->get('fp_exp_email_branding', []);
            $branding = is_array($branding) ? $branding : [];
        }

        $logo = isset($branding['logo']) ? esc_url((string) $branding['logo']) : '';
        $header_text = isset($branding['header_text']) ? trim((string) $branding['header_text']) : '';
        $footer_text = isset($branding['footer_text']) ? trim((string) $branding['footer_text']) : '';

        $site_name = (string) get_bloginfo('name');

        if ('' === $header_text) {
            $header_text = $site_name;
        }

        ob_start();
        ?>
        <div style="margin:0;padding:0;background-color:#f1f5f9;">
            <div style="max-width:640px;margin:0 auto;padding:24px;">
                <div style="border-radius:24px;overflow:hidden;background-color:#ffffff;box-shadow:0 24px 50px rgba(15,23,42,0.12);">
                    <div style="background:linear-gradient(135deg,#0b7285 0%,#0f4c81 100%);padding:24px 32px;text-align:center;color:#ffffff;font-family:'Helvetica Neue',Arial,sans-serif;">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($header_text); ?>" style="max-width:180px;height:auto;margin:0 auto 12px;display:block;" />
                        <?php endif; ?>
                        <?php if ($header_text) : ?>
                            <p style="margin:0;font-size:18px;font-weight:600;letter-spacing:0.3px;"><?php echo esc_html($header_text); ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="padding:32px 32px 24px;color:#0f172a;font-family:'Helvetica Neue',Arial,sans-serif;line-height:1.7;font-size:15px;">
                        <?php echo wp_kses_post($message); ?>
                    </div>
                    <div style="padding:20px 32px;background-color:#f8fafc;color:#475569;font-size:13px;text-align:center;font-family:'Helvetica Neue',Arial,sans-serif;">
                        <?php if ($footer_text) : ?>
                            <p style="margin:0;"><?php echo nl2br(esc_html($footer_text)); ?></p>
                        <?php else : ?>
                            <p style="margin:0;">
                                <?php echo esc_html(EmailTranslator::text('common.default_footer', $language)); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolve_subject_override(string $key, array $context, string $language): string
    {
        $emails_settings = $this->getOptions()->get('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];
        $subjects = isset($emails_settings['subjects']) && is_array($emails_settings['subjects']) ? $emails_settings['subjects'] : [];

        $experience_title = (string) ($context['experience']['title'] ?? '');
        $date_label = (string) ($context['slot']['start_local_date'] ?? '');
        $time_label = (string) ($context['slot']['start_local_time'] ?? '');
        $order_number = (string) ($context['order']['number'] ?? $context['order']['id'] ?? '');

        $override = isset($subjects[$key]) ? (string) $subjects[$key] : '';
        if ('' !== trim($override)) {
            return str_replace(
                ['{experience_title}', '{date}', '{time}', '{order_number}'],
                [$experience_title, $date_label, $time_label, $order_number],
                $override
            );
        }

        // fallback a traduzioni esistenti
        switch ($key) {
            case 'customer_confirmation':
                return EmailTranslator::text('customer_confirmation.subject', $language, [$experience_title]);
            case 'customer_reminder':
                return EmailTranslator::text('customer_reminder.subject', $language, [$experience_title]);
            case 'customer_post_experience':
                return EmailTranslator::text('customer_post_experience.subject', $language, [$experience_title]);
            case 'staff_notification_new':
                return EmailTranslator::text('staff_notification.subject_new', $language, [$experience_title]);
            case 'staff_notification_cancelled':
                return EmailTranslator::text('staff_notification.subject_cancelled', $language, [$experience_title]);
        }

        return '';
    }
}
