<?php

declare(strict_types=1);

namespace FP_Exp\Services\Cache;

use function delete_transient;
use function get_transient;
use function set_transient;

/**
 * Transient-based cache implementation.
 */
final class TransientCache implements CacheInterface
{
    private string $prefix;

    public function __construct(string $prefix = 'fp_exp_')
    {
        $this->prefix = $prefix;
    }

    /**
     * Get a value from cache.
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null)
    {
        $value = get_transient($this->prefix . $key);

        return $value !== false ? $value : $default;
    }

    /**
     * Set a value in cache.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        return set_transient($this->prefix . $key, $value, $ttl);
    }

    /**
     * Delete a value from cache.
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        return delete_transient($this->prefix . $key);
    }

    /**
     * Flush all cache.
     *
     * Note: WordPress doesn't provide a way to flush all transients,
     * so this is a no-op. Use object cache if you need flush capability.
     *
     * @return bool Always returns true
     */
    public function flush(): bool
    {
        // WordPress doesn't support flushing all transients
        // This would require iterating through all transients which is expensive
        return true;
    }

    /**
     * Remember a value (get from cache or compute and store).
     *
     * @param string $key Cache key
     * @param callable $callback Callback to compute value if not cached
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return mixed Cached or computed value
     */
    public function remember(string $key, callable $callback, int $ttl = 0)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}



