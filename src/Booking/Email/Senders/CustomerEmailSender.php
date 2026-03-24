<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Utils\Logger;

use function get_class;
use function is_array;
use function sanitize_key;

/**
 * Email sender for customer emails.
 *
 * When il provider email è "brevo" e in tab Brevo è attivo il canale
 * messaggi "Brevo", l'invio locale viene saltato (pipeline Brevo).
 * Con canale WordPress si usa sempre wp_mail anche se il provider è "brevo".
 * $force_send = true forza l'invio locale (fallback, RTB, reminder locali).
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
        if (! $force_send && $this->shouldDelegateCustomerMailToBrevoPipeline()) {
            Logger::log('email', \sprintf(
                'CustomerEmailSender::send: Brevo provider active — delegating %s to Brevo pipeline',
                get_class($template)
            ));
            return false;
        }

        return parent::send($template, $context);
    }

    private function shouldDelegateCustomerMailToBrevoPipeline(): bool
    {
        if ('brevo' !== $this->mailer->getProvider()) {
            return false;
        }

        $brevo = $this->options->get('fp_exp_brevo', []);
        $brevo = is_array($brevo) ? $brevo : [];
        $channel = isset($brevo['customer_messages_channel'])
            ? sanitize_key((string) $brevo['customer_messages_channel'])
            : Brevo::CUSTOMER_CHANNEL_WORDPRESS;

        return Brevo::CUSTOMER_CHANNEL_BREVO === $channel;
    }
}
