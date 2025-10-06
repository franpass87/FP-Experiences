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
$options = [
    'fp_exp_roles_version',
    'fp_exp_migrations',
    'fp_exp_experience_layout',
    'fp_exp_tracking',
    'fp_exp_listing',
    'fp_exp_gift',
    'fp_exp_rtb',
    'fp_exp_enable_meeting_points',
    'fp_exp_enable_meeting_point_import',
    'fp_exp_debug_logging',
    'fp_exp_branding',
    'fp_exp_logs',
    'fp_exp_google_calendar',
    'fp_exp_brevo',
    'fp_exp_emails',
    'fp_exp_email_branding',
    'fp_exp_structure_email',
    'fp_exp_webmaster_email',
];

foreach ($options as $option) {
    delete_option($option);
}
