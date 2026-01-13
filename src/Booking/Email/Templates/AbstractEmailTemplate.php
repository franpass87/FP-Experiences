<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Templates;

use FP_Exp\Booking\EmailTranslator;

use function apply_filters;
use function file_exists;
use function ob_get_clean;
use function ob_start;
use function sanitize_email;

use const FP_EXP_PLUGIN_DIR;

/**
 * Abstract base class for email templates.
 */
abstract class AbstractEmailTemplate implements EmailTemplateInterface
{
    /**
     * Get template file path.
     */
    abstract protected function getTemplatePath(): string;

    /**
     * Get template name for subject override.
     */
    abstract protected function getTemplateName(): string;

    /**
     * Get default subject.
     *
     * @param array<string, mixed> $context
     */
    abstract protected function getDefaultSubject(array $context): string;

    /**
     * Get email subject.
     *
     * @param array<string, mixed> $context
     */
    public function getSubject(array $context): string
    {
        $language = $this->resolveLanguage($context);
        $default_subject = $this->getDefaultSubject($context);

        return $this->resolveSubjectOverride($this->getTemplateName(), $context, $language, $default_subject);
    }

    /**
     * Get email body (HTML).
     *
     * @param array<string, mixed> $context
     */
    public function getBody(array $context): string
    {
        $path = $this->getTemplatePath();

        if (! file_exists($path)) {
            return '';
        }

        $language = $this->resolveLanguage($context);

        ob_start();
        $email_context = $context;
        $email_language = $language;
        include $path;

        $message = (string) ob_get_clean();

        return $this->applyBranding($message, $language);
    }

    /**
     * Get email attachments.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function getAttachments(array $context): array
    {
        $ics = $context['ics']['content'] ?? '';

        if (! is_string($ics) || '' === trim($ics)) {
            return [];
        }

        $filename = $context['ics']['filename'] ?? 'fp-experience.ics';

        // Use ICS class to create file
        if (class_exists(\FP_Exp\Booking\ICS::class)) {
            $path = \FP_Exp\Booking\ICS::create_file($ics, $filename);

            return $path ? [$path] : [];
        }

        return [];
    }

    /**
     * Check if email should be sent.
     *
     * @param array<string, mixed> $context
     */
    public function shouldSend(array $context): bool
    {
        $recipients = $this->getRecipients($context);

        return ! empty($recipients);
    }

    /**
     * Resolve language from context.
     *
     * @param array<string, mixed> $context
     */
    protected function resolveLanguage(array $context): string
    {
        $language = $context['language'] ?? EmailTranslator::LANGUAGE_IT;

        return EmailTranslator::normalize($language);
    }

    /**
     * Resolve subject override from settings.
     *
     * @param array<string, mixed> $context
     */
    protected function resolveSubjectOverride(string $template_name, array $context, string $language, string $default): string
    {
        $emails_settings = get_option('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];

        $subjects = isset($emails_settings['subjects']) && is_array($emails_settings['subjects']) ? $emails_settings['subjects'] : [];

        $override = $subjects[$template_name] ?? null;

        if (is_string($override) && '' !== trim($override)) {
            return $override;
        }

        return $default;
    }

    /**
     * Apply branding to email message.
     */
    protected function applyBranding(string $message, string $language): string
    {
        // Apply branding logic (simplified version)
        return apply_filters('fp_exp_email_branding', $message, $language);
    }
}















