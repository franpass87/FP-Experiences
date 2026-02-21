<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Booking\Email\Templates\EmailTemplateInterface;
use FP_Exp\Integrations\Brevo;

use function defined;
use function error_log;
use function get_class;

/**
 * Email sender for customer emails.
 */
final class CustomerEmailSender extends AbstractEmailSender
{
    /**
     * Send email using template.
     *
     * @param EmailTemplateInterface $template
     * @param array<string, mixed> $context
     * @param bool $force_send Force send even if Brevo is enabled
     *
     * @return bool True if sent successfully
     */
    public function send(EmailTemplateInterface $template, array $context, bool $force_send = false): bool
    {
        if (! $force_send && $this->brevo instanceof Brevo && $this->brevo->is_enabled()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-Exp CustomerEmail] Brevo enabled — delegating ' . get_class($template) . ' to Brevo pipeline');
            }
            return false;
        }

        $sent = parent::send($template, $context);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $recipient = $context['customer']['email'] ?? 'unknown';
            error_log('[FP-Exp CustomerEmail] Local send ' . get_class($template) . ' to ' . $recipient . ': ' . ($sent ? 'OK' : 'FAILED'));
        }

        return $sent;
    }
}















