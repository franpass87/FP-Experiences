<?php

declare(strict_types=1);

namespace FP_Exp\Utils\Helpers;

use function apply_filters;
use function filemtime;
use function is_readable;
use function ltrim;

use const FP_EXP_PLUGIN_DIR;
use const FP_EXP_VERSION;

/**
 * Helper for asset versioning and cache management.
 */
final class AssetHelper
{
    /**
     * @var array<string, string>
     */
    private static array $asset_version_cache = [];

    /**
     * Clear the asset version cache.
     */
    public static function clearCache(): void
    {
        self::$asset_version_cache = [];
    }

    /**
     * Get asset version for cache busting.
     *
     * @param string $relative_path Relative path from plugin root
     *
     * @return string Version string
     */
    public static function getVersion(string $relative_path): string
    {
        $relative_path = ltrim($relative_path, '/');

        if (isset(self::$asset_version_cache[$relative_path])) {
            return self::$asset_version_cache[$relative_path];
        }

        // In production, usa sempre la versione del plugin per garantire cache busting
        // ad ogni release, indipendentemente dai timestamp dei file
        if (defined('FP_EXP_VERSION') && FP_EXP_VERSION !== '') {
            $version = FP_EXP_VERSION;
        } else {
            // Fallback: usa timestamp del file se disponibile
            $full_path = FP_EXP_PLUGIN_DIR . $relative_path;
            if (is_readable($full_path)) {
                $version = (string) filemtime($full_path);
            } else {
                $version = '1.0.0';
            }
        }

        self::$asset_version_cache[$relative_path] = $version;

        return $version;
    }

    /**
     * Resolve asset relative path from candidates.
     *
     * @param array<string> $candidates Array of candidate paths
     *
     * @return string First existing asset path or first candidate
     */
    public static function resolveAssetPath(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $full_path = FP_EXP_PLUGIN_DIR . ltrim($candidate, '/');
            if (is_readable($full_path)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? '';
    }

    /**
     * Style handles registered by WordPress that must load before `fp-exp-admin` CSS.
     * Default `colors` pulls in the admin color scheme after `wp-admin` / `buttons`, so FP DMS
     * rules sort later and win over core `.nav-tab` / `.button-primary` without competing FOUC.
     *
     * @return list<string>
     */
    public static function adminStyleDependencies(): array
    {
        $deps = ['colors'];
        $filtered = apply_filters('fp_exp_admin_style_dependencies', $deps);

        if (! is_array($filtered)) {
            return $deps;
        }

        $out = [];
        foreach ($filtered as $handle) {
            if (is_string($handle) && $handle !== '') {
                $out[] = $handle;
            }
        }

        return $out !== [] ? $out : $deps;
    }
}















