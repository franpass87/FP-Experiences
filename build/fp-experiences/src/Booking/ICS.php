<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use DateTimeImmutable;
use DateTimeZone;

use function add_query_arg;
use function esc_url_raw;
use function gmdate;
use function is_string;
use function sanitize_email;
use function sanitize_text_field;
use function wp_tempnam;

final class ICS
{
    /**
     * Generate a basic ICS payload for a reservation event.
     *
     * @param array<string, mixed> $event Event payload.
     */
    public static function generate(array $event): string
    {
        $summary = sanitize_text_field((string) ($event['summary'] ?? 'Experience booking'));
        $description = sanitize_text_field((string) ($event['description'] ?? ''));
        $location = sanitize_text_field((string) ($event['location'] ?? ''));
        $organizer_name = sanitize_text_field((string) ($event['organizer_name'] ?? ''));
        $organizer_email = sanitize_email((string) ($event['organizer_email'] ?? ''));

        $uid = (string) ($event['uid'] ?? md5($summary . ($event['start'] ?? '') . ($event['end'] ?? '')) . '@fp-experiences');

        $dtstamp = gmdate('Ymd\THis\Z');
        $dtstart = self::format_utc($event['start'] ?? '');
        $dtend = self::format_utc($event['end'] ?? '');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FP Experiences//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
        ];

        if ($dtstart) {
            $lines[] = 'DTSTART:' . $dtstart;
        }

        if ($dtend) {
            $lines[] = 'DTEND:' . $dtend;
        }

        if ($summary) {
            $lines[] = 'SUMMARY:' . self::escape_text($summary);
        }

        if ($description) {
            $lines[] = 'DESCRIPTION:' . self::escape_text($description);
        }

        if ($location) {
            $lines[] = 'LOCATION:' . self::escape_text($location);
        }

        if ($organizer_email) {
            $organizer = 'MAILTO:' . $organizer_email;
            if ($organizer_name) {
                $organizer = 'CN=' . self::escape_text($organizer_name) . ':' . $organizer;
            }

            $lines[] = 'ORGANIZER;' . $organizer;
        }

        if (! empty($event['url']) && is_string($event['url'])) {
            $lines[] = 'URL:' . self::escape_text($event['url']);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Create a temporary ICS file on disk for attachment.
     */
    public static function create_file(string $content, string $filename): ?string
    {
        $temp_file = wp_tempnam($filename);

        if (! $temp_file) {
            return null;
        }

        file_put_contents($temp_file, $content);

        return $temp_file;
    }

    /**
     * Build a Google Calendar event link for the event.
     *
     * @param array<string, mixed> $event
     */
    public static function google_calendar_link(array $event): string
    {
        $dtstart = self::format_utc($event['start'] ?? '');
        $dtend = self::format_utc($event['end'] ?? '');

        $params = [
            'action' => 'TEMPLATE',
            'text' => $event['summary'] ?? '',
            'details' => $event['description'] ?? '',
            'location' => $event['location'] ?? '',
        ];

        if ($dtstart) {
            $params['dates'] = $dtstart . '/' . ($dtend ?: $dtstart);
        }

        $url = add_query_arg($params, 'https://calendar.google.com/calendar/render');

        return esc_url_raw($url);
    }

    /**
     * Format a date string or timestamp into the ICS UTC format.
     *
     * @param mixed $value
     */
    private static function format_utc($value): string
    {
        if (is_string($value) && '' !== $value) {
            try {
                $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));

                return $date->format('Ymd\THis\Z');
            } catch (\Throwable $throwable) {
                // Continue to fallback below.
            }
        }

        if (is_numeric($value)) {
            $date = (new DateTimeImmutable('@' . (string) $value))->setTimezone(new DateTimeZone('UTC'));

            return $date->format('Ymd\THis\Z');
        }

        return '';
    }

    private static function escape_text(string $text): string
    {
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);

        return $text;
    }
}
