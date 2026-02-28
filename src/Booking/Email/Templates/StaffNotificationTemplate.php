<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

use FP_Exp\Booking\EmailTranslator;
use FP_Exp\Utils\Logger;

use function __;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_values;
use function esc_html;
use function get_option;
use function sanitize_email;

use const FP_EXP_PLUGIN_DIR;

/**
 * Template for staff notification email.
 */
final class StaffNotificationTemplate extends AbstractEmailTemplate
{
    private bool $is_cancelled;

    public function __construct(bool $is_cancelled = false)
    {
        $this->is_cancelled = $is_cancelled;
    }

    /**
     * Get template file path.
     */
    protected function getTemplatePath(): string
    {
        return FP_EXP_PLUGIN_DIR . 'templates/emails/staff-notification.php';
    }

    /**
     * Get template name for subject override.
     */
    protected function getTemplateName(): string
    {
        return $this->is_cancelled ? 'staff_notification_cancelled' : 'staff_notification_new';
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

        if ($this->is_cancelled) {
            if (EmailTranslator::LANGUAGE_IT === $language) {
                return sprintf(
                    /* translators: %s: experience title. */
                    __('Prenotazione cancellata: %s', 'fp-experiences'),
                    esc_html($experience_title)
                );
            }

            return sprintf(
                /* translators: %s: experience title. */
                __('Booking cancelled: %s', 'fp-experiences'),
                esc_html($experience_title)
            );
        }

        if (EmailTranslator::LANGUAGE_IT === $language) {
            return sprintf(
                /* translators: %s: experience title. */
                __('Nuova prenotazione: %s', 'fp-experiences'),
                esc_html($experience_title)
            );
        }

        return sprintf(
            /* translators: %s: experience title. */
            __('New booking: %s', 'fp-experiences'),
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
        $structure = $this->getStructureEmail();
        $webmaster = $this->getWebmasterEmail();

        $recipients = array_filter([
            $structure,
            $webmaster,
        ]);

        $emails_settings = get_option('fp_exp_emails', []);
        if (is_array($emails_settings) && ! empty($emails_settings['recipients']['staff_extra']) && is_array($emails_settings['recipients']['staff_extra'])) {
            $extra = array_values(array_filter(array_map('sanitize_email', $emails_settings['recipients']['staff_extra'])));
            $recipients = array_merge($recipients, $extra);
        }

        /** @var array<int, string> $filtered */
        $filtered = apply_filters('fp_exp_email_recipients', $recipients, $context, 'staff');

        $filtered = array_map('sanitize_email', $filtered);
        $result = array_values(array_filter($filtered));

        if (empty($result)) {
            Logger::log('email', sprintf(
                'StaffNotificationTemplate::getRecipients: no recipients resolved (structure=%s, webmaster=%s, admin=%s)',
                $structure,
                $webmaster,
                sanitize_email((string) get_option('admin_email'))
            ));
        }

        return $result;
    }

    /**
     * Get email attachments (only for new bookings, not cancellations).
     *
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function getAttachments(array $context): array
    {
        if ($this->is_cancelled) {
            return [];
        }

        return parent::getAttachments($context);
    }

    /**
     * Get email body with cancellation flag.
     *
     * @param array<string, mixed> $context
     */
    public function getBody(array $context): string
    {
        $context['is_cancelled'] = $this->is_cancelled;

        return parent::getBody($context);
    }

    /**
     * Get structure email.
     */
    private function getStructureEmail(): string
    {
        $emails = get_option('fp_exp_emails', []);
        if (is_array($emails) && ! empty($emails['sender']['structure'])) {
            $candidate = sanitize_email((string) $emails['sender']['structure']);
            if ($candidate) {
                return $candidate;
            }
        }

        $option = (string) get_option('fp_exp_structure_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    /**
     * Get webmaster email.
     */
    private function getWebmasterEmail(): string
    {
        $emails = get_option('fp_exp_emails', []);
        if (is_array($emails) && ! empty($emails['sender']['webmaster'])) {
            $candidate = sanitize_email((string) $emails['sender']['webmaster']);
            if ($candidate) {
                return $candidate;
            }
        }

        $option = (string) get_option('fp_exp_webmaster_email', '');

        if ($option) {
            return sanitize_email($option);
        }

        return sanitize_email((string) get_option('admin_email'));
    }
}















