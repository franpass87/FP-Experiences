<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email;

use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Utils\Logger;

use function add_action;
use function add_filter;
use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function function_exists;
use function implode;
use function is_array;
use function is_email;
use function remove_action;
use function remove_filter;
use function sanitize_email;
use function trim;
use function wp_mail;

/**
 * Centralised mailer for all FP-Experiences email dispatch.
 *
 * Supports three providers configured via fp_exp_emails['provider']:
 *  - wordpress : wp_mail() with explicit From header
 *  - smtp      : wp_mail() + PHPMailer SMTP override via phpmailer_init
 *  - brevo     : delegates to Brevo transactional API (handled externally)
 *
 * Every plugin email (booking, RTB, gift) MUST go through this service.
 */
final class Mailer
{
    private OptionsInterface $options;

    /** @var array<string, mixed>|null Cached settings */
    private ?array $settings = null;

    public function __construct(OptionsInterface $options)
    {
        $this->options = $options;
    }

    /**
     * Send an email through the configured provider.
     *
     * @param string[] $recipients
     * @param string[] $extraHeaders  Additional headers (Content-Type is always added).
     * @param string[] $attachments
     */
    public function send(
        array $recipients,
        string $subject,
        string $body,
        array $extraHeaders = [],
        array $attachments = []
    ): bool {
        $recipients = array_values(array_filter($recipients, static fn ($e) => is_email($e)));

        if (empty($recipients)) {
            Logger::log('email', 'Mailer::send — no valid recipients');
            return false;
        }

        if ('' === trim($body)) {
            Logger::log('email', 'Mailer::send — empty body, skipping');
            return false;
        }

        $settings = $this->getSettings();
        $provider = $this->getProvider();

        $headers = $this->buildHeaders($settings, $extraHeaders);

        $to = $recipients[0];
        if (count($recipients) > 1) {
            foreach (array_slice($recipients, 1) as $cc) {
                $headers[] = 'Cc: ' . $cc;
            }
        }

        if ('smtp' === $provider) {
            $this->hookSmtp($settings);
            $sent = wp_mail($to, $subject, $body, $headers, $attachments);
            $this->unhookSmtp();
        } elseif ('wordpress' === $provider && function_exists('WC') && WC()->mailer()) {
            $sent = $this->sendViaWcMailer($to, $subject, $body, $headers, $attachments);
        } else {
            $sent = wp_mail($to, $subject, $body, $headers, $attachments);
        }

        Logger::log('email', \sprintf(
            'Mailer::send [%s]: %s — to=%s subject="%s"',
            $provider,
            $sent ? 'OK' : 'FAILED',
            implode(', ', $recipients),
            $subject
        ));

        return (bool) $sent;
    }

    public function getProvider(): string
    {
        $settings = $this->getSettings();
        $provider = $settings['provider'] ?? 'wordpress';

        if (!\in_array($provider, ['wordpress', 'smtp', 'brevo'], true)) {
            return 'wordpress';
        }

        return $provider;
    }

    public function getFromEmail(): string
    {
        $settings = $this->getSettings();
        $email = sanitize_email((string) ($settings['from_email'] ?? ''));

        if ($email) {
            return $email;
        }

        if (!empty($settings['sender']['structure'])) {
            return sanitize_email((string) $settings['sender']['structure']);
        }

        return sanitize_email((string) \get_option('admin_email'));
    }

    public function getFromName(): string
    {
        $settings = $this->getSettings();
        $name = trim((string) ($settings['from_name'] ?? ''));

        return $name !== '' ? $name : (string) \get_bloginfo('name');
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $raw = $this->options->get('fp_exp_emails', []);
        $this->settings = is_array($raw) ? $raw : [];

        return $this->settings;
    }

    /**
     * Build the full set of headers for wp_mail.
     *
     * @param array<string, mixed> $settings
     * @param string[]             $extra
     * @return string[]
     */
    private function buildHeaders(array $settings, array $extra): array
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $from_email = $this->getFromEmail();
        $from_name  = $this->getFromName();

