<?php

declare(strict_types=1);

namespace FP_Exp\Services\Cache;

/**
 * Cache service interface.
 */
interface CacheInterface
{
    /**
     * Get a value from cache.
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null);

    /**
     * Set a value in cache.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $ttl = 0): bool;

    /**
     * Delete a value from cache.
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Flush all cache.
     *
     * @return bool True on success, false on failure
     */
    public function flush(): bool;

    /**
     * Remember a value (get from cache or compute and store).
     *
     * @param string $key Cache key
     * @param callable $callback Callback to compute value if not cached
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return mixed Cached or computed value
     */
    public function remember(string $key, callable $callback, int $ttl = 0);
}



