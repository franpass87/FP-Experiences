<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Email;

/**
 * Interface for email templates.
 *
 * Defines the contract for all voucher email templates.
 */
interface EmailTemplateInterface
{
    /**
     * Get email subject.
     *
     * @param array<string, mixed> $data
     */
    public function getSubject(array $data): string;

    /**
     * Get email body (HTML).
     *
     * @param array<string, mixed> $data
     */
    public function getBody(array $data): string;

    /**
     * Get email headers.
     *
     * @return array<int, string>
     */
    public function getHeaders(): array;
}















