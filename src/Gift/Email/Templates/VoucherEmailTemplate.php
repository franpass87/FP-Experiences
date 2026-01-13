<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Email\Templates;

use FP_Exp\Gift\Email\EmailTemplateInterface;
use FP_Exp\Utils\Helpers;
use WP_Post;

use function add_query_arg;
use function date_i18n;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_option;
use function get_permalink;
use function number_format;
use function sanitize_email;
use function strtoupper;

/**
 * Template for main voucher email.
 */
final class VoucherEmailTemplate implements EmailTemplateInterface
{
    /**
     * Get email subject.
     *
     * @param array<string, mixed> $data
     */
    public function getSubject(array $data): string
    {
        $experience_title = $data['experience_title'] ?? esc_html__('FP Experience', 'fp-experiences');

        return sprintf(
            /* translators: %s: experience title. */
            esc_html__('You received a gift: %s', 'fp-experiences'),
            $experience_title
        );
    }

    /**
     * Get email body (HTML).
     *
     * @param array<string, mixed> $data
     */
    public function getBody(array $data): string
    {
        $code = strtoupper((string) ($data['code'] ?? ''));
        $experience_title = (string) ($data['experience_title'] ?? '');
        $experience_permalink = (string) ($data['experience_permalink'] ?? '');
        $value = (float) ($data['value'] ?? 0.0);
        $currency = (string) ($data['currency'] ?? 'EUR');
        $valid_until = (int) ($data['valid_until'] ?? 0);

        $message = '<p>' . esc_html__('You have received a gift voucher for an FP Experience!', 'fp-experiences') . '</p>';

        if ($experience_title) {
            $message .= '<p><strong>' . esc_html($experience_title) . '</strong></p>';
        }

        // Instructions section
        $message .= '<h3>' . esc_html__('Come usare il tuo regalo:', 'fp-experiences') . '</h3>';
        $message .= '<p>' . esc_html__('Il tuo codice regalo è anche un coupon sconto da usare al checkout:', 'fp-experiences') . '</p>';
        $message .= '<p><strong style="font-size: 18px; color: #2e7d32;">' . esc_html($code) . '</strong></p>';

        if ($value > 0) {
            $message .= '<p>' . sprintf(
                esc_html__('Valore: %s %s', 'fp-experiences'),
                number_format($value, 2, ',', '.'),
                esc_html($currency)
            ) . '</p>';
        }

        if ($valid_until > 0) {
            $message .= '<p>' . esc_html__('Valido fino al:', 'fp-experiences') . ' <strong>' . esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)) . '</strong></p>';
        }

        $message .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
        $message .= '<h4>' . esc_html__('Istruzioni:', 'fp-experiences') . '</h4>';
        $message .= '<ol style="line-height: 1.8;">';
        $message .= '<li>' . esc_html__('Visita la pagina dell\'esperienza e scegli data e orario', 'fp-experiences') . '</li>';
        $message .= '<li>' . esc_html__('Aggiungi al carrello e procedi al checkout', 'fp-experiences') . '</li>';
        $message .= '<li>' . esc_html__('Inserisci il codice coupon durante il pagamento', 'fp-experiences') . '</li>';
        $message .= '<li>' . esc_html__('Lo sconto verrà applicato automaticamente!', 'fp-experiences') . '</li>';
        $message .= '</ol>';

        if ($experience_permalink) {
            $message .= '<p style="text-align: center; margin-top: 30px;">';
            $message .= '<a href="' . esc_url($experience_permalink) . '" style="display: inline-block; padding: 12px 30px; background-color: #2e7d32; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">';
            $message .= esc_html__('Prenota ora', 'fp-experiences');
            $message .= '</a>';
            $message .= '</p>';
        }

        return $message;
    }

    /**
     * Get email headers.
     *
     * @return array<int, string>
     */
    public function getHeaders(): array
    {
        return ['Content-Type: text/html; charset=UTF-8'];
    }

    /**
     * Build data array for template.
     *
     * @return array<string, mixed>
     */
    public static function buildData(int $voucher_id, ?WP_Post $experience, string $code, float $value, string $currency, int $valid_until): array
    {
        return [
            'code' => $code,
            'experience_title' => $experience instanceof WP_Post ? $experience->post_title : '',
            'experience_permalink' => $experience instanceof WP_Post ? get_permalink($experience) : '',
            'value' => $value,
            'currency' => $currency,
            'valid_until' => $valid_until,
        ];
    }
}















