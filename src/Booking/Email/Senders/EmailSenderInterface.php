<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;

/**
 * Interface for email senders.
 */
interface EmailSenderInterface
{
    /**
     * Send email using template.
     *
     * @param EmailTemplateInterface $template
     * @param array<string, mixed> $context
     *
     * @return bool True if sent successfully
     */
    public function send(EmailTemplateInterface $template, array $context): bool;
}















