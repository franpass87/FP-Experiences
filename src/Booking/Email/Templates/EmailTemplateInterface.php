<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

/**
 * Interface for email templates.
 */
interface EmailTemplateInterface
{
    /**
     * Get email subject.
     *
     * @param array<string, mixed> $context
     *
     * @return string
     */
    public function getSubject(array $context): string;

    /**
     * Get email body (HTML).
     *
     * @param array<string, mixed> $context
     *
     * @return string
     */
    public function getBody(array $context): string;

    /**
     * Get email recipients.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function getRecipients(array $context): array;

    /**
     * Get email attachments.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function getAttachments(array $context): array;

    /**
     * Check if email should be sent.
     *
     * @param array<string, mixed> $context
     *
     * @return bool
     */
    public function shouldSend(array $context): bool;
}















