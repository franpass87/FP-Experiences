<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Utils\TemplateLoader;
use FP_Exp\Utils\Theme;
use WP_Error;

use function add_shortcode;
use function esc_html;
use function shortcode_atts;
use function header;
use function headers_sent;

abstract class BaseShortcode
{
    private static bool $sent_no_store_header = false;

    protected string $tag = '';

    /**
     * @var array<string, mixed>
     */
    protected array $defaults = [];

    protected string $template = '';

    public function register(): void
    {
        if ('' === $this->tag) {
            return;
        }

        add_shortcode($this->tag, [$this, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render($atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        $atts = is_array($atts) ? $atts : [];
        $attributes = shortcode_atts($this->defaults, $atts, $shortcode_tag ?: $this->tag);

        if ($this->should_disable_cache()) {
            $this->send_no_store_header();
        }

        $context = $this->get_context($attributes, $content);

        if ($context instanceof WP_Error) {
            return '<div class="fp-exp-notice fp-exp-notice-error">' . esc_html($context->get_error_message()) . '</div>';
        }

        $scope_class = $context['scope_class'] ?? Theme::generate_scope();
        $context['scope_class'] = $scope_class;
        $context['attributes'] = $attributes;

        $theme = $context['theme'] ?? [];
        $asset_handle = $this->get_asset_handle();

        if ('checkout' === $asset_handle) {
            Assets::instance()->enqueue_checkout($theme, $scope_class);
        } else {
            Assets::instance()->enqueue_front($theme, $scope_class);
        }

        return TemplateLoader::render($this->template, $context);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|WP_Error
     */
    abstract protected function get_context(array $attributes, ?string $content = null);

    protected function get_asset_handle(): string
    {
        return 'front';
    }

    protected function should_disable_cache(): bool
    {
        return false;
    }

    private function send_no_store_header(): void
    {
        if (self::$sent_no_store_header) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        self::$sent_no_store_header = true;
    }
}
