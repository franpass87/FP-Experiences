<?php

declare(strict_types=1);

namespace FP_Exp\Services\HTTP;

use WP_Error;

use function wp_remote_get;
use function wp_remote_post;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_headers;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_response_message;
use function is_wp_error;

/**
 * WordPress HTTP client implementation using wp_remote_* functions.
 */
final class WordPressHttpClient implements HttpClientInterface
{
    /**
     * Default timeout in seconds.
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Make a GET request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function get(string $url, array $args = []): ?array
    {
        $args['method'] = 'GET';
        return $this->request('GET', $url, $args);
    }

    /**
     * Make a POST request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function post(string $url, array $args = []): ?array
    {
        $args['method'] = 'POST';
        return $this->request('POST', $url, $args);
    }

    /**
     * Make a PUT request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function put(string $url, array $args = []): ?array
    {
        $args['method'] = 'PUT';
        return $this->request('PUT', $url, $args);
    }

    /**
     * Make a DELETE request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function delete(string $url, array $args = []): ?array
    {
        $args['method'] = 'DELETE';
        return $this->request('DELETE', $url, $args);
    }

    /**
     * Make a custom HTTP request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function request(string $method, string $url, array $args = []): ?array
    {
        // Prepare request arguments
        $requestArgs = [
            'method' => $args['method'] ?? $method,
            'timeout' => $args['timeout'] ?? self::DEFAULT_TIMEOUT,
            'headers' => $args['headers'] ?? [],
            'body' => $args['body'] ?? null,
            'sslverify' => $args['sslverify'] ?? true,
            'user-agent' => $args['user-agent'] ?? 'FP-Experiences/' . (defined('FP_EXP_VERSION') ? FP_EXP_VERSION : '1.0.0'),
        ];

        // Remove method from args if it was set
        unset($requestArgs['method']);

        // Make request
        $response = wp_remote_request($url, $requestArgs);

        // Handle WP_Error
        if (is_wp_error($response)) {
            return null;
        }

        // Extract response data
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $code = wp_remote_retrieve_response_code($response);
        $message = wp_remote_retrieve_response_message($response);

        // Convert headers to array
        $headersArray = [];
        if ($headers instanceof \Requests_Utility_CaseInsensitiveDictionary) {
            foreach ($headers as $key => $value) {
                $headersArray[$key] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
        } elseif (is_array($headers)) {
            $headersArray = $headers;
        }

        return [
            'body' => $body !== false ? $body : '',
            'headers' => $headersArray,
            'response' => [
                'code' => $code !== false ? $code : 0,
                'message' => $message !== false ? $message : '',
            ],
        ];
    }
}







