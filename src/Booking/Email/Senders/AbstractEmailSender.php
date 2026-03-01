<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Utils\Logger;

use function file_exists;
use function get_class;
use function implode;
use function is_string;
use function trim;
use function unlink;

/**
 * Abstract base class for email senders.
 *
 * All dispatch is delegated to the centralised Mailer service.
 */
abstract class AbstractEmailSender implements EmailSenderInterface
{
    protected Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send email using template.
     *
     * @param EmailTemplateInterface $template
     * @param array<string, mixed>   $context
     *
     * @return bool True if sent successfully
     */
    public function send(EmailTemplateInterface $template, array $context): bool
    {
        $template_class = get_class($template);

        if (! $template->shouldSend($context)) {
            $recipients = $template->getRecipients($context);
            Logger::log('email', \sprintf(
                'AbstractEmailSender::send: shouldSend=false for %s (recipients resolved: %s)',
                $template_class,
                $recipients ? implode(', ', $recipients) : 'NONE'
            ));
            return false;
        }

        $recipients  = $template->getRecipients($context);
        $subject     = $template->getSubject($context);
        $body        = $template->getBody($context);
        $attachments = $template->getAttachments($context);

        if ('' === trim($body)) {
            Logger::log('email', \sprintf(
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
     * Dispatch email via the centralised Mailer.
     *
     * @param string[] $recipients
     * @param string[] $attachments
     */
    protected function dispatch(array $recipients, string $subject, string $body, array $attachments = []): bool
    {
        if (empty($recipients)) {
            Logger::log('email', \sprintf(
                'AbstractEmailSender::dispatch: no recipients for subject "%s"',
                $subject
            ));
            return false;
        }

        return $this->mailer->send($recipients, $subject, $body, [], $attachments);
    }

    /**
     * Cleanup attachment files.
     *
     * @param string[] $attachments
     */
    protected function cleanupAttachments(array $attachments): void
    {
        foreach ($attachments as $path) {
            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
