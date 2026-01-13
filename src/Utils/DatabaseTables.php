<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\Gift\VoucherTable;

/**
 * Helper class for registering database tables in WordPress.
 * 
 * This replaces the need for Plugin::instance()->register_database_tables()
 * and allows registration without instantiating the legacy Plugin class.
 */
final class DatabaseTables
{
    /**
     * Register all plugin database tables in WordPress $wpdb object.
     * 
     * This method registers the table names so they can be used with $wpdb->prefix
     * and are recognized by WordPress core functions.
     */
    public static function register(): void
    {
        global $wpdb;

        $wpdb->fp_exp_slots = Slots::table_name();
        $wpdb->fp_exp_reservations = Reservations::table_name();
        $wpdb->fp_exp_resources = Resources::table_name();
        $wpdb->fp_exp_gift_vouchers = VoucherTable::table_name();

        if (! is_array($wpdb->tables)) {
            $wpdb->tables = [];
        }

        foreach (['fp_exp_slots', 'fp_exp_reservations', 'fp_exp_resources', 'fp_exp_gift_vouchers'] as $table) {
            if (! in_array($table, $wpdb->tables, true)) {
                $wpdb->tables[] = $table;
            }
        }
    }
}



