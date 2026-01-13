<?php

declare(strict_types=1);

namespace FP_Exp\Core\Container;

/**
 * Dependency injection container interface.
 */
interface ContainerInterface
{
    /**
     * Bind an abstract to a concrete implementation.
     *
     * @param string $abstract Abstract class or interface name
     * @param string|callable|object $concrete Concrete class name, factory closure, or instance
     * @param bool $singleton Whether to register as singleton
     */
    public function bind(string $abstract, $concrete, bool $singleton = false): void;

    /**
     * Register a singleton binding.
     *
     * @param string $abstract Abstract class or interface name
     * @param string|callable|object $concrete Concrete class name, factory closure, or instance
     */
    public function singleton(string $abstract, $concrete): void;

    /**
     * Resolve an instance from the container.
     *
     * @param string $abstract Abstract class or interface name
     * @param array<string, mixed> $parameters Optional parameters for constructor
     * @return mixed Resolved instance
     */
    public function make(string $abstract, array $parameters = []);

    /**
     * Check if an abstract is bound in the container.
     *
     * @param string $abstract Abstract class or interface name
     */
    public function has(string $abstract): bool;

    /**
     * Register an existing instance in the container.
     *
     * @param string $abstract Abstract class or interface name
     * @param object $instance Instance to register
     */
    public function instance(string $abstract, object $instance): void;
}



