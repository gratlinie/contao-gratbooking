<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Booking;

/**
 * Kalenderdatei zur Buchung (RFC 5545). Gleiche UID bei Umbuchung und Absage, damit Kalenderprogramme den
 * vorhandenen Eintrag ändern statt einen neuen anzulegen; SEQUENCE zählt die Änderungen.
 */
class Ics
{
    public static function create(array $booking, array $type, array $calendar, string $host, bool $cancelled = false): string
    {
        $description = trim(($type['description'] ?? '')."\n\n".($booking['zoomJoinUrl'] ? 'Zoom: '.$booking['zoomJoinUrl'].($booking['zoomPassword'] ? "\nKenncode: ".$booking['zoomPassword'] : '') : ''));
        $organizer = $calendar['organizerEmail'] ? 'ORGANIZER;CN='.self::escape($calendar['organizerName'] ?: $calendar['title']).':mailto:'.$calendar['organizerEmail'] : null;

        $lines = array_filter([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Gratlinie//GratBooking//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($cancelled ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT',
            'UID:'.$booking['token'].'@'.$host,
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'SEQUENCE:'.(int) $booking['sequence'],
            'DTSTART:'.gmdate('Ymd\THis\Z', (int) $booking['start']),
            'DTEND:'.gmdate('Ymd\THis\Z', (int) $booking['end']),
            'SUMMARY:'.self::escape($type['title'].' – '.($calendar['organizerName'] ?: $calendar['title'])),
            '' !== $description ? 'DESCRIPTION:'.self::escape($description) : null,
            ($booking['zoomJoinUrl'] ?: $type['location']) ? 'LOCATION:'.self::escape($booking['zoomJoinUrl'] ?: $type['location']) : null,
            $booking['zoomJoinUrl'] ? 'URL:'.$booking['zoomJoinUrl'] : null,
            $organizer,
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $text);
    }

    /**
     * Zeilen nach 75 Bytes umbrechen, ohne UTF-8-Zeichen zu teilen.
     */
    private static function fold(string $line): string
    {
        $out = '';
        $length = 0;

        foreach (mb_str_split($line) as $char) {
            if ($length + \strlen($char) > 75) {
                $out .= "\r\n ";
                $length = 1;
            }

            $out .= $char;
            $length += \strlen($char);
        }

        return $out;
    }
}
