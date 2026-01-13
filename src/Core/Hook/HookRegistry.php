<?php

declare(strict_types=1);

namespace FP_Exp\Core\Hook;

/**
 * Centralized registry for WordPress hooks.
 */
final class HookRegistry
{
    /**
     * @var array<int, array{hook: string, callback: callable, priority: int, args: int}>
     */
    private array $hooks = [];

    /**
     * Register a WordPress hook.
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Hook priority (use HookPriority constants)
     * @param int $args Number of arguments
     */
    public function register(string $hook, callable $callback, int $priority = HookPriority::DEFAULT, int $args = 1): void
    {
        $this->hooks[] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];

        // Register as both action and filter (WordPress treats them the same)
        add_action($hook, $callback, $priority, $args);
    }

    /**
     * Register a WordPress action.
     *
     * @param string $hook Action name
     * @param callable $callback Callback function
     * @param int $priority Hook priority
     * @param int $args Number of arguments
     */
    public function registerAction(string $hook, callable $callback, int $priority = 10, int $args = 1): void
    {
        $this->register($hook, $callback, $priority, $args);
    }

    /**
     * Register a WordPress filter.
     *
     * @param string $hook Filter name
     * @param callable $callback Callback function
     * @param int $priority Hook priority
     * @param int $args Number of arguments
     */
    public function registerFilter(string $hook, callable $callback, int $priority = 10, int $args = 1): void
    {
        $this->register($hook, $callback, $priority, $args);
    }

    /**
     * Get all registered hooks.
     *
     * @return array<int, array{hook: string, callback: callable, priority: int, args: int}>
     */
    public function getRegisteredHooks(): array
    {
        return $this->hooks;
    }

    /**
     * Get hooks by name.
     *
     * @param string $hook Hook name
     * @return array<int, array{hook: string, callback: callable, priority: int, args: int}>
     */
    public function getHooksByName(string $hook): array
    {
        return array_filter($this->hooks, static function (array $registered) use ($hook): bool {
            return $registered['hook'] === $hook;
        });
    }
}

