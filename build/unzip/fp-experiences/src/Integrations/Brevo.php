<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Booking\EmailTranslator;
use FP_Exp\Booking\Emails;
use FP_Exp\Booking\Reservations;
use FP_Exp\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;

use function absint;
use function add_action;
use function apply_filters;
use function array_filter;
use function base64_encode;
use function esc_url_raw;
use function get_option;
use function get_post_field;
use function get_transient;
use function hash_equals;
use function is_array;
use function is_numeric;
use function is_string;
use function is_wp_error;
use function register_rest_route;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function set_transient;
use function time;
use function max;
use function round;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use const MINUTE_IN_SECONDS;
use function __;

final class Brevo
{
    private ?Emails $emails = null;

    public function register_hooks(): void
    {
        add_action('fp_exp_reservation_paid', [$this, 'handle_reservation_paid'], 20, 2);
        add_action('fp_exp_reservation_cancelled', [$this, 'handle_reservation_cancelled'], 20, 2);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function set_email_service(Emails $emails): void
    {
        $this->emails = $emails;
    }

    public function is_enabled(): bool
    {
        $settings = $this->get_settings();

        return ! empty($settings['enabled']) && ! empty($settings['api_key']);
    }

    /**
     * @return array<string, mixed>
     */
    public function get_settings(): array
    {
        $settings = get_option('fp_exp_brevo', []);

        return is_array($settings) ? $settings : [];
    }

    public function handle_reservation_paid(int $reservation_id, int $order_id): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        $context = $this->get_context($reservation_id, $order_id);
        if (! $context) {
            return;
        }

        $this->sync_contact($context, $reservation_id);
        $sent = $this->send_transactional('confirmation', $context, $reservation_id);

        if (! $sent && $this->emails instanceof Emails) {
            Logger::log('brevo', 'Transactional fallback triggered', [
                'reservation' => $reservation_id,
                'reason' => 'confirmation_send_failed',
            ]);
            $this->emails->send_customer_confirmation_fallback($context);
        }

        $this->send_event('reservation_paid', $context, $reservation_id);
    }

    public function handle_reservation_cancelled(int $reservation_id, int $order_id): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        $context = $this->get_context($reservation_id, $order_id);
        if (! $context) {
            return;
        }

        $context['status_label'] = 'cancelled';
        $this->send_transactional('cancel', $context, $reservation_id);
        $this->send_event('reservation_cancelled', $context, $reservation_id);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function queue_automation_events(array $context, int $reservation_id): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        $timers = isset($context['timers']) && is_array($context['timers']) ? $context['timers'] : [];
        $slot = isset($context['slot']) && is_array($context['slot']) ? $context['slot'] : [];

        $start_timestamp = isset($slot['start_timestamp']) ? absint((int) $slot['start_timestamp']) : 0;
        $end_timestamp = isset($slot['end_timestamp']) ? absint((int) $slot['end_timestamp']) : $start_timestamp;

        $reminder_timestamp = isset($timers['reminder_timestamp']) ? absint((int) $timers['reminder_timestamp']) : 0;
        if ($reminder_timestamp > 0) {
            $offset_minutes = ($start_timestamp > 0 && $reminder_timestamp > 0)
                ? max(0, (int) round(($start_timestamp - $reminder_timestamp) / 60))
                : 0;

            $this->send_event('reservation_reminder_scheduled', $context, $reservation_id, [
                'trigger_at' => sanitize_text_field((string) ($timers['reminder_iso'] ?? '')),
                'trigger_timestamp' => $reminder_timestamp,
                'trigger_local_date' => sanitize_text_field((string) ($timers['reminder_local_date'] ?? '')),
                'trigger_local_time' => sanitize_text_field((string) ($timers['reminder_local_time'] ?? '')),
                'trigger_offset_minutes' => $offset_minutes,
                'schedule_label' => '24h_before_start',
            ]);
        }

