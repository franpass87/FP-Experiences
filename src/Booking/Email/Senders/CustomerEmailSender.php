<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Utils\Logger;

use function get_class;

/**
 * Email sender for customer emails.
 *
 * When the provider is "brevo" and $force_send is false the local send
 * is skipped so that the Brevo pipeline can handle the transactional
 * email instead.  Passing $force_send = true always dispatches locally
 * (used for fallbacks and RTB templates).
 */
final class CustomerEmailSender extends AbstractEmailSender
{
    /**
     * @param EmailTemplateInterface $template
     * @param array<string, mixed>   $context
     * @param bool                   $force_send  Force local send even when Brevo is the active provider.
     */
    public function send(EmailTemplateInterface $template, array $context, bool $force_send = false): bool
    {
        if (! $force_send && 'brevo' === $this->mailer->getProvider()) {
            Logger::log('email', \sprintf(
                'CustomerEmailSender::send: Brevo provider active — delegating %s to Brevo pipeline',
                get_class($template)
            ));
            return false;
        }

        return parent::send($template, $context);
    }
}
