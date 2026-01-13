<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

/**
 * Integration con FP Performance Suite
 * Esclude automaticamente REST API di FP-Experiences dalla cache
 */
final class PerformanceIntegration implements HookableInterface
{
    public function register_hooks(): void
    {
        $this->register();
    }

    public function register(): void
    {
        // Escludi REST API dalla cache di FP Performance
        add_filter('fp_ps_cache_exclude_uris', [$this, 'exclude_rest_api_from_cache'], 10, 1);
        
        // Escludi anche da altri plugin cache comuni
        add_filter('rocket_cache_reject_uri', [$this, 'exclude_from_wp_rocket'], 10, 1);
        add_filter('w3tc_pagecache_reject_uri', [$this, 'exclude_from_w3tc'], 10, 1);
        add_filter('litespeed_cache_is_cacheable', [$this, 'exclude_from_litespeed'], 10, 1);
    }
    
    /**
     * Escludi REST API di FP-Experiences dalla cache di FP Performance
     * 
     * @param array<string> $exclude_uris
     * @return array<string>
     */
    public function exclude_rest_api_from_cache(array $exclude_uris): array
    {
        $exclude_uris[] = '/wp-json/fp-exp/v1/';
        $exclude_uris[] = 'wp-json/fp-exp/';
        
        // CRITICAL: Escludi admin-ajax.php dalla cache (causa 503 errors)
        $exclude_uris[] = '/wp-admin/admin-ajax.php';
        $exclude_uris[] = 'wp-admin/admin-ajax.php';
        $exclude_uris[] = 'admin-ajax.php';
        
        return $exclude_uris;
    }
    
    /**
     * Escludi da WP Rocket
     * 
     * @param array<string> $exclude_uris
     * @return array<string>
     */
    public function exclude_from_wp_rocket(array $exclude_uris): array
    {
        $exclude_uris[] = '/wp-json/fp-exp/(.*)';
        
        return $exclude_uris;
    }
    
    /**
     * Escludi da W3 Total Cache
     * 
     * @param array<string> $exclude_uris
     * @return array<string>
     */
    public function exclude_from_w3tc(array $exclude_uris): array
    {
        $exclude_uris[] = 'wp-json/fp-exp/';
        
        return $exclude_uris;
    }
    
    /**
     * Escludi da LiteSpeed Cache
     * 
     * @param bool $is_cacheable
     * @return bool
     */
    public function exclude_from_litespeed(bool $is_cacheable): bool
    {
        if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], '/wp-json/fp-exp/') !== false) {
            return false; // Non cachare
        }
        
        return $is_cacheable;
    }
}

