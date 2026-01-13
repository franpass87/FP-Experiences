<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

use function strtotime;
use function wp_timezone;

/**
 * Value Object representing a time range (start and end).
 */
final class TimeRange
{
    private DateTimeImmutable $start;
    private DateTimeImmutable $end;

    /**
     * @throws InvalidArgumentException If dates are invalid or end is before start
     */
    public function __construct(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        if ($end < $start) {
            throw new InvalidArgumentException('End time must be after start time.');
        }

        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Create from ISO strings.
     *
     * @throws InvalidArgumentException If strings are invalid
     */
    public static function fromIsoStrings(string $start_iso, string $end_iso): self
    {
        $timezone = wp_timezone();
        $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $start_iso, $timezone);

        if ($start === false) {
            $start_timestamp = strtotime($start_iso);
            if ($start_timestamp === false) {
                throw new InvalidArgumentException('Invalid start date format.');
            }
            $start = (new DateTimeImmutable())->setTimestamp($start_timestamp)->setTimezone($timezone);
        }

        $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $end_iso, $timezone);

        if ($end === false) {
            $end_timestamp = strtotime($end_iso);
            if ($end_timestamp === false) {
                throw new InvalidArgumentException('Invalid end date format.');
            }
            $end = (new DateTimeImmutable())->setTimestamp($end_timestamp)->setTimezone($timezone);
        }

        return new self($start, $end);
    }

    /**
     * Create from UTC datetime strings.
     *
     * @throws InvalidArgumentException If strings are invalid
     */
    public static function fromUtcStrings(string $start_utc, string $end_utc): self
    {
        $timezone = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_utc, $timezone);

        if ($start === false) {
            throw new InvalidArgumentException('Invalid start UTC format.');
        }

        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end_utc, $timezone);

        if ($end === false) {
            throw new InvalidArgumentException('Invalid end UTC format.');
        }

        return new self($start, $end);
    }

    /**
     * Get start datetime.
     */
    public function getStart(): DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Get end datetime.
     */
    public function getEnd(): DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Get start as UTC string.
     */
    public function getStartUtc(): string
    {
        return $this->start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * Get end as UTC string.
     */
    public function getEndUtc(): string
    {
        return $this->end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * Get start as ISO string.
     */
    public function getStartIso(): string
    {
        return $this->start->format('c');
    }

    /**
     * Get end as ISO string.
     */
    public function getEndIso(): string
    {
        return $this->end->format('c');
    }

    /**
     * Get duration in seconds.
     */
    public function getDuration(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }

    /**
     * Check if time range overlaps with another.
     */
    public function overlaps(TimeRange $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    /**
     * Check if time range contains a datetime.
     */
    public function contains(DateTimeImmutable $datetime): bool
    {
        return $datetime >= $this->start && $datetime <= $this->end;
    }
}















