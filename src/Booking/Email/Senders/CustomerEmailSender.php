<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Integrations\Brevo;

/**
 * Email sender for customer emails.
 */
final class CustomerEmailSender extends AbstractEmailSender
{
    /**
     * Send email using template.
     *
     * @param EmailTemplateInterface $template
     * @param array<string, mixed> $context
     * @param bool $force_send Force send even if Brevo is enabled
     *
     * @return bool True if sent successfully
     */
    public function send(EmailTemplateInterface $template, array $context, bool $force_send = false): bool
    {
        // If Brevo is enabled and not forcing, let Brevo handle it
        if (! $force_send && $this->brevo instanceof Brevo && $this->brevo->is_enabled()) {
            return false;
        }

        return parent::send($template, $context);
    }
}















