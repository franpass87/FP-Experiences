<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

if (! defined('ABSPATH')) {
    exit;
}

use function apply_filters;
use function get_option;
use function is_array;

final class Consent
{
    public const CHANNEL_GA4 = 'ga4';
    public const CHANNEL_GOOGLE_ADS = 'google_ads';
    public const CHANNEL_META = 'meta_pixel';
    public const CHANNEL_CLARITY = 'clarity';

    public static function granted(string $channel): bool
    {
        $settings = get_option('fp_exp_tracking', []);
        $defaults = [];

        if (is_array($settings) && isset($settings['consent_defaults']) && is_array($settings['consent_defaults'])) {
            $defaults = $settings['consent_defaults'];
        }

        if (is_array($settings) && isset($settings[$channel]) && is_array($settings[$channel])) {
            if (isset($settings[$channel]['enabled']) && empty($settings[$channel]['enabled'])) {
                return false;
            }
        }

        $granted = ! empty($defaults[$channel]);

        /**
         * Allow CMPs or plugins to override consent decisions per channel.
         */
        $granted = (bool) apply_filters('fp_exp_tracking_consent', $granted, $channel, $settings);

        return $granted;
    }
}
