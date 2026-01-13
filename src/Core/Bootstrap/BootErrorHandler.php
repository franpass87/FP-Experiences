<?php

declare(strict_types=1);

namespace FP_Exp\Core\Bootstrap;

use FP_Exp\Utils\Helpers;
use Throwable;

use function add_action;
use function esc_html;
use function esc_html__;
use function is_multisite;
use function sanitize_text_field;
use function sprintf;
use function strlen;
use function substr;
use function trim;

/**
 * Handles plugin boot errors and displays admin notices.
 */
final class BootErrorHandler
{
    /**
     * @var array<int, array{component: string, action: string, message: string}>
     */
    private array $boot_errors = [];

    private bool $boot_notice_hooked = false;

    /**
     * Guard a callback execution and catch errors.
     *
     * @param callable $callback Callback to execute
     * @param string $component Component name
     * @param string $action Action name
     */
    public function guard(callable $callback, string $component, string $action): void
    {
        try {
            $callback();

            return;
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage());
            if ('' === $message) {
                $message = get_class($exception);
            }

            $message = sanitize_text_field($message);

            if (strlen($message) > 160) {
                $message = substr($message, 0, 157) . '…';
            }

            $this->boot_errors[] = [
                'component' => $component,
                'action' => $action,
                'message' => $message,
            ];

            Helpers::log_debug('plugin_boot', 'Component bootstrap failed', [
                'component' => $component,
                'action' => $action,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            if (! $this->boot_notice_hooked) {
                add_action('admin_notices', [$this, 'render_boot_errors']);

                if (is_multisite()) {
                    add_action('network_admin_notices', [$this, 'render_boot_errors']);
                }

                $this->boot_notice_hooked = true;
            }
        }
    }

    /**
     * Render boot errors as admin notices.
     */
    public function render_boot_errors(): void
    {
        if (empty($this->boot_errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html__(
            'FP Experiences could not finish loading some modules. Check the logs for more details.',
            'fp-experiences'
        ) . '</p>';
        echo '<ul>';

        foreach ($this->boot_errors as $error) {
            $summary = sprintf(
                /* translators: 1: module name, 2: method, 3: error message */
                __('%1$s::%2$s — %3$s', 'fp-experiences'),
                $error['component'],
                $error['action'],
                $error['message']
            );

            echo '<li>' . esc_html($summary) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Check if there are any boot errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->boot_errors);
    }

    /**
     * Get boot errors.
     *
     * @return array<int, array{component: string, action: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->boot_errors;
    }
}








