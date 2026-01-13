<?php

declare(strict_types=1);

namespace FP_Exp\Services\Logger;

/**
 * Logger service interface.
 */
interface LoggerInterface
{
    /**
     * Log a message with context.
     *
     * @param string $channel Log channel
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function log(string $channel, string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log an info message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a warning message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log an error message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function error(string $message, array $context = []): void;
}



