<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Utils\Logger;

use function file_exists;
use function function_exists;
use function get_class;
use function implode;
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
        $template_class = get_class($template);

        if (! $template->shouldSend($context)) {
            $recipients = $template->getRecipients($context);
            Logger::log('email', sprintf(
                'AbstractEmailSender::send: shouldSend=false for %s (recipients resolved: %s)',
                $template_class,
                $recipients ? implode(', ', $recipients) : 'NONE'
            ));
            return false;
        }

        $recipients = $template->getRecipients($context);
        $subject = $template->getSubject($context);
        $body = $template->getBody($context);
        $attachments = $template->getAttachments($context);

        if ('' === trim($body)) {
            Logger::log('email', sprintf(
                'AbstractEmailSender::send: empty body for %s (recipients: %s)',
                $template_class,
                implode(', ', $recipients)
            ));
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
            Logger::log('email', sprintf(
                'AbstractEmailSender::dispatch: no recipients for subject "%s"',
                $subject
            ));
            return false;
        }

        $to = $recipients[0];
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        if (count($recipients) > 1) {
            $cc = implode(', ', array_slice($recipients, 1));
            $headers[] = 'Cc: ' . $cc;
        }

        if (function_exists('WC') && WC()->mailer()) {
            $sent = WC()->mailer()->send($to, $subject, $body, implode("\r\n", $headers), $attachments);
        } else {
            $sent = wp_mail($to, $subject, $body, $headers, $attachments);
        }

        Logger::log('email', sprintf(
            'AbstractEmailSender::dispatch: %s to=%s subject="%s"',
            $sent ? 'OK' : 'FAILED',
            implode(', ', $recipients),
            $subject
        ));

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















