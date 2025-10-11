<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop custom tables (if present).
$tables = [
    $wpdb->prefix . 'fp_exp_slots',
    $wpdb->prefix . 'fp_exp_reservations',
    $wpdb->prefix . 'fp_exp_resources',
    $wpdb->prefix . 'fp_exp_gift_vouchers',
];

foreach ($tables as $table_name) {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
}

// Remove plugin options.
// NOTA: Le opzioni di configurazione e branding vengono mantenute per preservare
// le impostazioni in caso di reinstallazione del plugin.
$options = [
    'fp_exp_roles_version',
    'fp_exp_migrations',
    'fp_exp_logs',
];

// Opzioni conservate (non vengono eliminate):
// - fp_exp_branding (colori, font, temi)
// - fp_exp_email_branding (branding email)
// - fp_exp_tracking (configurazione tracking: GA4, Meta Pixel, Google Ads, Clarity)
// - fp_exp_emails (configurazione email)
// - fp_exp_brevo (integrazione Brevo)
// - fp_exp_google_calendar (integrazione Google Calendar)
// - fp_exp_experience_layout (layout esperienze)
// - fp_exp_listing (impostazioni listing)
// - fp_exp_gift (impostazioni gift/voucher)
// - fp_exp_rtb (request to book)
// - fp_exp_enable_meeting_points (punti di incontro)
// - fp_exp_enable_meeting_point_import (import punti di incontro)
// - fp_exp_structure_email (email struttura)
// - fp_exp_webmaster_email (email webmaster)
// - fp_exp_debug_logging (debug logging)

foreach ($options as $option) {
    delete_option($option);
}
