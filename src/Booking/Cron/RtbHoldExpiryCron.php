<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Cron;

use FP_Exp\Booking\Reservations;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Logger;

use function abs;
use function add_action;
use function current_time;
use function gmdate;
use function wp_get_scheduled_event;
use function wp_schedule_event;
use function wp_unschedule_event;

use const HOUR_IN_SECONDS;

/**
 * Expires RTB hold reservations whose hold_expires_at has passed,
 * freeing up slot capacity.
 */
final class RtbHoldExpiryCron implements HookableInterface
{
    public const CRON_HOOK = 'fp_exp_expire_rtb_holds';

    public function register_hooks(): void
    {
        add_action('init', [$this, 'maybe_schedule']);
        add_action(self::CRON_HOOK, [$this, 'process']);
    }

    public function maybe_schedule(): void
    {
        $scheduled = wp_get_scheduled_event(self::CRON_HOOK);

        if (! $scheduled) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
            return;
        }

        if (abs($scheduled->timestamp - time()) > 2 * HOUR_IN_SECONDS) {
            wp_unschedule_event($scheduled->timestamp, self::CRON_HOOK);
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    public function process(): void
    {
        global $wpdb;

        $table = Reservations::table_name();
        $now = gmdate('Y-m-d H:i:s', current_time('timestamp', true));

        $expired_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE status = %s AND hold_expires_at IS NOT NULL AND hold_expires_at < %s",
            Reservations::STATUS_PENDING_REQUEST,
            $now
        ));

        if (empty($expired_ids)) {
            return;
        }

        $count = 0;
        foreach ($expired_ids as $id) {
            $reservation_id = (int) $id;
            if (Reservations::update_status($reservation_id, Reservations::STATUS_CANCELLED)) {
                $count++;
                do_action('fp_exp_rtb_hold_expired', $reservation_id);
            }
        }

        if ($count > 0) {
            Logger::log(sprintf('RTB hold expiry: %d reservation(s) cancelled', $count));
        }
    }
}