        $followup_timestamp = isset($timers['followup_timestamp']) ? absint((int) $timers['followup_timestamp']) : 0;
        if ($followup_timestamp > 0) {
            $offset_minutes = ($followup_timestamp > 0 && $end_timestamp > 0)
                ? max(0, (int) round(($followup_timestamp - $end_timestamp) / 60))
                : 0;

            $this->send_event('reservation_followup_scheduled', $context, $reservation_id, [
                'trigger_at' => sanitize_text_field((string) ($timers['followup_iso'] ?? '')),
                'trigger_timestamp' => $followup_timestamp,
                'trigger_local_date' => sanitize_text_field((string) ($timers['followup_local_date'] ?? '')),
                'trigger_local_time' => sanitize_text_field((string) ($timers['followup_local_time'] ?? '')),
                'trigger_offset_minutes' => $offset_minutes,
                'schedule_label' => '24h_after_experience',
            ]);
        }
    }

    public function register_rest_routes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/brevo/webhook',
            [
                'methods' => ['POST'],
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * @return WP_REST_Response
     */
    public function handle_webhook(WP_REST_Request $request)
    {
        $settings = $this->get_settings();
        $secret = isset($settings['webhook_secret']) ? sanitize_text_field((string) $settings['webhook_secret']) : '';

        if ('' !== $secret) {
            $provided = sanitize_text_field((string) $request->get_param('secret'));
            if ('' === $provided) {
                $provided = sanitize_text_field((string) $request->get_header('x-brevo-secret'));
            }

            if (! $provided || ! hash_equals($secret, $provided)) {
                Logger::log('brevo_webhook', 'Webhook rejected: invalid secret', [
                    'event' => 'unknown',
                ]);

                return new WP_REST_Response(['received' => false], 403);
            }
        }

        $payload = $request->get_json_params();
        $event = '';
        $message_id = '';

        if (is_array($payload)) {
            $event = sanitize_key((string) ($payload['event'] ?? ''));
            $message_id = sanitize_text_field((string) ($payload['message-id'] ?? ''));
        }

        Logger::log('brevo_webhook', 'Webhook received', [
            'event' => $event,
            'message_id' => $message_id,
        ]);

        return new WP_REST_Response(['received' => true]);
    }

    private function get_context(int $reservation_id, int $order_id): ?array
    {
        if ($this->emails instanceof Emails) {
            $context = $this->emails->get_context($reservation_id, $order_id);
            if (is_array($context)) {
                return $context;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sync_contact(array $context, int $reservation_id): void
    {
        $settings = $this->get_settings();
        $api_key = (string) ($settings['api_key'] ?? '');

        if (! $api_key) {
            return;
        }

        $customer = $context['customer'] ?? [];
        if (! is_array($customer)) {
            return;
        }

        $email = sanitize_email((string) ($customer['email'] ?? ''));
        if (! $email) {
            return;
        }

        $payload = $this->build_contact_payload($context, $reservation_id);
        $payload = apply_filters('fp_exp_brevo_contact_payload', $payload, $context, $reservation_id);

        $endpoint = 'https://api.brevo.com/v3/contacts';
        $response = wp_remote_post($endpoint, [
            'headers' => $this->build_headers($api_key),
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400) {
            Logger::log('brevo', 'Contact sync failed', [
                'reservation' => $reservation_id,
                'status' => $code,
                'body' => wp_remote_retrieve_body($response),
            ]);

            $this->record_notice(
                'contact_sync',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('Brevo contact sync failed (HTTP %d). Check the logs for details.', 'fp-experiences'),
                    $code
                ),
                'error'
            );
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_transactional(string $template_key, array $context, int $reservation_id, ?int $override_template_id = null): bool
    {
        $settings = $this->get_settings();
        $api_key = (string) ($settings['api_key'] ?? '');

        if (! $api_key) {
            Logger::log('brevo', 'Transactional skipped: missing API key', [
                'reservation' => $reservation_id,
                'template_key' => $template_key,
            ]);

            return false;
        }

        $template_id = $override_template_id;

        if (null === $template_id) {
            $templates = is_array($settings['templates'] ?? null) ? $settings['templates'] : [];
            $template_id = $templates[$template_key] ?? null;
        }

        if (! is_numeric($template_id) || (int) $template_id <= 0) {
            Logger::log('brevo', 'Transactional skipped: template not configured', [
                'reservation' => $reservation_id,
                'template_key' => $template_key,
            ]);

            $this->record_notice(
                'template_' . sanitize_key($template_key),
                __('Transactional email template not configured. WooCommerce fallback used.', 'fp-experiences')
            );

            return false;
        }

        $customer = $context['customer'] ?? [];
        if (! is_array($customer) || empty($customer['email'])) {
            return false;
        }

        $ics_content = '';
        $ics_filename = 'fp-experience.ics';
        $event_link = '';

        if (isset($context['ics']) && is_array($context['ics'])) {
            $ics_content = (string) ($context['ics']['content'] ?? '');
            $ics_filename = (string) ($context['ics']['filename'] ?? 'fp-experience.ics');
            $event_link = (string) ($context['ics']['google_link'] ?? '');
        }

        $payload = [
            'to' => [
                [
                    'email' => sanitize_email((string) $customer['email']),
                    'name' => sanitize_text_field((string) ($customer['name'] ?? '')),
                ],
            ],
            'templateId' => (int) $template_id,
            'params' => [
                'experience_name' => sanitize_text_field((string) ($context['experience']['title'] ?? '')),
                'experience_date' => sanitize_text_field((string) ($context['slot']['start_local_date'] ?? '')),
                'experience_time' => sanitize_text_field((string) ($context['slot']['start_local_time'] ?? '')),
                'meeting_point' => sanitize_text_field((string) ($context['experience']['meeting_point'] ?? '')),
                'order_id' => sanitize_text_field((string) ($context['order']['number'] ?? '')),
                'total' => sanitize_text_field((string) ($context['order']['total'] ?? '')),
                'pax_total' => absint((int) ($context['totals']['pax_total'] ?? $context['totals']['guests'] ?? 0)),
                'addons' => $this->render_addons_summary($context),
                'google_calendar_url' => esc_url_raw($event_link),
            ],
        ];

        if ('' !== $ics_content) {
            $payload['attachment'] = [
                [
                    'content' => base64_encode($ics_content),
                    'name' => $ics_filename,
                ],
            ];
        }

        $payload = apply_filters('fp_exp_brevo_tx_payload', $payload, $template_key, $context, $reservation_id);

        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
            'headers' => $this->build_headers($api_key),
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            Logger::log('brevo', 'Transactional send failed', [
                'reservation' => $reservation_id,
                'error' => $response->get_error_message(),
            ]);

            $this->record_notice(
                'tx_error',
                __('Brevo transactional email failed to send. Check the logs for details.', 'fp-experiences'),
                'error'
            );

            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            Logger::log('brevo', 'Transactional send failed', [
                'reservation' => $reservation_id,
                'status' => $code,
                'body' => wp_remote_retrieve_body($response),
            ]);

            $this->record_notice(
                'tx_http',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('Brevo transactional email failed with HTTP %d. Check the logs for details.', 'fp-experiences'),
                    $code
                ),
                'error'
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send_event(string $event, array $context, int $reservation_id, array $additional_properties = []): void
    {
        $settings = $this->get_settings();
        $api_key = (string) ($settings['api_key'] ?? '');

        if (! $api_key) {
            return;
        }

        $customer = $context['customer'] ?? [];
        if (! is_array($customer)) {
            return;
        }

        $email = sanitize_email((string) ($customer['email'] ?? ''));
        if (! $email) {
            return;
        }

        $properties = $this->build_event_properties($context, $reservation_id);

        if ($additional_properties) {
            foreach ($additional_properties as $key => $value) {
                if (is_string($value)) {
                    $additional_properties[$key] = sanitize_text_field($value);
                }
            }

            $properties = array_merge($properties, $additional_properties);
        }

        $properties = array_filter(
            $properties,
            static fn ($value) => is_numeric($value) || ('' !== $value && null !== $value)
        );

        $payload = [
            'email' => $email,
            'event' => sanitize_key($event),
            'properties' => $properties,
        ];

        $payload = apply_filters('fp_exp_brevo_event_payload', $payload, $event, $context, $reservation_id);

        $response = wp_remote_post('https://in-automate.brevo.com/api/v2/trackEvent', [
            'headers' => $this->build_headers($api_key),
            'body' => wp_json_encode($payload),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            Logger::log('brevo', 'Event send failed', [
                'reservation' => $reservation_id,
                'event' => $event,
                'error' => $response->get_error_message(),
            ]);

            $this->record_notice(
                'event_error',
                __('Brevo event tracking failed. Check the logs for diagnostics.', 'fp-experiences'),
                'warning'
            );

            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            Logger::log('brevo', 'Event send failed', [
                'reservation' => $reservation_id,
                'event' => $event,
                'status' => $code,
                'body' => wp_remote_retrieve_body($response),
            ]);

            $this->record_notice(
                'event_http',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('Brevo event tracking request returned HTTP %d.', 'fp-experiences'),
                    $code
                ),
                'warning'
            );
        }
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function build_event_properties(array $context, int $reservation_id): array
    {
        $slot = isset($context['slot']) && is_array($context['slot']) ? $context['slot'] : [];
        $order = isset($context['order']) && is_array($context['order']) ? $context['order'] : [];
        $experience = isset($context['experience']) && is_array($context['experience']) ? $context['experience'] : [];
        $totals = isset($context['totals']) && is_array($context['totals']) ? $context['totals'] : [];
        $timers = isset($context['timers']) && is_array($context['timers']) ? $context['timers'] : [];

        $reminder_offset = isset($timers['reminder_offset']) ? (int) $timers['reminder_offset'] : 0;
        $followup_offset = isset($timers['followup_offset']) ? (int) $timers['followup_offset'] : 0;

        return [
            'reservation_id' => $reservation_id,
            'order_id' => absint((int) ($order['id'] ?? 0)),
            'order_number' => sanitize_text_field((string) ($order['number'] ?? '')),
            'experience_id' => absint((int) ($experience['id'] ?? 0)),
            'experience_name' => sanitize_text_field((string) ($experience['title'] ?? '')),
            'experience_permalink' => esc_url_raw((string) ($experience['permalink'] ?? '')),
            'language' => sanitize_text_field((string) ($context['language'] ?? '')),
            'language_locale' => sanitize_text_field((string) ($context['language_locale'] ?? '')),
            'start_iso' => sanitize_text_field((string) ($slot['start_iso'] ?? '')),
            'start_timestamp' => absint((int) ($slot['start_timestamp'] ?? 0)),
            'start_local_date' => sanitize_text_field((string) ($slot['start_local_date'] ?? '')),
            'start_local_time' => sanitize_text_field((string) ($slot['start_local_time'] ?? '')),
            'end_iso' => sanitize_text_field((string) ($slot['end_iso'] ?? '')),
            'end_timestamp' => absint((int) ($slot['end_timestamp'] ?? 0)),
            'timezone' => sanitize_text_field((string) ($slot['timezone'] ?? '')),
            'reminder_iso' => sanitize_text_field((string) ($timers['reminder_iso'] ?? '')),
            'reminder_timestamp' => absint((int) ($timers['reminder_timestamp'] ?? 0)),
            'reminder_local_date' => sanitize_text_field((string) ($timers['reminder_local_date'] ?? '')),
            'reminder_local_time' => sanitize_text_field((string) ($timers['reminder_local_time'] ?? '')),
            'reminder_offset_minutes' => $reminder_offset > 0 ? absint((int) round($reminder_offset / 60)) : 0,
            'followup_iso' => sanitize_text_field((string) ($timers['followup_iso'] ?? '')),
            'followup_timestamp' => absint((int) ($timers['followup_timestamp'] ?? 0)),
            'followup_local_date' => sanitize_text_field((string) ($timers['followup_local_date'] ?? '')),
            'followup_local_time' => sanitize_text_field((string) ($timers['followup_local_time'] ?? '')),
            'followup_offset_minutes' => $followup_offset > 0 ? absint((int) round($followup_offset / 60)) : 0,
            'booking_iso' => sanitize_text_field((string) ($timers['booked_iso'] ?? '')),
            'booking_timestamp' => absint((int) ($timers['booked_timestamp'] ?? 0)),
            'total' => (float) ($totals['gross'] ?? 0.0),
            'currency' => sanitize_text_field((string) ($order['currency'] ?? '')),
            'pax_total' => absint((int) ($totals['pax_total'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function send_rtb_notification(string $stage, array $context, int $reservation_id, int $template_id): bool
    {
        if (! $this->is_enabled() || $template_id <= 0) {
            return false;
        }

        $key = 'rtb_' . sanitize_key($stage);
        $sent = $this->send_transactional($key, $context, $reservation_id, $template_id);

        if ($sent) {
            $this->send_event($key, $context, $reservation_id);
        }

        return $sent;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render_addons_summary(array $context): string
    {
        $addons = $context['addons'] ?? [];

        if (! is_array($addons) || ! $addons) {
            return '';
        }

        $parts = [];

        foreach ($addons as $addon) {
            if (! is_array($addon)) {
                continue;
            }

            $label = sanitize_text_field((string) ($addon['label'] ?? ($addon['key'] ?? 'addon')));
            $quantity = absint((int) ($addon['quantity'] ?? 0));

            if ($quantity <= 0) {
                continue;
            }

            $parts[] = $label . ' x ' . $quantity;
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function build_contact_payload(array $context, int $reservation_id): array
    {
        $settings = $this->get_settings();
        $customer = $context['customer'] ?? [];
        $mapping = isset($settings['attribute_mapping']) && is_array($settings['attribute_mapping']) ? $settings['attribute_mapping'] : [];
        $attributes = [];

        $first_name_key = $mapping['first_name'] ?? 'FIRSTNAME';
        $last_name_key = $mapping['last_name'] ?? 'LASTNAME';
        $phone_key = $mapping['phone'] ?? 'SMS';
        $language_key = $mapping['language'] ?? 'LANGUAGE';
        $marketing_key = $mapping['marketing_consent'] ?? 'CONSENT';

        $attributes[$first_name_key] = sanitize_text_field((string) ($customer['first_name'] ?? ''));
        $attributes[$last_name_key] = sanitize_text_field((string) ($customer['last_name'] ?? ''));
        $attributes[$phone_key] = sanitize_text_field((string) ($customer['phone'] ?? ''));

        $language_value = '';
        if (! empty($context['language_locale'])) {
            $language_value = (string) $context['language_locale'];
        } elseif (! empty($context['locale'])) {
            $language_value = (string) $context['locale'];
        }

        if ('' !== $language_value) {
            $attributes[$language_key] = sanitize_text_field($language_value);
        }

        $consent = $context['consent']['marketing'] ?? false;
        $attributes[$marketing_key] = $consent ? '1' : '0';

        $reservation = Reservations::get($reservation_id);
        if (isset($reservation['utm']) && is_array($reservation['utm'])) {
            $attributes['UTM_SOURCE'] = sanitize_text_field((string) ($reservation['utm']['utm_source'] ?? ''));
            $attributes['UTM_MEDIUM'] = sanitize_text_field((string) ($reservation['utm']['utm_medium'] ?? ''));
            $attributes['UTM_CAMPAIGN'] = sanitize_text_field((string) ($reservation['utm']['utm_campaign'] ?? ''));
        }

        $experience_slug = '';
        if (! empty($context['experience']['id'])) {
            $experience_slug = (string) get_post_field('post_name', $context['experience']['id']);
        }

        $tags = [];
        if ($experience_slug) {
            $tags[] = 'experience:' . sanitize_key($experience_slug);
        }

        if (! empty($reservation['utm']) && is_array($reservation['utm'])) {
            $source = sanitize_key((string) ($reservation['utm']['utm_source'] ?? ''));
            if ($source) {
                $tags[] = 'channel:' . $source;
            }
        }

        $payload = [
            'email' => sanitize_email((string) ($customer['email'] ?? '')),
            'updateEnabled' => true,
            'attributes' => array_filter($attributes),
        ];

        if ($tags) {
            $payload['tags'] = $tags;
        }

        if (! empty($settings['subscribe_to_list'])) {
            $language = EmailTranslator::normalize((string) ($context['language'] ?? $context['language_locale'] ?? ''));
            $language_key = EmailTranslator::LANGUAGE_IT === $language ? 'it' : 'en';

            $list_ids = [];
            $configured_lists = isset($settings['lists']) && is_array($settings['lists']) ? $settings['lists'] : [];

            if (! empty($configured_lists[$language_key])) {
                $list_ids[] = absint((int) $configured_lists[$language_key]);
            }

            if (! $list_ids) {
                $fallback_id = absint((int) ($settings['list_id'] ?? 0));
                if ($fallback_id > 0) {
                    $list_ids[] = $fallback_id;
                }
            }

            if ($list_ids) {
                $payload['listIds'] = array_values(array_unique(array_filter($list_ids)));
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function build_headers(string $api_key): array
    {
        return [
            'api-key' => $api_key,
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ];
    }

    private function record_notice(string $code, string $message, string $type = 'warning'): void
    {
        $stored = get_transient('fp_exp_brevo_notices');
        $notices = is_array($stored) ? $stored : [];

        $notices[$code] = [
            'message' => sanitize_text_field($message),
            'type' => $type,
            'time' => time(),
        ];

        set_transient('fp_exp_brevo_notices', $notices, 30 * MINUTE_IN_SECONDS);
    }
}
