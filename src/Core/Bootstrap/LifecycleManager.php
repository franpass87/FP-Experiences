<?php

declare(strict_types=1);

namespace FP_Exp\Core\Bootstrap;

use FP_Exp\Activation;
use FP_Exp\Services\Logger\LoggerInterface;

/**
 * Lifecycle manager for plugin activation and deactivation.
 */
final class LifecycleManager
{
    private ?LoggerInterface $logger = null;

    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Activate the plugin.
     */
    public function activate(): void
    {
        $this->log('info', 'Plugin activation started', [
            'version' => defined('FP_EXP_VERSION') ? FP_EXP_VERSION : 'unknown',
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
        ]);

        try {
            // Delegate to existing Activation class for backward compatibility
            if (class_exists(Activation::class)) {
                Activation::activate();
                $this->log('info', 'Plugin activated successfully via Activation class');
            } else {
                $this->log('error', 'Activation class not found');
            }
        } catch (\Throwable $e) {
            $this->log('error', 'Plugin activation failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Deactivate the plugin.
     */
    public function deactivate(): void
    {
        $this->log('info', 'Plugin deactivation started');

        try {
            // Delegate to existing Activation class for backward compatibility
            if (class_exists(Activation::class)) {
                Activation::deactivate();
                $this->log('info', 'Plugin deactivated successfully via Activation class');
            } else {
                $this->log('error', 'Activation class not found');
            }
        } catch (\Throwable $e) {
            $this->log('error', 'Plugin deactivation failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw on deactivation - log and continue
        }
    }

    /**
     * Log a message.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            // Try to get logger from container if available
            $kernel = Bootstrap::kernel();
            if ($kernel !== null) {
                $container = $kernel->container();
                if ($container->has(LoggerInterface::class)) {
                    $this->logger = $container->make(LoggerInterface::class);
                }
            }
        }

        if ($this->logger !== null) {
            $this->logger->log('lifecycle', sprintf('[%s] %s', strtoupper($level), $message), array_merge([
                'level' => $level,
            ], $context));
        }
    }
}







