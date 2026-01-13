<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use WP_Post;

use function add_action;
use function did_action;

final class Registrar implements HookableInterface
{
    /**
     * @var array<int, BaseShortcode>
     */
    private array $shortcodes = [];

    public function __construct()
    {
        $this->shortcodes = [
            new SimpleArchiveShortcode(),
            new ListShortcode(),
            new WidgetShortcode(),
            new CalendarShortcode(),
            new CheckoutShortcode(),
            new ExperienceShortcode(),
            new GiftRedeemShortcode(),
        ];

        if (Helpers::meeting_points_enabled()) {
            $this->shortcodes[] = new MeetingPointsShortcode();
        }
    }

    public function register_hooks(): void
    {
        $this->register();
    }

    public function register(): void
    {
        if (did_action('init')) {
            $this->register_shortcodes();
        } else {
            add_action('init', [$this, 'register_shortcodes']);
        }
        add_action('save_post_fp_experience', [$this, 'flush_experience_cache'], 20, 3);
    }

    public function register_shortcodes(): void
    {
        foreach ($this->shortcodes as $shortcode) {
            $shortcode->register();
        }
        
        // Register diagnostic shortcode (admin only)
        if (is_admin() || current_user_can('manage_options')) {
            \FP_Exp\Admin\DiagnosticShortcode::register();
        }
    }

    public function flush_experience_cache(int $post_id, WP_Post $post, bool $update): void
    {
        if ($post_id <= 0) {
            return;
        }

        if ('fp_experience' !== $post->post_type) {
            return;
        }

        if ('auto-draft' === $post->post_status) {
            return;
        }

        Helpers::clear_experience_transients($post_id);
    }
}
