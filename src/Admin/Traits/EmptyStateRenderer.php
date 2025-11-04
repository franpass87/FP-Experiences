<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Traits;

use function esc_attr;
use function esc_html;
use function esc_url;

/**
 * Trait EmptyStateRenderer
 * 
 * Provides a consistent empty state component across all admin pages.
 * Used when tables, lists, or data collections have no items to display.
 * 
 * @package FP_Exp\Admin\Traits
 * @since 1.0.3
 */
trait EmptyStateRenderer
{
    /**
     * Render a consistent empty state across all admin pages.
     *
     * @param string $icon Dashicon name (without 'dashicons-' prefix)
     * @param string $title Main heading
     * @param string $description Explanatory text
     * @param string $cta_url Optional call-to-action URL
     * @param string $cta_text Optional call-to-action button text
     * 
     * @return void
     */
    protected static function render_empty_state(
        string $icon,
        string $title,
        string $description,
        string $cta_url = '',
        string $cta_text = ''
    ): void {
        echo '<div class="fp-exp-empty-state">';
        echo '<span class="fp-exp-empty-state__icon dashicons dashicons-' . esc_attr($icon) . '"></span>';
        echo '<h3 class="fp-exp-empty-state__title">' . esc_html($title) . '</h3>';
        echo '<p class="fp-exp-empty-state__description">' . esc_html($description) . '</p>';
        
        if ($cta_url && $cta_text) {
            echo '<a class="button button-primary fp-exp-empty-state__cta" href="' . esc_url($cta_url) . '">';
            echo esc_html($cta_text);
            echo '</a>';
        }
        
        echo '</div>';
    }
}

