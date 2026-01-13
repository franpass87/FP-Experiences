<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

use FP_Exp\Booking\EmailTranslator;

use function __;
use function esc_html;

use const FP_EXP_PLUGIN_DIR;

/**
 * Template for booking confirmation email to customer.
 */
final class BookingConfirmationTemplate extends AbstractEmailTemplate
{
    /**
     * Get template file path.
     */
    protected function getTemplatePath(): string
    {
        return FP_EXP_PLUGIN_DIR . 'templates/emails/customer-confirmation.php';
    }

    /**
     * Get template name for subject override.
     */
    protected function getTemplateName(): string
    {
        return 'customer_confirmation';
    }

    /**
     * Get default subject.
     *
     * @param array<string, mixed> $context
     */
    protected function getDefaultSubject(array $context): string
    {
        $language = $this->resolveLanguage($context);
        $experience_title = $context['experience']['title'] ?? '';

        if (EmailTranslator::LANGUAGE_IT === $language) {
            return sprintf(
                /* translators: %s: experience title. */
                __('Conferma prenotazione: %s', 'fp-experiences'),
                esc_html($experience_title)
            );
        }

        return sprintf(
            /* translators: %s: experience title. */
            __('Booking confirmation: %s', 'fp-experiences'),
            esc_html($experience_title)
        );
    }

    /**
     * Get email recipients.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function getRecipients(array $context): array
    {
        $email = $context['customer']['email'] ?? '';

        if (! $email) {
            return [];
        }

        return [sanitize_email($email)];
    }
}















