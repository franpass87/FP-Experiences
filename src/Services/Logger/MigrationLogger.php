<?php

declare(strict_types=1);

namespace FP_Exp\Services\Logger;

/**
 * Migration logger for tracking refactoring progress.
 */
final class MigrationLogger
{
    private LoggerInterface $logger;
    private const CHANNEL = 'migration';

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log migration phase start.
     *
     * @param string $phase Phase name
     * @param array<string, mixed> $context Additional context
     */
    public function phaseStart(string $phase, array $context = []): void
    {
        $this->logger->info(sprintf('[Migration] Phase started: %s', $phase), array_merge([
            'phase' => $phase,
            'type' => 'phase_start',
        ], $context));
    }

    /**
     * Log migration phase completion.
     *
     * @param string $phase Phase name
     * @param array<string, mixed> $context Additional context
     */
    public function phaseComplete(string $phase, array $context = []): void
    {
        $this->logger->info(sprintf('[Migration] Phase completed: %s', $phase), array_merge([
            'phase' => $phase,
            'type' => 'phase_complete',
        ], $context));
    }

    /**
     * Log service migration.
     *
     * @param string $service Service name
     * @param string $from From location
     * @param string $to To location
     */
    public function serviceMigrated(string $service, string $from, string $to): void
    {
        $this->logger->info(sprintf('[Migration] Service migrated: %s', $service), [
            'service' => $service,
            'from' => $from,
            'to' => $to,
            'type' => 'service_migrated',
        ]);
    }

    /**
     * Log class refactoring.
     *
     * @param string $class Class name
     * @param array<string, mixed> $context Additional context
     */
    public function classRefactored(string $class, array $context = []): void
    {
        $this->logger->info(sprintf('[Migration] Class refactored: %s', $class), array_merge([
            'class' => $class,
            'type' => 'class_refactored',
        ], $context));
    }

    /**
     * Log backward compatibility layer creation.
     *
     * @param string $component Component name
     */
    public function compatibilityLayerCreated(string $component): void
    {
        $this->logger->info(sprintf('[Migration] Compatibility layer created: %s', $component), [
            'component' => $component,
            'type' => 'compatibility_layer',
        ]);
    }

    /**
     * Log deprecated method usage.
     *
     * @param string $method Method name
     * @param string $replacement Replacement method
     */
    public function deprecatedUsed(string $method, string $replacement): void
    {
        $this->logger->warning(sprintf('[Migration] Deprecated method used: %s (use %s instead)', $method, $replacement), [
            'method' => $method,
            'replacement' => $replacement,
            'type' => 'deprecated_usage',
        ]);
    }
}







