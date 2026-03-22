<?php

declare(strict_types=1);

namespace FP_Exp\Checkin;

use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function rawurlencode;
use function sanitize_text_field;
use function time;
use function wp_json_encode;
use function wp_salt;

/**
 * Builds and verifies signed QR tokens used for event check-in.
 */
final class QrTokenService
{
    private const VERSION = 1;
    private const DEFAULT_TTL = 1209600; // 14 days.

    /**
     * Generate a signed token payload for reservation check-in.
     *
     * @return array{token:string,issued_at:int,expires_at:int,version:int}
     */
    public static function generate(int $reservation_id, int $order_id, int $ttl = self::DEFAULT_TTL): array
    {
        $issued_at = time();
        $expires_at = $issued_at + max(300, $ttl);

        $payload = [
            'reservation_id' => $reservation_id,
            'order_id' => $order_id,
            'iat' => $issued_at,
            'exp' => $expires_at,
            'v' => self::VERSION,
        ];

        $payload_json = (string) wp_json_encode($payload);
        $payload_encoded = self::base64url_encode($payload_json);
        $signature_encoded = self::base64url_encode(hash_hmac('sha256', $payload_encoded, self::secret(), true));

        return [
            'token' => $payload_encoded . '.' . $signature_encoded,
            'issued_at' => $issued_at,
            'expires_at' => $expires_at,
            'version' => self::VERSION,
        ];
    }

    /**
     * Verify token integrity and expiry.
     *
     * @return array{valid:bool,error:string,payload:array<string,int>}
     */
    public static function verify(string $token): array
    {
        $token = sanitize_text_field($token);
        if ('' === $token || false === strpos($token, '.')) {
            return [
                'valid' => false,
                'error' => 'invalid_format',
                'payload' => [],
            ];
        }

        [$payload_encoded, $signature_encoded] = explode('.', $token, 2);
        if ('' === $payload_encoded || '' === $signature_encoded) {
            return [
                'valid' => false,
                'error' => 'invalid_format',
                'payload' => [],
            ];
        }

        $expected_signature = self::base64url_encode(hash_hmac('sha256', $payload_encoded, self::secret(), true));
        if (! hash_equals($expected_signature, $signature_encoded)) {
            return [
                'valid' => false,
                'error' => 'invalid_signature',
                'payload' => [],
            ];
        }

        $payload_json = self::base64url_decode($payload_encoded);
        if (null === $payload_json || '' === $payload_json) {
            return [
                'valid' => false,
                'error' => 'invalid_payload',
                'payload' => [],
            ];
        }

        $payload = json_decode($payload_json, true);
        if (! is_array($payload)) {
            return [
                'valid' => false,
                'error' => 'invalid_payload',
                'payload' => [],
            ];
        }

        $reservation_id = $payload['reservation_id'] ?? null;
        $order_id = $payload['order_id'] ?? null;
        $issued_at = $payload['iat'] ?? null;
        $expires_at = $payload['exp'] ?? null;
        $version = $payload['v'] ?? null;

        if (! is_int($reservation_id) || ! is_int($order_id) || ! is_int($issued_at) || ! is_int($expires_at) || ! is_int($version)) {
            return [
                'valid' => false,
                'error' => 'invalid_payload',
                'payload' => [],
            ];
        }

        if (self::VERSION !== $version) {
            return [
                'valid' => false,
                'error' => 'invalid_version',
                'payload' => [],
            ];
        }

        if ($expires_at < time()) {
            return [
                'valid' => false,
                'error' => 'expired',
                'payload' => [],
            ];
        }

        return [
            'valid' => true,
            'error' => '',
            'payload' => [
                'reservation_id' => $reservation_id,
                'order_id' => $order_id,
                'iat' => $issued_at,
                'exp' => $expires_at,
                'v' => $version,
            ],
        ];
    }

    /**
     * Build a remote QR image URL for email rendering.
     */
    public static function build_qr_url(string $token, int $size = 220): string
    {
        $safe_size = max(120, min(600, $size));

        return 'https://api.qrserver.com/v1/create-qr-code/?size='
            . $safe_size
            . 'x'
            . $safe_size
            . '&margin=12&data='
            . rawurlencode($token);
    }

    private static function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }

    private static function secret(): string
    {
        return (string) wp_salt('fp_exp_checkin_qr');
    }
}
