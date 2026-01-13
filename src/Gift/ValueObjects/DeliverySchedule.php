<?php

declare(strict_types=1);

namespace FP_Exp\Gift\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

use function current_time;
use function preg_match;
use function sanitize_text_field;
use function sprintf;
use function wp_timezone;

/**
 * Value Object representing a voucher delivery schedule.
 *
 * Handles scheduled delivery dates and times with timezone support.
 */
final class DeliverySchedule
{
    private string $send_on;
    private int $send_at;
    private string $timezone;

    /**
     * @throws InvalidArgumentException If schedule data is invalid.
     */
    public function __construct(string $send_on, string $time = '09:00', string $timezone = 'Europe/Rome')
    {
        $this->send_on = sanitize_text_field($send_on);
        $this->timezone = $timezone;

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->send_on)) {
            throw new InvalidArgumentException(
                sprintf('Invalid date format: %s. Expected Y-m-d.', $send_on)
            );
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException(
                sprintf('Invalid time format: %s. Expected H:i.', $time)
            );
        }

        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception $exception) {
            $wp_timezone = wp_timezone();
            $tz = $wp_timezone instanceof DateTimeZone ? $wp_timezone : new DateTimeZone('UTC');
            $this->timezone = $tz->getName();
        }

        try {
            $scheduled = new DateTimeImmutable(
                sprintf('%s %s', $this->send_on, $time),
                $tz
            );
            $this->send_at = $scheduled->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                sprintf('Invalid date/time: %s %s', $send_on, $time)
            );
        }
    }

    /**
     * Create immediate delivery (no schedule).
     */
    public static function immediate(): self
    {
        $now = current_time('timestamp', true);
        $date = gmdate('Y-m-d', $now);
        $time = gmdate('H:i', $now);

        return new self($date, $time, 'UTC');
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $send_on = sanitize_text_field((string) ($data['send_on'] ?? ($data['date'] ?? '')));
        $time = sanitize_text_field((string) ($data['time'] ?? '09:00'));
        $timezone = sanitize_text_field((string) ($data['timezone'] ?? 'Europe/Rome'));

        return new self($send_on, $time, $timezone);
    }

    /**
     * Get the scheduled timestamp (UTC).
     */
    public function getTimestamp(): int
    {
        return $this->send_at;
    }

    /**
     * Get the date (Y-m-d format).
     */
    public function getDate(): string
    {
        return $this->send_on;
    }

    /**
     * Get the timezone.
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * Check if delivery is scheduled (future).
     */
    public function isScheduled(): bool
    {
        $now = current_time('timestamp', true);

        return $this->send_at > $now;
    }

    /**
     * Check if scheduled time has passed.
     */
    public function isPast(): bool
    {
        $now = current_time('timestamp', true);

        return $this->send_at <= $now;
    }

    /**
     * Check if delivery should happen now (within 1 minute).
     */
    public function shouldDeliverNow(): bool
    {
        $now = current_time('timestamp', true);

        return $this->send_at <= ($now + 60);
    }

    /**
     * Convert to array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'send_on' => $this->send_on,
            'send_at' => $this->send_at,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * Check if two schedules are equal.
     */
    public function equals(DeliverySchedule $other): bool
    {
        return $this->send_at === $other->send_at;
    }
}















