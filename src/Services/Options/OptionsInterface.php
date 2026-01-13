<?php

declare(strict_types=1);

namespace FP_Exp\Services\Options;

/**
 * Options service interface.
 */
interface OptionsInterface
{
    /**
     * Get an option value.
     *
     * @param string $key Option key
     * @param mixed $default Default value if option doesn't exist
     * @return mixed Option value or default
     */
    public function get(string $key, $default = null);

    /**
     * Set an option value.
     *
     * @param string $key Option key
     * @param mixed $value Option value
     * @param bool $autoload Whether to autoload this option
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, bool $autoload = true): bool;

    /**
     * Delete an option.
     *
     * @param string $key Option key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Check if an option exists.
     *
     * @param string $key Option key
     * @return bool True if option exists, false otherwise
     */
    public function has(string $key): bool;
}



