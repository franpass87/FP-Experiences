<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

if (! defined('ABSPATH')) {
    exit;
}

use function extract;
use function is_readable;
use function ob_get_clean;
use function ob_start;
use function trailingslashit;

final class TemplateLoader
{
    /**
     * @param array<string, mixed> $context
     */
    public static function render(string $template, array $context = []): string
    {
        $path = trailingslashit(FP_EXP_PLUGIN_DIR) . 'templates/' . ltrim($template, '/');

        if (! is_readable($path)) {
            return '';
        }

        ob_start();

        /** @psalm-suppress UnresolvableInclude */
        if (! empty($context)) {
            extract($context, EXTR_SKIP);
        }

        include $path;

        return (string) ob_get_clean();
    }
}
