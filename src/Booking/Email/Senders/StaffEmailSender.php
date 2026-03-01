<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Utils\Logger;

use function get_class;
use function implode;
use function sprintf;

/**
 * Email sender for staff/admin emails.
 *
 * Always sends locally (never delegates to Brevo).
 */
final class StaffEmailSender extends AbstractEmailSender
{
    public function send(EmailTemplateInterface $template, array $context): bool
    {
        $template_class = get_class($template);
        $recipients = $template->getRecipients($context);

        Logger::log('email', sprintf(
            'StaffEmailSender::send: starting %s — recipients=[%s]',
            $template_class,
            $recipients ? implode(', ', $recipients) : 'NONE'
        ));

        $sent = parent::send($template, $context);

        Logger::log('email', sprintf(
            'StaffEmailSender::send: %s for %s',
            $sent ? 'OK' : 'FAILED',
            $template_class
        ));

        return $sent;
    }
}
