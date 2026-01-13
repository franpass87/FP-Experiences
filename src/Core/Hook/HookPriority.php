<?php

declare(strict_types=1);

namespace FP_Exp\Core\Hook;

/**
 * Standard hook priorities for consistent hook registration.
 */
final class HookPriority
{
    /**
     * Core hooks - highest priority, run first.
     */
    public const CORE = 5;

    /**
     * Default priority - standard hook registration.
     */
    public const DEFAULT = 10;

    /**
     * Integration hooks - run after core and default hooks.
     */
    public const INTEGRATION = 20;

    /**
     * Late hooks - run near the end.
     */
    public const LATE = 90;

    /**
     * Theme override - lowest priority, allows themes to override.
     */
    public const THEME_OVERRIDE = 100;

    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
    }
}







