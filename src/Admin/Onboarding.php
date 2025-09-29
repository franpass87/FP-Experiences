<?php

namespace FP_Exp\Admin;

/**
 * Handles the onboarding wizard for FP Experiences administrators.
 */
class Onboarding
{
    /**
     * Bootstraps onboarding hooks for admin users.
     */
    public function register(): void
    {
        // TODO: Hook into WordPress admin to display the onboarding wizard entry point.
    }

    /**
     * Renders the onboarding wizard screen in the admin area.
     */
    public function render(): void
    {
        // TODO: Output the onboarding wizard markup and enqueue its assets.
    }

    /**
     * Processes onboarding form submissions and persists settings.
     */
    public function handle_submission(): void
    {
        // TODO: Validate nonce-protected onboarding data and save plugin settings.
    }
}
