<?php

declare(strict_types=1);

namespace FP_Exp\Services\Logger;

use FP_Exp\Utils\Logger as LegacyLogger;

/**
 * Logger service implementation.
 * Wraps existing Logger class for backward compatibility.
 */
final class Logger implements LoggerInterface
{
    /**
     * Log a message with context.
     *
     * @param string $channel Log channel
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function log(string $channel, string $message, array $context = []): void
    {
        LegacyLogger::log($channel, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
}



