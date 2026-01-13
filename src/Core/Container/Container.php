<?php

declare(strict_types=1);

namespace FP_Exp\Core\Container;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * Simple dependency injection container with auto-wiring.
 */
final class Container implements ContainerInterface
{
    /**
     * @var array<string, ServiceDefinition>
     */
    private array $bindings = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, bool>
     */
    private array $resolving = [];

    public function bind(string $abstract, $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = new ServiceDefinition($abstract, $concrete, $singleton);
    }

    public function singleton(string $abstract, $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function make(string $abstract, array $parameters = [])
    {
        // Check if already resolved as singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check if we have a binding
        if (!isset($this->bindings[$abstract])) {
            // Try to auto-resolve if it's a class
            if (class_exists($abstract)) {
                return $this->resolveClass($abstract, $parameters);
            }

            throw new ContainerException("No binding found for: {$abstract}");
        }

        $definition = $this->bindings[$abstract];

        // Check for circular dependency
        if (isset($this->resolving[$abstract])) {
            throw new ContainerException("Circular dependency detected: {$abstract}");
        }

        $this->resolving[$abstract] = true;

        try {
            $instance = $this->build($definition, $parameters);

            // Store singleton instance
            if ($definition->isSingleton()) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Build an instance from a service definition.
     *
     * @param ServiceDefinition $definition Service definition
     * @param array<string, mixed> $parameters Optional parameters
     * @return object Resolved instance
     */
    private function build(ServiceDefinition $definition, array $parameters = []): object
    {
        $concrete = $definition->getConcrete();

        // If it's already an instance, return it
        if (is_object($concrete) && !is_callable($concrete)) {
            return $concrete;
        }

        // If it's a factory closure, call it
        if (is_callable($concrete)) {
            return $concrete($this, $parameters);
        }

        // If it's a class name, resolve it
        if (is_string($concrete)) {
            return $this->resolveClass($concrete, $parameters);
        }

        throw new ContainerException("Unable to build instance for: {$definition->getAbstract()}");
    }

    /**
     * Resolve a class with auto-wiring.
     *
     * @param string $class Class name
     * @param array<string, mixed> $parameters Optional parameters
     * @return object Resolved instance
     */
    private function resolveClass(string $class, array $parameters = []): object
    {
        try {
            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                throw new ContainerException("Class is not instantiable: {$class}");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return new $class();
            }

            $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

            return $reflection->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to resolve class: {$class}", 0, $e);
        }
    }

    /**
     * Resolve constructor dependencies.
     *
     * @param array<ReflectionParameter> $parameters Constructor parameters
     * @param array<string, mixed> $provided Provided parameters
     * @return array<int, mixed> Resolved dependencies
     */
    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // Use provided parameter if available
            if (isset($provided[$name])) {
                $dependencies[] = $provided[$name];
                continue;
            }

            // Try to resolve from container if type-hinted
            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($this->has($typeName)) {
                    $dependencies[] = $this->make($typeName);
                    continue;
                }

                // Try to auto-resolve class
                if (class_exists($typeName)) {
                    $dependencies[] = $this->resolveClass($typeName);
                    continue;
                }
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Optional parameter
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new ContainerException("Unable to resolve dependency: {$name} for parameter {$parameter->getDeclaringClass()?->getName()}::{$parameter->getDeclaringFunction()->getName()}");
        }

        return $dependencies;
    }
}

/**
 * Container exception.
 */
final class ContainerException extends \Exception
{
}



