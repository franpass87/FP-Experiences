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
     * Usa `FP_EXP_VERSION` più il `filemtime` del file quando il file esiste, così ogni rebuild
     * di bundle in `assets/css/dist` e `assets/js/dist` invalida la cache del browser anche se la versione del plugin
     * non è stata ancora bumpata (evita 304 «silenziosi» su CSS/JS admin).
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

        $full_path = FP_EXP_PLUGIN_DIR . $relative_path;

        $base = (defined('FP_EXP_VERSION') && FP_EXP_VERSION !== '')
            ? (string) FP_EXP_VERSION
            : '1.0.0';

        $version = $base;
        if (is_readable($full_path)) {
            $mtime = filemtime($full_path);
            if (false !== $mtime) {
                $version = $base . '-' . $mtime;
            }
        }

        /** @var string $filtered */
        $filtered = apply_filters('fp_exp_asset_version', $version, $relative_path);
        $version = is_string($filtered) && $filtered !== '' ? $filtered : $version;

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















