<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use function preg_replace;
use function sanitize_text_field;
use function strtolower;
use function wp_unslash;

final class GiftRedeemShortcode extends BaseShortcode
{
    protected string $tag = 'fp_exp_gift_redeem';

    protected string $template = 'front/gift-redeem.php';

    protected array $defaults = [
        'code' => '',
    ];

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    protected function get_context(array $attributes, ?string $content = null)
    {
        $code = sanitize_text_field((string) ($attributes['code'] ?? ''));

        if (! $code && isset($_GET['gift'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $code = sanitize_text_field((string) wp_unslash($_GET['gift'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        $code = strtolower($code);
        if ($code) {
            $code = preg_replace('/[^a-z0-9\-]/', '', $code) ?? $code;
        }

        return [
            'theme' => [],
            'initial_code' => $code,
        ];
    }
}
