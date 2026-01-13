<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Booking\Cart;
use FP_Exp\Utils\Theme;
use WP_Error;

use function esc_html__;
use function function_exists;
use function get_locale;
use function get_option;
use function is_array;
use function number_format_i18n;
use function sanitize_text_field;
use function sprintf;
use function wp_create_nonce;
use function wp_json_encode;
use function wc_price;

final class CheckoutShortcode extends BaseShortcode
{
    private ?GetSettingsUseCase $getSettingsUseCase = null;

    protected string $tag = 'fp_exp_checkout';

    protected string $template = 'front/checkout.php';

    protected array $defaults = [
        'preset' => '',
        'mode' => '',
        'primary' => '',
        'secondary' => '',
        'accent' => '',
        'background' => '',
        'surface' => '',
        'text' => '',
        'muted' => '',
        'success' => '',
        'warning' => '',
        'danger' => '',
        'radius' => '',
        'shadow' => '',
        'font' => '',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $theme = Theme::resolve_palette([
            'preset' => (string) $attributes['preset'],
            'mode' => (string) $attributes['mode'],
            'primary' => (string) $attributes['primary'],
            'secondary' => (string) $attributes['secondary'],
            'accent' => (string) $attributes['accent'],
            'background' => (string) $attributes['background'],
            'surface' => (string) $attributes['surface'],
            'text' => (string) $attributes['text'],
            'muted' => (string) $attributes['muted'],
            'success' => (string) $attributes['success'],
            'warning' => (string) $attributes['warning'],
            'danger' => (string) $attributes['danger'],
            'radius' => (string) $attributes['radius'],
            'shadow' => (string) $attributes['shadow'],
            'font' => (string) $attributes['font'],
        ]);

        $cart = Cart::instance();

        if (! $cart->has_items()) {
            return new WP_Error('fp_exp_checkout_empty', esc_html__('Il carrello esperienze è vuoto.', 'fp-experiences'));
        }

        $conflict = $cart->check_woocommerce_cart_state();

        if ($conflict instanceof WP_Error) {
            return $conflict;
        }

        $cart_items = $this->prepare_items_for_display($cart->get_items());
        $totals = $cart->get_totals();
        $total_formatted = $this->format_money((float) $totals['total'], (string) $totals['currency']);

        $strings = [
            'contact_details' => esc_html__('Dati di contatto', 'fp-experiences'),
            'billing_details' => esc_html__('Indirizzo di fatturazione', 'fp-experiences'),
            'payment_details' => esc_html__('Metodo di pagamento', 'fp-experiences'),
            'order_review' => esc_html__('Riepilogo ordine', 'fp-experiences'),
            'submit' => esc_html__('Conferma e paga', 'fp-experiences'),
            'cart_locked' => esc_html__('Il carrello è bloccato mentre completi il pagamento.', 'fp-experiences'),
        ];

        // Try to use new use case if available
        $useCase = $this->getGetSettingsUseCase();
        $currency = 'EUR';
        if ($useCase !== null) {
            $currency = (string) $useCase->get('woocommerce_currency', 'EUR');
        } else {
            // Fallback to direct get_option for backward compatibility
            $currency = get_option('woocommerce_currency', 'EUR');
        }

        return [
            'theme' => $theme,
            'nonce' => '', // NON generare nonce qui - verrà richiesto via AJAX per evitare problemi di cache
            'locale' => get_locale(),
            'strings' => $strings,
            'currency' => $currency,
            'cart_items' => $cart_items,
            'cart_totals' => $totals,
            'total_formatted' => $total_formatted,
            'cart_locked' => $cart->is_locked(),
            'schema_json' => wp_json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Order',
                'acceptedOffer' => [
                    '@type' => 'Offer',
                    'priceCurrency' => $currency,
                ],
            ]),
        ];
    }

    protected function get_asset_handle(): string
    {
        return 'checkout';
    }

    protected function should_disable_cache(): bool
    {
        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepare_items_for_display(array $items): array
    {
        $prepared = [];

        foreach ($items as $item) {
            $title = sanitize_text_field((string) ($item['title'] ?? esc_html__('Prenotazione esperienza', 'fp-experiences')));
            $slot = sanitize_text_field((string) ($item['slot_label'] ?? ($item['slot_start'] ?? '')));
            $tickets = $this->format_ticket_lines($item['tickets'] ?? []);
            $addons = $this->format_addon_lines($item['addons'] ?? []);
            $total = (float) ($item['totals']['total'] ?? 0.0);

            $prepared[] = [
                'title' => $title,
                'slot' => $slot,
                'tickets' => $tickets,
                'addons' => $addons,
                'total' => $total,
                'total_formatted' => $this->format_money($total, $currency),
            ];
        }

        return $prepared;
    }

    /**
     * @param mixed $tickets
     *
     * @return array<int, string>
     */
    private function format_ticket_lines($tickets): array
    {
        if (! is_array($tickets)) {
            return [];
        }

        $lines = [];

        foreach ($tickets as $key => $ticket) {
            if (is_array($ticket)) {
                $label = sanitize_text_field((string) ($ticket['label'] ?? $key));
                $quantity = (int) ($ticket['quantity'] ?? ($ticket['qty'] ?? 0));
            } else {
                $label = sanitize_text_field((string) $key);
                $quantity = (int) $ticket;
            }

            if ($quantity <= 0) {
                continue;
            }

            $lines[] = sprintf('%s × %d', $label, $quantity);
        }

        return $lines;
    }

    /**
     * @param mixed $addons
     *
     * @return array<int, string>
     */
    private function format_addon_lines($addons): array
    {
        if (! is_array($addons)) {
            return [];
        }

        $lines = [];

        foreach ($addons as $key => $addon) {
            if (is_array($addon)) {
                $active = $addon['active'] ?? ($addon['selected'] ?? true);
                if (! $active) {
                    continue;
                }
                $label = sanitize_text_field((string) ($addon['label'] ?? $key));
            } else {
                $label = sanitize_text_field((string) $addon);
            }

            if ('' === $label) {
                continue;
            }

            $lines[] = $label;
        }

        return $lines;
    }

    private function format_money(float $amount, string $currency): string
    {
        if (function_exists('wc_price')) {
            return wc_price($amount, ['currency' => $currency]);
        }

        return sprintf('%s %s', $currency, number_format_i18n($amount, 2));
    }
}
