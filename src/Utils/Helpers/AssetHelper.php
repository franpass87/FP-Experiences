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
}















