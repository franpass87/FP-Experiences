<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Integrations\Brevo;

use function file_exists;
use function trim;
use function unlink;
use function wp_mail;

/**
 * Abstract base class for email senders.
 */
abstract class AbstractEmailSender implements EmailSenderInterface
{
    protected ?Brevo $brevo;

    public function __construct(?Brevo $brevo = null)
    {
        $this->brevo = $brevo;
    }

    /**
     * Send email using template.
     *
     * @param EmailTemplateInterface $template
     * @param array<string, mixed> $context
     *
     * @return bool True if sent successfully
     */
    public function send(EmailTemplateInterface $template, array $context): bool
    {
        if (! $template->shouldSend($context)) {
            return false;
        }

        $recipients = $template->getRecipients($context);
        $subject = $template->getSubject($context);
        $body = $template->getBody($context);
        $attachments = $template->getAttachments($context);

        if ('' === trim($body)) {
            return false;
        }

        $sent = $this->dispatch($recipients, $subject, $body, $attachments);

        $this->cleanupAttachments($attachments);

        return $sent;
    }

    /**
     * Dispatch email.
     *
     * @param array<string> $recipients
     * @param array<string> $attachments
     */
    protected function dispatch(array $recipients, string $subject, string $body, array $attachments = []): bool
    {
        if (empty($recipients)) {
            return false;
        }

        $to = $recipients[0];
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        // Add CC if multiple recipients
        if (count($recipients) > 1) {
            $cc = implode(', ', array_slice($recipients, 1));
            $headers[] = 'Cc: ' . $cc;
        }

        $sent = wp_mail($to, $subject, $body, $headers, $attachments);

        return $sent;
    }

    /**
     * Cleanup attachment files.
     *
     * @param array<string> $attachments
     */
    protected function cleanupAttachments(array $attachments): void
    {
        foreach ($attachments as $path) {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }
}















