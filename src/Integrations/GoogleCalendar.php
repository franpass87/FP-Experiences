<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Booking\Emails;
use FP_Exp\Utils\Logger;
use WC_Order;

use function add_action;
use function absint;
use function array_filter;
use function array_map;
use function esc_url_raw;
use function get_option;
use function get_transient;
use function gmdate;
use function implode;
use function is_array;
use function is_string;
use function is_wp_error;
use function rawurlencode;
use function sanitize_text_field;
use function set_transient;
use function sprintf;
use function strtotime;
use function time;
use function wp_json_encode;
use function wp_remote_delete;
use function wp_remote_post;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function update_option;
use function wc_get_order;
use const MINUTE_IN_SECONDS;
use function __;

final class GoogleCalendar implements HookableInterface
{
    private ?Emails $emails = null;
    private ?OptionsInterface $options = null;

    /**
     * GoogleCalendar constructor.
     *
     * @param Emails|null $emails Email service (optional, can be set via set_email_service())
     * @param OptionsInterface|null $options Optional OptionsInterface (will try to get from container if not provided)
     */
    public function __construct(?Emails $emails = null, ?OptionsInterface $options = null)
    {
        $this->emails = $emails;
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

    public function register_hooks(): void
    {
        add_action('fp_exp_reservation_paid', [$this, 'handle_reservation_paid'], 30, 2);
        add_action('fp_exp_reservation_cancelled', [$this, 'handle_reservation_cancelled'], 30, 2);
    }

    public function set_email_service(Emails $emails): void
    {
        $this->emails = $emails;
    }

    public function is_connected(): bool
    {
        $settings = $this->get_settings();

        return ! empty($settings['access_token']) && ! empty($settings['calendar_id']);
    }

    public function handle_reservation_paid(int $reservation_id, int $order_id): void
    {
        if (! $this->is_connected()) {
            return;
        }

        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $this->upsert_event($context, $reservation_id);
    }

    public function handle_reservation_cancelled(int $reservation_id, int $order_id): void
    {
        if (! $this->is_connected()) {
            return;
        }

        $context = $this->get_context($reservation_id, $order_id);

        if (! $context) {
            return;
        }

        $this->delete_event($context, $reservation_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function get_settings(): array
    {
        $settings = $this->getOptions()->get('fp_exp_google_calendar', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get_context(int $reservation_id, int $order_id): ?array
    {
        if (! $this->emails instanceof Emails) {
            return null;
        }

        return $this->emails->get_context($reservation_id, $order_id);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function upsert_event(array $context, int $reservation_id): void
    {
        $order_id = absint($context['order']['id'] ?? 0);
        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $settings = $this->get_settings();
        $calendar_id = (string) ($settings['calendar_id'] ?? '');

        $token = $this->get_access_token();
        if (! $token || ! $calendar_id) {
            return;
        }

        $event_payload = $this->build_event_payload($context);

        $meta_key = '_fp_exp_google_event_' . $reservation_id;
        $event_id = (string) $order->get_meta($meta_key);

        if ($event_id) {
            $endpoint = sprintf(
                'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
                rawurlencode($calendar_id),
                rawurlencode($event_id)
            );

            $response = wp_remote_request($endpoint, [
                'method' => 'PATCH',
                'headers' => $this->build_headers($token),
                'body' => wp_json_encode($event_payload),
                'timeout' => 15,
            ]);
        } else {
            $endpoint = sprintf(
                'https://www.googleapis.com/calendar/v3/calendars/%s/events',
                rawurlencode($calendar_id)
            );

            $response = wp_remote_post($endpoint, [
                'headers' => $this->build_headers($token),
                'body' => wp_json_encode($event_payload),
                'timeout' => 15,
            ]);
        }

        if (! isset($response)) {
            return;
        }

        if (is_wp_error($response)) {
            Logger::log('google_calendar', 'Failed to sync event', [
                'reservation' => $reservation_id,
                'error' => $response->get_error_message(),
            ]);

            $this->record_notice(
                'event_request',
                __('Google Calendar event sync failed. Check the logs for details.', 'fp-experiences'),
                'error'
            );

            return;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($body) && ! empty($body['id'])) {
                $order->update_meta_data($meta_key, sanitize_text_field((string) $body['id']));
                $order->save();
            }

            return;
        }

        Logger::log('google_calendar', 'Failed to sync event', [
            'reservation' => $reservation_id,
            'status' => $code,
            'body' => wp_remote_retrieve_body($response),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function delete_event(array $context, int $reservation_id): void
    {
        $order_id = absint($context['order']['id'] ?? 0);
        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $settings = $this->get_settings();
        $calendar_id = (string) ($settings['calendar_id'] ?? '');
        $token = $this->get_access_token();

        if (! $calendar_id || ! $token) {
            return;
        }

        $meta_key = '_fp_exp_google_event_' . $reservation_id;
        $event_id = (string) $order->get_meta($meta_key);

        if (! $event_id) {
            return;
        }

        $endpoint = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            rawurlencode($calendar_id),
            rawurlencode($event_id)
        );

        $response = wp_remote_delete($endpoint, [
            'headers' => $this->build_headers($token),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            Logger::log('google_calendar', 'Failed to delete event', [
                'reservation' => $reservation_id,
                'error' => $response->get_error_message(),
            ]);

            $this->record_notice(
                'event_request',
                __('Google Calendar event deletion failed. Check the logs for details.', 'fp-experiences'),
                'error'
            );

            return;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            $order->delete_meta_data($meta_key);
            $order->save();

            return;
        }

        Logger::log('google_calendar', 'Failed to delete event', [
            'reservation' => $reservation_id,
            'status' => $code,
            'body' => wp_remote_retrieve_body($response),
        ]);

        $this->record_notice(
            'event_delete',
            __('Google Calendar event deletion failed. Check the logs for diagnostics.', 'fp-experiences'),
            'warning'
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function build_event_payload(array $context): array
    {
        $timezone = sanitize_text_field((string) ($context['slot']['timezone'] ?? 'UTC'));
        $start_utc = (string) ($context['slot']['start_utc'] ?? '');
        $end_utc = (string) ($context['slot']['end_utc'] ?? $start_utc);
        $start = $start_utc ? gmdate('c', strtotime($start_utc . ' UTC')) : gmdate('c');
        $end = $end_utc ? gmdate('c', strtotime($end_utc . ' UTC')) : $start;

        $description = (string) ($context['experience']['short_description'] ?? '');
        $addons = $context['addons'] ?? [];
        if (is_array($addons) && $addons) {
            $addon_lines = array_map(
                static fn ($addon) => is_array($addon)
                    ? sprintf('%s x %s', $addon['label'] ?? $addon['key'] ?? 'Addon', $addon['quantity'] ?? 1)
                    : '',
                $addons
            );
            $addon_lines = array_filter($addon_lines);
            if ($addon_lines) {
                $description .= "\n" . implode('\n', $addon_lines);
            }
        }

        $attendees = [];
        $customer = $context['customer'] ?? [];
        if (is_array($customer) && ! empty($customer['email'])) {
            $attendees[] = [
                'email' => sanitize_text_field((string) $customer['email']),
                'displayName' => sanitize_text_field((string) ($customer['name'] ?? '')),
            ];
        }

        return [
            'summary' => sanitize_text_field((string) ($context['experience']['title'] ?? 'Experience booking')),
            'description' => $description,
            'location' => sanitize_text_field((string) ($context['experience']['meeting_point'] ?? '')),
            'start' => [
                'dateTime' => $start,
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end,
                'timeZone' => $timezone,
            ],
            'attendees' => $attendees,
            'source' => [
                'title' => 'FP Experiences',
                'url' => esc_url_raw((string) ($context['experience']['permalink'] ?? '')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function build_headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];
    }

    private function get_access_token(): string
    {
        $settings = $this->get_settings();
        $token = (string) ($settings['access_token'] ?? '');
        $expires_at = (int) ($settings['expires_at'] ?? 0);

        if ($token && $expires_at > time() + 60) {
            return $token;
        }

        $refresh_token = (string) ($settings['refresh_token'] ?? '');
        $client_id = (string) ($settings['client_id'] ?? '');
        $client_secret = (string) ($settings['client_secret'] ?? '');

        if (! $refresh_token || ! $client_id || ! $client_secret) {
            return '';
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
            'timeout' => 15,
        ]);

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($body) && ! empty($body['access_token'])) {
                $settings['access_token'] = (string) $body['access_token'];
                $settings['expires_at'] = time() + (int) ($body['expires_in'] ?? 3600);
                $this->getOptions()->set('fp_exp_google_calendar', $settings, false);

                return (string) $body['access_token'];
            }
        }

        Logger::log('google_calendar', 'Failed to refresh token', [
            'status' => $code,
            'body' => wp_remote_retrieve_body($response),
        ]);

        $this->record_notice(
            'token_refresh',
            __('Google Calendar token refresh failed. Reconnect the account from Settings → Calendar.', 'fp-experiences'),
            'error'
        );

        return '';
    }

    private function record_notice(string $code, string $message, string $type = 'warning'): void
    {
        $stored = get_transient('fp_exp_calendar_notices');
        $notices = is_array($stored) ? $stored : [];

        $notices[$code] = [
            'message' => sanitize_text_field($message),
            'type' => $type,
            'time' => time(),
        ];

        set_transient('fp_exp_calendar_notices', $notices, 30 * MINUTE_IN_SECONDS);
    }
}
