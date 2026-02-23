<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Services;

use FP_Exp\Utils\Logger;

use function absint;
use function get_option;
use function is_array;
use function max;
use function sprintf;
use function time;
use function wp_clear_scheduled_hook;
use function wp_schedule_single_event;

use const HOUR_IN_SECONDS;
use const MINUTE_IN_SECONDS;

/**
 * Service for scheduling email notifications (reminder, followup).
 */
final class EmailSchedulerService
{
    private const REMINDER_HOOK = 'fp_exp_email_send_reminder';
    private const FOLLOWUP_HOOK = 'fp_exp_email_send_followup';

    /**
     * Schedule internal notifications (reminder and followup).
     *
     * @param array<string, mixed> $context
     */
    public function scheduleNotifications(int $reservation_id, int $order_id, array $context): void
    {
        if ($reservation_id <= 0 || $order_id <= 0) {
            return;
        }

        $emails_settings = get_option('fp_exp_emails', []);
        $emails_settings = is_array($emails_settings) ? $emails_settings : [];
        $schedule = isset($emails_settings['schedule']) && is_array($emails_settings['schedule']) ? $emails_settings['schedule'] : [];

        $reminder_offset_hours = isset($schedule['reminder_offset_hours']) ? max(0, (int) $schedule['reminder_offset_hours']) : 24;
        $followup_offset_hours = isset($schedule['followup_offset_hours']) ? max(0, (int) $schedule['followup_offset_hours']) : 24;

        $start_timestamp = isset($context['slot']['start_timestamp']) ? (int) $context['slot']['start_timestamp'] : 0;
        $end_timestamp = isset($context['slot']['end_timestamp']) ? (int) $context['slot']['end_timestamp'] : $start_timestamp;

        if ($start_timestamp <= 0) {
            Logger::log(sprintf(
                'EmailScheduler: cannot schedule notifications, invalid start_timestamp for reservation %d, order %d',
                $reservation_id,
                $order_id
            ));
            return;
        }

        $now = time();

        // Schedule reminder
        $reminder_at = $start_timestamp > 0 ? max(0, $start_timestamp - ($reminder_offset_hours * HOUR_IN_SECONDS)) : 0;
        if ($reminder_at > 0) {
            if ($reminder_at <= $now) {
                $reminder_at = $now + (5 * MINUTE_IN_SECONDS);
            }

            wp_clear_scheduled_hook(self::REMINDER_HOOK, [$reservation_id, $order_id]);
            wp_schedule_single_event($reminder_at, self::REMINDER_HOOK, [$reservation_id, $order_id]);
        }

        // Schedule followup
        $followup_at = $end_timestamp > 0 ? max(0, $end_timestamp + ($followup_offset_hours * HOUR_IN_SECONDS)) : 0;
        if ($followup_at > 0) {
            if ($followup_at <= $now) {
                $followup_at = $now + (10 * MINUTE_IN_SECONDS);
            }

            wp_clear_scheduled_hook(self::FOLLOWUP_HOOK, [$reservation_id, $order_id]);
            wp_schedule_single_event($followup_at, self::FOLLOWUP_HOOK, [$reservation_id, $order_id]);
        }
    }

    /**
     * Cancel scheduled notifications.
     */
    public function cancelNotifications(int $reservation_id, int $order_id): void
    {
        wp_clear_scheduled_hook(self::REMINDER_HOOK, [$reservation_id, $order_id]);
        wp_clear_scheduled_hook(self::FOLLOWUP_HOOK, [$reservation_id, $order_id]);
    }
}















