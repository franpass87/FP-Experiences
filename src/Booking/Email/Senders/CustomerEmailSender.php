<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Booking\Email\Templates\BookingFollowupTemplate;
use FP_Exp\Booking\Email\Templates\BookingReminderTemplate;
use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Utils\Logger;

use function get_class;
use function is_array;

/**
 * Email sender for customer emails.
 *
 * Con provider email «brevo», per ogni tipo di messaggio (conferma/RTB/riprogrammazione,
 * promemoria, follow-up) si rispetta il canale impostato in tab Brevo: con Brevo si salta
 * wp_mail per quel tipo; con WordPress si invia in locale anche se il provider è Brevo.
 * $force_send = true forza l'invio locale (fallback dopo transactional fallito, ecc.).
 */
final class CustomerEmailSender extends AbstractEmailSender
{
    private OptionsInterface $options;

    public function __construct(Mailer $mailer, ?OptionsInterface $options = null)
    {
        parent::__construct($mailer);
        $this->options = $options ?? new \FP_Exp\Services\Options\Options();
    }

    /**
     * @param EmailTemplateInterface $template
     * @param array<string, mixed>   $context
     * @param bool                   $force_send  Force local send even when Brevo is the active provider.
     */
    public function send(EmailTemplateInterface $template, array $context, bool $force_send = false): bool
    {
        if (! $force_send && $this->shouldDelegateCustomerMailToBrevoPipeline($template)) {
            Logger::log('email', \sprintf(
                'CustomerEmailSender::send: Brevo provider active — delegating %s to Brevo pipeline',
                get_class($template)
            ));
            return false;
        }

        return parent::send($template, $context);
    }

    /**
     * Bucket canale per template cliente (RTB e riprogrammazione = conferma).
     */
    private function customerMessageBucket(EmailTemplateInterface $template): string
    {
        if ($template instanceof BookingReminderTemplate) {
            return 'reminder';
        }

        if ($template instanceof BookingFollowupTemplate) {
            return 'followup';
        }

        return 'confirmation';
    }

    private function shouldDelegateCustomerMailToBrevoPipeline(EmailTemplateInterface $template): bool
    {
        if ('brevo' !== $this->mailer->getProvider()) {
            return false;
        }

        $brevo = new Brevo(null, $this->options);

        if (! $brevo->is_enabled()) {
            return false;
        }

        return $brevo->is_bucket_using_brevo($this->customerMessageBucket($template));
    }
}
