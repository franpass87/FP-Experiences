<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Utils\Consent;
use FP_Exp\Utils\Helpers;

use function add_action;
use function esc_html;
use function is_array;

final class Clarity
{
    public function register_hooks(): void
    {
        add_action('wp_head', [$this, 'output_snippet'], 12);
    }

    public function output_snippet(): void
    {
        if (! Consent::granted(Consent::CHANNEL_CLARITY)) {
            return;
        }

        $settings = Helpers::tracking_settings();
        $config = isset($settings['clarity']) && is_array($settings['clarity']) ? $settings['clarity'] : [];
        $project_id = (string) ($config['project_id'] ?? '');

        if (! $project_id) {
            return;
        }

        echo "<!-- FP Experiences Microsoft Clarity -->\n";
        echo "<script type='text/javascript'>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};" .
            "t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];" .
            "y.parentNode.insertBefore(t,y);})(window, document, 'clarity', 'script', '" . esc_html($project_id) . "');</script>";
    }
}
