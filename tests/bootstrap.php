<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (! defined('FP_EXP_PLUGIN_DIR')) {
    define('FP_EXP_PLUGIN_DIR', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
}

if (! function_exists('trailingslashit')) {
    function trailingslashit(string $path): string
    {
        return rtrim($path, '/\\') . '/';
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return (string) $key;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[\r\n\t]+/', ' ', $text);

        return trim($text);
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (! function_exists('absint')) {
    function absint($maybeint): int
    {
        return (int) abs((int) $maybeint);
    }
}

if (! function_exists('maybe_unserialize')) {
    function maybe_unserialize($data)
    {
        if (! is_string($data)) {
            return $data;
        }

        $data = trim($data);
        if ('' === $data) {
            return $data;
        }

        $unserialized = @unserialize($data);
        if (false === $unserialized && 'b:0;' !== $data) {
            return $data;
        }

        return $unserialized;
    }
}

if (! function_exists('maybe_serialize')) {
    function maybe_serialize($data)
    {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }

        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        return '';
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        if (is_object($args)) {
            $parsed = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed = $args;
        } else {
            parse_str((string) $args, $parsed);
        }

        if (! is_array($defaults)) {
            $defaults = [];
        }

        return array_merge($defaults, $parsed);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

