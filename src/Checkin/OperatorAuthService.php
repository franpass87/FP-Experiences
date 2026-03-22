<?php

declare(strict_types=1);

namespace FP_Exp\Checkin;

use FP_Exp\Utils\Logger;

/**
 * Handles plugin-managed mobile operators authentication and sessions.
 */
final class OperatorAuthService
{
    private const OPTION_KEY = 'fp_exp_mobile_operators';
    private const LOCK_INDEX_OPTION_KEY = 'fp_exp_mobile_operator_lock_index';
    private const COOKIE_NAME = 'fp_exp_mobile_operator_session';
    private const SESSION_PREFIX = 'fp_exp_mobile_operator_session_';
    private const SESSION_TTL = 43200; // 12h.
    private const LOGIN_ATTEMPT_PREFIX = 'fp_exp_mobile_login_attempt_';
    private const LOGIN_LOCK_PREFIX = 'fp_exp_mobile_login_lock_';
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 900; // 15m.

    /**
     * @return array<int, array{id:int,username:string,display_name:string,password_hash:string,active:bool,created_at:int}>
     */
    public function get_operators(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        $operators = [];
        foreach ($stored as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = absint((int) ($item['id'] ?? 0));
            $username = sanitize_user((string) ($item['username'] ?? ''), true);
            $display_name = sanitize_text_field((string) ($item['display_name'] ?? $username));
            $password_hash = (string) ($item['password_hash'] ?? '');
            $active = ! empty($item['active']);
            $created_at = absint((int) ($item['created_at'] ?? 0));

            if ($id <= 0 || '' === $username || '' === $password_hash) {
                continue;
            }

            $operators[] = [
                'id' => $id,
                'username' => $username,
                'display_name' => $display_name,
                'password_hash' => $password_hash,
                'active' => $active,
                'created_at' => $created_at,
            ];
        }

        return $operators;
    }

    /**
     * @return array{ok:bool,error:string}
     */
    public function create_operator(string $username, string $display_name, string $password): array
    {
        $username = sanitize_user($username, true);
        $display_name = sanitize_text_field($display_name);

        if ('' === $username || '' === $password) {
            return ['ok' => false, 'error' => 'invalid_data'];
        }

        $operators = $this->get_operators();
        foreach ($operators as $operator) {
            if ($operator['username'] === $username) {
                return ['ok' => false, 'error' => 'username_exists'];
            }
        }

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $operators);
        $next_id = $ids ? (max($ids) + 1) : 1;

        $operators[] = [
            'id' => $next_id,
            'username' => $username,
            'display_name' => '' !== $display_name ? $display_name : $username,
            'password_hash' => wp_hash_password($password),
            'active' => true,
            'created_at' => time(),
        ];

        update_option(self::OPTION_KEY, $operators, false);

