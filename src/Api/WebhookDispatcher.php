<?php

declare(strict_types=1);

namespace FP_Exp\Api;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Logger;

use function add_action;
use function current_time;
use function get_option;
use function hash_hmac;
use function in_array;
use function is_array;
use function is_wp_error;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_response_code;
use function wp_schedule_single_event;

use const MINUTE_IN_SECONDS;

/**
 * Dispatches outbound webhooks for FP Experiences events (reservation_created, reservation_paid, etc.).
 * Sends POST with JSON body and X-FP-EXP-Signature (HMAC-SHA256). Retries once after 5 minutes on failure.
 */
final class WebhookDispatcher implements HookableInterface
{
    private const OPTION_KEY = 'fp_exp_webhook_settings';

    public const EVENT_RESERVATION_CREATED = 'reservation_created';
    public const EVENT_RESERVATION_PAID = 'reservation_paid';
    public const EVENT_RESERVATION_CANCELLED = 'reservation_cancelled';
    public const EVENT_RTB_APPROVED = 'rtb_request_approved';
    public const EVENT_RTB_DECLINED = 'rtb_request_declined';
    public const EVENT_GIFT_REDEEMED = 'gift_voucher_redeemed';

    public static function supported_events(): array
    {
        return [
            self::EVENT_RESERVATION_CREATED,
            self::EVENT_RESERVATION_PAID,
            self::EVENT_RESERVATION_CANCELLED,
            self::EVENT_RTB_APPROVED,
            self::EVENT_RTB_DECLINED,
            self::EVENT_GIFT_REDEEMED,
        ];
    }

    public function register_hooks(): void
    {
        add_action('fp_exp_reservation_created', [$this, 'on_reservation_created'], 10, 2);
        add_action('fp_exp_reservation_paid', [$this, 'on_reservation_paid'], 10, 2);
        add_action('fp_exp_reservation_cancelled', [$this, 'on_reservation_cancelled'], 10, 2);
        add_action('fp_exp_rtb_request_approved', [$this, 'on_rtb_approved'], 10, 3);
        add_action('fp_exp_rtb_request_declined', [$this, 'on_rtb_declined'], 10, 3);
        add_action('fp_exp_gift_voucher_redeemed', [$this, 'on_gift_redeemed'], 10, 3);
        add_action('fp_exp_webhook_retry', [$this, 'run_retry'], 10, 3);
    }

    public function on_reservation_created(int $reservation_id, int $order_id): void
    {
        $this->dispatch(self::EVENT_RESERVATION_CREATED, [
            'reservation_id' => $reservation_id,
            'order_id' => $order_id,
        ]);
    }

    public function on_reservation_paid(int $reservation_id, int $order_id): void
    {
        $this->dispatch(self::EVENT_RESERVATION_PAID, [
            'reservation_id' => $reservation_id,
            'order_id' => $order_id,
        ]);
    }

    public function on_reservation_cancelled(int $reservation_id, int $order_id): void
    {
        $this->dispatch(self::EVENT_RESERVATION_CANCELLED, [
            'reservation_id' => $reservation_id,
            'order_id' => $order_id,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function on_rtb_approved(int $reservation_id, array $context, string $mode): void
    {
        $this->dispatch(self::EVENT_RTB_APPROVED, [
            'reservation_id' => $reservation_id,
            'mode' => $mode,
            'order_id' => (int) ($context['order_id'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function on_rtb_declined(int $reservation_id, array $context, string $reason): void
    {
        $this->dispatch(self::EVENT_RTB_DECLINED, [
            'reservation_id' => $reservation_id,
            'reason' => $reason,
            'order_id' => (int) ($context['order_id'] ?? 0),
        ]);
    }

    public function on_gift_redeemed(int $voucher_id, int $order_id, int $reservation_id): void
    {
        $this->dispatch(self::EVENT_GIFT_REDEEMED, [
            'voucher_id' => $voucher_id,
            'order_id' => $order_id,
            'reservation_id' => $reservation_id,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function run_retry(string $url, array $payload, string $signature): void
    {
        $this->send_request($url, $payload, $signature, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dispatch(string $event, array $data): void
    {
        $settings = get_option(self::OPTION_KEY, []);
        if (! is_array($settings)) {
            return;
        }
        $url = isset($settings['url']) ? trim((string) $settings['url']) : '';
        $secret = isset($settings['secret']) ? (string) $settings['secret'] : '';
        $events = isset($settings['events']) && is_array($settings['events']) ? $settings['events'] : [];
        if ('' === $url || '' === $secret || ! in_array($event, $events, true)) {
            return;
        }

        $payload = [
            'event' => $event,
            'timestamp' => current_time('c'),
            'data' => $data,
        ];
        $body = wp_json_encode($payload);
        if ($body === false) {
            return;
        }
        $signature = hash_hmac('sha256', $body, $secret);
        $this->send_request($url, $payload, $signature, false);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send_request(string $url, array $payload, string $signature, bool $is_retry): void
    {
        $body = wp_json_encode($payload);
        if ($body === false) {
            return;
        }
        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-FP-EXP-Event' => (string) ($payload['event'] ?? ''),
                'X-FP-EXP-Signature' => $signature,
                'User-Agent' => 'FP-Experiences-Webhook/1.0',
            ],
            'body' => $body,
        ]);
        $code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        $ok = $code >= 200 && $code < 300;
        if ($ok) {
            return;
        }
        if (! $is_retry) {
            wp_schedule_single_event(
                time() + 5 * MINUTE_IN_SECONDS,
                'fp_exp_webhook_retry',
                [$url, $payload, $signature]
            );
            Logger::log('webhook-dispatcher', 'Webhook delivery failed, retry scheduled.', [
                'event' => $payload['event'] ?? '',
                'url' => $url,
                'code' => $code,
            ]);
        }
    }
}
