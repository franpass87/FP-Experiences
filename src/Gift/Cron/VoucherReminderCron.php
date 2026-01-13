<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Cron;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Gift\Email\VoucherEmailSender;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use FP_Exp\Gift\VoucherCPT;
use FP_Exp\Utils\Helpers;

use function abs;
use function absint;
use function array_map;
use function array_unique;
use function array_values;
use function current_time;
use function get_posts;
use function HOUR_IN_SECONDS;
use function in_array;
use function update_post_meta;
use function wp_get_scheduled_event;
use function wp_schedule_event;
use function wp_unschedule_event;

use const DAY_IN_SECONDS;

/**
 * Cron job for processing voucher reminders.
 *
 * Sends reminder emails before voucher expiration.
 */
final class VoucherReminderCron implements HookableInterface
{
    public const CRON_HOOK = 'fp_exp_gift_send_reminders';

    private VoucherRepository $repository;
    private VoucherEmailSender $email_sender;

    public function __construct(
        ?VoucherRepository $repository = null,
        ?VoucherEmailSender $email_sender = null
    ) {
        $this->repository = $repository ?? new VoucherRepository();
        $this->email_sender = $email_sender ?? new VoucherEmailSender();
    }

    public function register_hooks(): void
    {
        $this->register();
    }

    /**
     * Register cron hooks.
     */
    public function register(): void
    {
        add_action('init', [$this, 'maybeSchedule']);
        add_action(self::CRON_HOOK, [$this, 'process']);
    }

    /**
     * Schedule cron if needed.
     */
    public function maybeSchedule(): void
    {
        if (! Helpers::gift_enabled()) {
            $this->clear();

            return;
        }

        $scheduled = wp_get_scheduled_event(self::CRON_HOOK);
        $target = $this->resolveNextCronTimestamp();

        if (! $scheduled) {
            wp_schedule_event($target, 'daily', self::CRON_HOOK);

            return;
        }

        if (abs($scheduled->timestamp - $target) > HOUR_IN_SECONDS) {
            wp_unschedule_event($scheduled->timestamp, self::CRON_HOOK);
            wp_schedule_event($target, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Clear scheduled cron.
     */
    public function clear(): void
    {
        $scheduled = wp_get_scheduled_event(self::CRON_HOOK);

        if ($scheduled) {
            wp_unschedule_event($scheduled->timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Process reminders (called by cron).
     */
    public function process(): void
    {
        if (! Helpers::gift_enabled()) {
            return;
        }

        $now = current_time('timestamp', true);
        $offsets = Helpers::gift_reminder_offsets();
        $batch_size = 50;
        $page = 1;

        do {
            $voucher_ids = get_posts([
                'post_type' => VoucherCPT::POST_TYPE,
                'post_status' => 'any',
                'posts_per_page' => $batch_size,
                'paged' => $page,
                'fields' => 'ids',
                'meta_key' => '_fp_exp_gift_status',
                'meta_value' => 'active',
                'no_found_rows' => true,
            ]);

            if (! $voucher_ids) {
                break;
            }

            $voucher_ids = array_map('absint', $voucher_ids);

            foreach ($voucher_ids as $voucher_id) {
                if ($voucher_id <= 0) {
                    continue;
                }

                $this->processVoucher($voucher_id, $now, $offsets);
            }

            $page++;
        } while (count($voucher_ids) === $batch_size);
    }

    /**
     * Process a single voucher for reminders.
     */
    private function processVoucher(int $voucher_id, int $now, array $offsets): void
    {
        $valid_until = $this->repository->getValidUntil($voucher_id);

        // Check if expired
        if ($valid_until > 0 && $valid_until <= $now) {
            $this->repository->updateStatus($voucher_id, VoucherStatus::expired());
            $this->repository->appendLog($voucher_id, 'expired');
            $this->email_sender->sendExpiredEmail($voucher_id);

            return;
        }

        if ($valid_until <= 0) {
            return;
        }

        // Process reminders
        $sent = $this->repository->getRemindersSent($voucher_id);

        foreach ($offsets as $offset) {
            if (in_array($offset, $sent, true)) {
                continue;
            }

            $reminder_timestamp = $valid_until - ($offset * DAY_IN_SECONDS);

            if ($reminder_timestamp <= $now && $valid_until > $now) {
                $this->email_sender->sendReminderEmail($voucher_id, $offset, $valid_until);
                $sent[] = $offset;
            }
        }

        // Update sent reminders
        $sent = array_values(array_unique(array_map('absint', $sent)));
        $this->repository->updateRemindersSent($voucher_id, $sent);
    }

    /**
     * Resolve next cron timestamp.
     */
    private function resolveNextCronTimestamp(): int
    {
        $time_string = Helpers::gift_reminder_time();
        [$hour, $minute] = array_map('intval', explode(':', $time_string));
        $timezone = wp_timezone();

        $now = new \DateTimeImmutable('now', $timezone);
        $target = $now->setTime($hour, $minute, 0);

        if ($target <= $now) {
            $target = $target->modify('+1 day');
        }

        return $target->getTimestamp();
    }
}