        return ['ok' => true, 'error' => ''];
    }

    public function delete_operator(int $operator_id): bool
    {
        if ($operator_id <= 0) {
            return false;
        }

        $operators = $this->get_operators();
        $filtered = array_values(array_filter(
            $operators,
            static fn(array $row): bool => (int) $row['id'] !== $operator_id
        ));

        update_option(self::OPTION_KEY, $filtered, false);

        foreach ($operators as $operator) {
            if ((int) $operator['id'] === $operator_id) {
                $this->reset_lockout_for_username((string) $operator['username']);
                break;
            }
        }

        return true;
    }

    public function reset_lockout_for_operator(int $operator_id): bool
    {
        if ($operator_id <= 0) {
            return false;
        }

        foreach ($this->get_operators() as $operator) {
            if ((int) $operator['id'] !== $operator_id) {
                continue;
            }

            $this->reset_lockout_for_username((string) $operator['username']);
            return true;
        }

        return false;
    }

    public function get_lockout_until_for_operator(int $operator_id): int
    {
        if ($operator_id <= 0) {
            return 0;
        }

        foreach ($this->get_operators() as $operator) {
            if ((int) $operator['id'] !== $operator_id) {
                continue;
            }

            return $this->get_lockout_until_for_username((string) $operator['username']);
        }

        return 0;
    }

    /**
     * @return array{id:int,username:string,display_name:string}|null
     */
    public function verify_credentials(string $username, string $password): ?array
    {
        $username = sanitize_user($username, true);
        if ('' === $username || '' === $password) {
            return null;
        }

        foreach ($this->get_operators() as $operator) {
            if (! $operator['active']) {
                continue;
            }

            if ($operator['username'] !== $username) {
                continue;
            }

            if (! wp_check_password($password, $operator['password_hash'])) {
                return null;
            }

            return [
                'id' => (int) $operator['id'],
                'username' => $operator['username'],
                'display_name' => $operator['display_name'],
            ];
        }

        return null;
    }

    /**
     * Authenticate operator credentials with rate limiting and lockout.
     *
     * @return array{ok:bool,error:string,operator:?array{id:int,username:string,display_name:string},remaining_attempts:int,lockout_until:int}
     */
    public function authenticate(string $username, string $password): array
    {
        $username = sanitize_user($username, true);
        $client_ip = $this->resolve_client_ip();
        $lock_key = $this->lock_key($username, $client_ip);
        $attempt_key = $this->attempt_key($username, $client_ip);
        $this->track_lock_keys($username, [$attempt_key, $lock_key]);
        $lockout_until = (int) get_transient($lock_key);

        if ($lockout_until > time()) {
            return [
                'ok' => false,
                'error' => 'locked',
                'operator' => null,
                'remaining_attempts' => 0,
                'lockout_until' => $lockout_until,
            ];
        }

        $operator = $this->verify_credentials($username, $password);
        if (is_array($operator)) {
            delete_transient($attempt_key);
            delete_transient($lock_key);

            Logger::log('security', 'Mobile operator login success', [
                'username' => $username,
                'ip' => $client_ip,
                'operator_id' => (int) ($operator['id'] ?? 0),
            ]);

            return [
                'ok' => true,
                'error' => '',
                'operator' => $operator,
                'remaining_attempts' => self::LOGIN_MAX_ATTEMPTS,
                'lockout_until' => 0,
            ];
        }

        $attempts = (int) get_transient($attempt_key);
        $attempts = max(0, $attempts) + 1;
        set_transient($attempt_key, $attempts, self::LOGIN_LOCK_SECONDS);
        $remaining_attempts = max(0, self::LOGIN_MAX_ATTEMPTS - $attempts);

        if ($attempts >= self::LOGIN_MAX_ATTEMPTS) {
            $lockout_until = time() + self::LOGIN_LOCK_SECONDS;
            set_transient($lock_key, $lockout_until, self::LOGIN_LOCK_SECONDS);
            delete_transient($attempt_key);

            Logger::log('security', 'Mobile operator login lockout', [
                'username' => $username,
                'ip' => $client_ip,
                'lockout_until' => $lockout_until,
            ]);

            return [
                'ok' => false,
                'error' => 'locked',
                'operator' => null,
                'remaining_attempts' => 0,
                'lockout_until' => $lockout_until,
            ];
        }

        Logger::log('security', 'Mobile operator login failed', [
            'username' => $username,
            'ip' => $client_ip,
            'remaining_attempts' => $remaining_attempts,
        ]);

        return [
            'ok' => false,
            'error' => 'invalid_credentials',
            'operator' => null,
            'remaining_attempts' => $remaining_attempts,
            'lockout_until' => 0,
        ];
    }

    /**
     * @param array{id:int,username:string,display_name:string} $operator
     */
    public function create_session(array $operator): void
    {
        $token = wp_generate_password(48, false, false);
        $expires_at = time() + self::SESSION_TTL;
        set_transient(self::SESSION_PREFIX . $token, [
            'operator_id' => (int) $operator['id'],
            'username' => (string) $operator['username'],
            'display_name' => (string) $operator['display_name'],
            'issued_at' => time(),
            'expires_at' => $expires_at,
        ], self::SESSION_TTL);

        $cookie_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires' => $expires_at,
                'path' => '/',
                'domain' => is_string($cookie_domain) ? $cookie_domain : '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    public function destroy_session(): void
    {
        $token = $this->get_cookie_token();
        if ('' !== $token) {
            delete_transient(self::SESSION_PREFIX . $token);
        }

        $cookie_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => '/',
                'domain' => is_string($cookie_domain) ? $cookie_domain : '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * @return array{id:int,username:string,display_name:string}|null
     */
    public function get_authenticated_operator(): ?array
    {
        $token = $this->get_cookie_token();
        if ('' === $token) {
            return null;
        }

        $session = get_transient(self::SESSION_PREFIX . $token);
        if (! is_array($session)) {
            return null;
        }

        $operator_id = absint((int) ($session['operator_id'] ?? 0));
        $username = sanitize_user((string) ($session['username'] ?? ''), true);
        $display_name = sanitize_text_field((string) ($session['display_name'] ?? ''));
        if ($operator_id <= 0 || '' === $username) {
            return null;
        }

        return [
            'id' => $operator_id,
            'username' => $username,
            'display_name' => $display_name,
        ];
    }

    private function get_cookie_token(): string
    {
        if (! isset($_COOKIE[self::COOKIE_NAME])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $_COOKIE[self::COOKIE_NAME])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }

    private function attempt_key(string $username, string $ip): string
    {
        return self::LOGIN_ATTEMPT_PREFIX . md5($username . '|' . $ip);
    }

    private function lock_key(string $username, string $ip): string
    {
        return self::LOGIN_LOCK_PREFIX . md5($username . '|' . $ip);
    }

    private function resolve_client_ip(): string
    {
        $raw = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $ip = sanitize_text_field($raw);

        return '' !== $ip ? $ip : 'unknown';
    }

    private function reset_lockout_for_username(string $username): void
    {
        $username = sanitize_user($username, true);
        if ('' === $username) {
            return;
        }

        $index = $this->get_lock_index();
        $keys = isset($index[$username]) && is_array($index[$username]) ? $index[$username] : [];

        foreach ($keys as $key) {
            if (! is_string($key) || '' === $key) {
                continue;
            }
            delete_transient($key);
        }

        unset($index[$username]);
        update_option(self::LOCK_INDEX_OPTION_KEY, $index, false);
    }

    private function get_lockout_until_for_username(string $username): int
    {
        $username = sanitize_user($username, true);
        if ('' === $username) {
            return 0;
        }

        $index = $this->get_lock_index();
        $keys = isset($index[$username]) && is_array($index[$username]) ? $index[$username] : [];
        if ($keys === []) {
            return 0;
        }

        $max_lockout = 0;
        foreach ($keys as $key) {
            if (! is_string($key) || '' === $key) {
                continue;
            }

            if (0 !== strpos($key, self::LOGIN_LOCK_PREFIX)) {
                continue;
            }

            $until = (int) get_transient($key);
            if ($until > $max_lockout) {
                $max_lockout = $until;
            }
        }

        return $max_lockout > time() ? $max_lockout : 0;
    }

    /**
     * @param array<int,string> $keys
     */
    private function track_lock_keys(string $username, array $keys): void
    {
        $username = sanitize_user($username, true);
        if ('' === $username) {
            return;
        }

        $index = $this->get_lock_index();
        $existing = isset($index[$username]) && is_array($index[$username]) ? $index[$username] : [];

        foreach ($keys as $key) {
            if (! is_string($key) || '' === $key) {
                continue;
            }
            $existing[] = $key;
        }

        $existing = array_values(array_unique($existing));
        $index[$username] = $existing;
        update_option(self::LOCK_INDEX_OPTION_KEY, $index, false);
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function get_lock_index(): array
    {
        $index = get_option(self::LOCK_INDEX_OPTION_KEY, []);
        if (! is_array($index)) {
            return [];
        }

        return $index;
    }
}
