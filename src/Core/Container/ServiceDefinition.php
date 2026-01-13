<?php

declare(strict_types=1);

namespace FP_Exp\Core\Container;

/**
 * Service definition for container bindings.
 */
final class ServiceDefinition
{
    private string $abstract;
    private $concrete;
    private bool $singleton;
    private ?object $instance = null;

    /**
     * @param string $abstract Abstract class or interface name
     * @param string|callable|object $concrete Concrete class name, factory closure, or instance
     * @param bool $singleton Whether this is a singleton
     */
    public function __construct(string $abstract, $concrete, bool $singleton = false)
    {
        $this->abstract = $abstract;
        $this->concrete = $concrete;
        $this->singleton = $singleton;
    }

    public function getAbstract(): string
    {
        return $this->abstract;
    }

    /**
     * @return string|callable|object
     */
    public function getConcrete()
    {
        return $this->concrete;
    }

    public function isSingleton(): bool
    {
        return $this->singleton;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }

    public function setInstance(object $instance): void
    {
        $this->instance = $instance;
    }

    public function hasInstance(): bool
    {
        return $this->instance !== null;
    }
}



