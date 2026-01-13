<?php

declare(strict_types=1);

namespace FP_Exp\Services\Security;

use function wp_create_nonce;
use function wp_verify_nonce;
use function wp_nonce_field;
use function wp_nonce_url;

/**
 * Nonce manager service for generating and verifying WordPress nonces.
 */
final class NonceManager
{
    /**
     * Generate a nonce.
     *
     * @param string|int $action Optional action name
     * @return string Generated nonce
     */
    public function create(string|int $action = -1): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify a nonce.
     *
     * @param string $nonce Nonce to verify
     * @param string|int $action Optional action name
     * @return int|false 1 if valid, 2 if valid and generated between 0-12 hours ago, false if invalid
     */
    public function verify(string $nonce, string|int $action = -1): int|false
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Verify nonce from request.
     *
     * @param string $nonceName Name of nonce field in request
     * @param string|int $action Optional action name
     * @return int|false 1 if valid, 2 if valid and generated between 0-12 hours ago, false if invalid
     */
    public function verifyFromRequest(string $nonceName, string|int $action = -1): int|false
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $nonce = $_REQUEST[$nonceName] ?? '';

        if (!is_string($nonce) || $nonce === '') {
            return false;
        }

        return $this->verify($nonce, $action);
    }

    /**
     * Generate nonce field HTML.
     *
     * @param string|int $action Optional action name
     * @param string $name Nonce field name
     * @param bool $referer Whether to include referer field
     * @param bool $echo Whether to echo or return
     * @return string Nonce field HTML
     */
    public function field(string|int $action = -1, string $name = '_wpnonce', bool $referer = true, bool $echo = false): string
    {
        return wp_nonce_field($action, $name, $referer, $echo);
    }

    /**
     * Generate nonce URL.
     *
     * @param string $actionurl URL to add nonce to
     * @param string|int $action Optional action name
     * @param string $name Nonce parameter name
     * @return string URL with nonce
     */
    public function url(string $actionurl, string|int $action = -1, string $name = '_wpnonce'): string
    {
        return wp_nonce_url($actionurl, $action, $name);
    }
}







