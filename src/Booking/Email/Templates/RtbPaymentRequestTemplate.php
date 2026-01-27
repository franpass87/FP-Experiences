<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

use FP_Exp\Booking\EmailTranslator;

use function __;
use function esc_html;

use const FP_EXP_PLUGIN_DIR;

/**
 * Template for RTB payment request email to customer.
 */
final class RtbPaymentRequestTemplate extends AbstractEmailTemplate
{
    /**
     * Get template file path.
     */
    protected function getTemplatePath(): string
    {
        return FP_EXP_PLUGIN_DIR . 'templates/emails/rtb-payment-request.php';
    }

    /**
     * Get template name for subject override.
     */
    protected function getTemplateName(): string
    {
        return 'rtb_payment_request';
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
                __('Completa il pagamento per %s', 'fp-experiences'),
                esc_html($experience_title)
            );
        }

        return sprintf(
            /* translators: %s: experience title. */
            __('Complete your payment for %s', 'fp-experiences'),
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
