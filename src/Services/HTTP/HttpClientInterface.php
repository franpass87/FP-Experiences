<?php

declare(strict_types=1);

namespace FP_Exp\Services\HTTP;

/**
 * HTTP client interface for making external API requests.
 */
interface HttpClientInterface
{
    /**
     * Make a GET request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function get(string $url, array $args = []): ?array;

    /**
     * Make a POST request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function post(string $url, array $args = []): ?array;

    /**
     * Make a PUT request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function put(string $url, array $args = []): ?array;

    /**
     * Make a DELETE request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function delete(string $url, array $args = []): ?array;

    /**
     * Make a custom HTTP request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null Response data or null on failure
     */
    public function request(string $method, string $url, array $args = []): ?array;
}