        if ($from_email) {
            $headers[] = \sprintf('From: %s <%s>', $from_name, $from_email);
        }

        foreach ($extra as $h) {
            if (\is_string($h) && '' !== trim($h)) {
                $headers[] = $h;
            }
        }

        return $headers;
    }

    /**
     * Send through WC_Mailer, bypassing WC's template wrapper.
     *
     * WC_Email::send() adds wp_mail_from / wp_mail_from_name filters and
     * calls wp_mail() — the same pipeline used by all WooCommerce emails.
     * We override woocommerce_mail_content so WC does not wrap our
     * already-branded body with its own header/footer.
     *
     * Headers: we strip our From header and let WC handle it via its
     * own filters; if the plugin has a custom From configured we
     * temporarily override WC's values at higher priority.
     *
     * @param string   $to
     * @param string   $subject
     * @param string   $body
     * @param string[] $headers
     * @param string[] $attachments
     */
    private function sendViaWcMailer(string $to, string $subject, string $body, array $headers, array $attachments): bool
    {
        $raw = $body;
        $bypassContent = static function () use ($raw): string { return $raw; };
        add_filter('woocommerce_mail_content', $bypassContent, \PHP_INT_MAX);

        $wcHeaders = array_filter($headers, static function (string $h): bool {
            return 0 !== \strncasecmp($h, 'From:', 5);
        });

        $fromOverrides = [];
        $pluginFromEmail = $this->getFromEmail();
        $pluginFromName  = $this->getFromName();

        if ($pluginFromEmail) {
            $fromEmailCb = static function () use ($pluginFromEmail): string { return $pluginFromEmail; };
            add_filter('wp_mail_from', $fromEmailCb, 99);
            $fromOverrides[] = ['wp_mail_from', $fromEmailCb, 99];
        }

        if ($pluginFromName !== '') {
            $fromNameCb = static function () use ($pluginFromName): string { return $pluginFromName; };
            add_filter('wp_mail_from_name', $fromNameCb, 99);
            $fromOverrides[] = ['wp_mail_from_name', $fromNameCb, 99];
        }

        $sent = WC()->mailer()->send($to, $subject, $body, implode("\r\n", $wcHeaders), $attachments);

        remove_filter('woocommerce_mail_content', $bypassContent, \PHP_INT_MAX);
        foreach ($fromOverrides as [$filter, $cb, $prio]) {
            remove_filter($filter, $cb, $prio);
        }

        return (bool) $sent;
    }

    /**
     * Temporarily hook into phpmailer_init to override SMTP settings.
     *
     * @param array<string, mixed> $settings
     */
    private function hookSmtp(array $settings): void
    {
        $smtp = isset($settings['smtp']) && is_array($settings['smtp']) ? $settings['smtp'] : [];

        $host       = trim((string) ($smtp['host'] ?? ''));
        $port       = (int) ($smtp['port'] ?? 587);
        $username   = (string) ($smtp['username'] ?? '');
        $password   = (string) ($smtp['password'] ?? '');
        $encryption = (string) ($smtp['encryption'] ?? 'tls');

        if ('' === $host) {
            return;
        }

        add_action('phpmailer_init', $callback = static function ($phpmailer) use ($host, $port, $username, $password, $encryption): void {
            $phpmailer->isSMTP();
            $phpmailer->Host       = $host;
            $phpmailer->Port       = $port;
            $phpmailer->SMTPSecure = 'none' === $encryption ? '' : $encryption;
            $phpmailer->SMTPAuth   = '' !== $username;

            if ('' !== $username) {
                $phpmailer->Username = $username;
                $phpmailer->Password = $password;
            }
        }, 99999);

        $this->smtpCallback = $callback;
    }

    /** @var callable|null */
    private $smtpCallback = null;

    private function unhookSmtp(): void
    {
        if ($this->smtpCallback !== null) {
            remove_action('phpmailer_init', $this->smtpCallback, 99999);
            $this->smtpCallback = null;
        }
    }
}
