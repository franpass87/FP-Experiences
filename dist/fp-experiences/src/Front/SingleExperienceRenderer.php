<?php

declare(strict_types=1);

namespace FP_Exp\Front;

use function add_filter;
use function do_shortcode;
use function get_post_type;
use function get_the_ID;
use function has_shortcode;
use function in_the_loop;
use function is_admin;
use function is_main_query;
use function is_singular;
use function shortcode_exists;
use function sprintf;
use function trim;

final class SingleExperienceRenderer
{
    public function register_hooks(): void
    {
        add_filter('the_content', [$this, 'maybe_render_single'], 20);
    }

    /**
     * @param string $content
     */
    public function maybe_render_single($content)
    {
        if (is_admin()) {
            return $content;
        }

        if (! is_singular('fp_experience')) {
            return $content;
        }

        if (! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        $post_type = get_post_type();
        if ('fp_experience' !== $post_type) {
            return $content;
        }

        if (is_string($content) && has_shortcode($content, 'fp_exp_page')) {
            return $content;
        }

        if (! shortcode_exists('fp_exp_page')) {
            return $content;
        }

        $experience_id = (int) get_the_ID();
        if ($experience_id <= 0) {
            return $content;
        }

        $shortcode = sprintf('[fp_exp_page id="%d"]', $experience_id);
        $rendered = do_shortcode($shortcode);

        if ('' === trim((string) $rendered)) {
            return $content;
        }

        return $rendered;
    }
}
