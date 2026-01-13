<?php

declare(strict_types=1);

namespace FP_Exp\Services\Options;

use function delete_option;
use function get_option;
use function update_option;

/**
 * Options service implementation.
 * Wraps WordPress options API.
 */
final class Options implements OptionsInterface
{
    /**
     * Get an option value.
     *
     * @param string $key Option key
     * @param mixed $default Default value if option doesn't exist
     * @return mixed Option value or default
     */
    public function get(string $key, $default = null)
    {
        return get_option($key, $default);
    }

    /**
     * Set an option value.
     *
     * @param string $key Option key
     * @param mixed $value Option value
     * @param bool $autoload Whether to autoload this option
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, bool $autoload = true): bool
    {
        return update_option($key, $value, $autoload);
    }

    /**
     * Delete an option.
     *
     * @param string $key Option key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        return delete_option($key);
    }

    /**
     * Check if an option exists.
     *
     * @param string $key Option key
     * @return bool True if option exists, false otherwise
     */
    public function has(string $key): bool
    {
        return get_option($key) !== false;
    }
}



