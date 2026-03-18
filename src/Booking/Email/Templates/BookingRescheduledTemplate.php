<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

use FP_Exp\Booking\EmailTranslator;

use function __;
use function esc_html;
use function sanitize_email;
use function sprintf;

use const FP_EXP_PLUGIN_DIR;

/**
 * Template for customer email sent after reservation date change.
 */
final class BookingRescheduledTemplate extends AbstractEmailTemplate
{
    protected function getTemplatePath(): string
    {
        return FP_EXP_PLUGIN_DIR . 'templates/emails/customer-rescheduled.php';
    }

    protected function getTemplateName(): string
    {
        return 'customer_rescheduled';
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function getDefaultSubject(array $context): string
    {
        $language = $this->resolveLanguage($context);
        $experience_title = $context['experience']['title'] ?? '';

        if (EmailTranslator::LANGUAGE_IT === $language) {
            return sprintf(
                /* translators: %s: experience title. */
                __('Aggiornamento prenotazione: %s', 'fp-experiences'),
                esc_html((string) $experience_title)
            );
        }

        return sprintf(
            /* translators: %s: experience title. */
            __('Reservation updated: %s', 'fp-experiences'),
            esc_html((string) $experience_title)
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string>
     */
    public function getRecipients(array $context): array
    {
        $email = $context['customer']['email'] ?? '';

        if (! $email) {
            return [];
        }

        return [sanitize_email((string) $email)];
    }
}
