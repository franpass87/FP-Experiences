<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

use FP_Exp\Booking\EmailTranslator;
use FP_Exp\Utils\Logger;

use function __;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function get_option;
use function sanitize_email;
use function sprintf;

use const FP_EXP_PLUGIN_DIR;

/**
 * Template for staff notification when a reservation is rescheduled.
 */
final class StaffRescheduledTemplate extends AbstractEmailTemplate
{
    protected function getTemplatePath(): string
    {
        return FP_EXP_PLUGIN_DIR . 'templates/emails/staff-rescheduled.php';
    }

    protected function getTemplateName(): string
    {
        return 'staff_notification_rescheduled';
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
                __('Prenotazione riprogrammata: %s', 'fp-experiences'),
                $this->plainTextForEmailSubject((string) $experience_title)
            );
        }

        return sprintf(
            /* translators: %s: experience title. */
            __('Reservation rescheduled: %s', 'fp-experiences'),
            $this->plainTextForEmailSubject((string) $experience_title)
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string>
     */
    public function getRecipients(array $context): array
    {
        $structure = $this->getStructureEmail();
        $webmaster = $this->getWebmasterEmail();

        $recipients = array_filter([$structure, $webmaster]);

        $emails_settings = get_option('fp_exp_emails', []);
        if (is_array($emails_settings) && ! empty($emails_settings['recipients']['staff_extra']) && is_array($emails_settings['recipients']['staff_extra'])) {
            $extra = array_values(array_filter(array_map('sanitize_email', $emails_settings['recipients']['staff_extra'])));
            $recipients = array_merge($recipients, $extra);
        }

        /** @var array<int, string> $filtered */
        $filtered = apply_filters('fp_exp_email_recipients', $recipients, $context, 'staff');
        $filtered = array_map('sanitize_email', $filtered);
        $result = array_values(array_unique(array_filter($filtered)));

        if (empty($result)) {
            Logger::log('email', sprintf(
                'StaffRescheduledTemplate::getRecipients: no recipients resolved (structure=%s, webmaster=%s, admin=%s)',
                $structure,
                $webmaster,
                sanitize_email((string) get_option('admin_email'))
            ));
        }

        return $result;
    }

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
