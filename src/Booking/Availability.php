<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Booking;

use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\ApiException;
use Gratlinie\GratBookingBundle\Google\GoogleCalendar;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Freie Termine einer Terminart: Wochenzeiten der Terminart im Raster, ab Vorlauf bis Horizont, abzüglich
 * belegter Zeiten aus Google (alle angegebenen Kalender) und eigener Buchungen – jeweils mit Puffer davor und
 * danach. Die Abfrage bei Google wird für die Anzeige 60 Sekunden zwischengespeichert; vor dem Buchen wird
 * frisch geprüft.
 */
class Availability
{
    public const DAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    public function __construct(
        private readonly Connection $db,
        private readonly GoogleCalendar $google,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return list<string> Fehler in den Wochenzeiten, z. B. „Mo: 9-12“
     */
    public static function validateHours(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $range): bool => '' !== $range && null === self::parseRange($range),
        ));
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    public function slots(array $type, array $calendar, int $from, int $to, int|null $excludeBooking = null, bool $fresh = false): array
    {
        $tz = new \DateTimeZone($calendar['timezone'] ?: 'Europe/Berlin');
        $now = time();
        $from = max($from, $now + (int) $type['leadTime'] * 3600);
        $to = min($to, (new \DateTimeImmutable('@'.($now + (int) $type['horizon'] * 86400)))->setTimezone($tz)->setTime(23, 59, 59)->getTimestamp());

        if ($from >= $to) {
            return [];
        }

        $candidates = $this->candidates($type, $tz, $from, $to, $this->absentDays($calendar, $from, $to));

        if ([] === $candidates) {
            return [];
        }

        $before = (int) $type['bufferBefore'] * 60;
        $after = (int) $type['bufferAfter'] * 60;
        $busy = $this->busy($calendar, $candidates[0]['start'] - $before, end($candidates)['end'] + $after, $excludeBooking, $fresh);
        $perDay = (int) $type['maxPerDay'] > 0 ? $this->bookingsPerDay($type, $tz, $from, $to, $excludeBooking) : [];

        return array_values(array_filter($candidates, static function (array $slot) use ($busy, $before, $after, $perDay, $type, $tz): bool {
            if ((int) $type['maxPerDay'] > 0 && ($perDay[(new \DateTimeImmutable('@'.$slot['start']))->setTimezone($tz)->format('Y-m-d')] ?? 0) >= (int) $type['maxPerDay']) {
                return false;
            }

            foreach ($busy as [$busyStart, $busyEnd]) {
                if ($slot['start'] - $before < $busyEnd && $slot['end'] + $after > $busyStart) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function isFree(array $type, array $calendar, int $start, int|null $excludeBooking = null): bool
    {
        foreach ($this->slots($type, $calendar, $start, $start + (int) $type['duration'] * 60, $excludeBooking, true) as $slot) {
            if ($slot['start'] === $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    /**
     * Tage der Abwesenheiten (Urlaub) als „Y-m-d“. Contao speichert Datumsfelder als Mitternacht in der Zeitzone
     * der Installation, deshalb mit date() lesen.
     *
     * @return array<string, true>
     */
    private function absentDays(array $calendar, int $from, int $to): array
    {
        $days = [];
        $rows = $this->db->fetchAllAssociative('SELECT startDate, endDate FROM tl_gb_absence WHERE pid = ? AND endDate >= ? AND startDate <= ?', [$calendar['id'], $from - 86400, $to + 86400]);

        foreach ($rows as $row) {
            $day = new \DateTimeImmutable(date('Y-m-d', (int) $row['startDate']));
            $last = new \DateTimeImmutable(date('Y-m-d', max((int) $row['startDate'], (int) $row['endDate'])));

            for (; $day <= $last; $day = $day->modify('+1 day')) {
                $days[$day->format('Y-m-d')] = true;
            }
        }

        return $days;
    }

    /**
     * @param array<string, true> $absent
     */
    private function candidates(array $type, \DateTimeZone $tz, int $from, int $to, array $absent = []): array
    {
        $duration = (int) $type['duration'] * 60;
        $step = max(5, (int) $type['slotStep']) * 60;
        $slots = [];
        $day = (new \DateTimeImmutable('@'.$from))->setTimezone($tz)->setTime(0, 0);
        $last = (new \DateTimeImmutable('@'.$to))->setTimezone($tz)->setTime(0, 0);

        for (; $day <= $last; $day = $day->modify('+1 day')) {
            if (isset($absent[$day->format('Y-m-d')])) {
                continue;
            }

            foreach (explode(',', (string) $type['hours'.self::DAYS[(int) $day->format('N') - 1]]) as $range) {
                if (null === ($minutes = self::parseRange($range))) {
                    continue;
                }

                // Uhrzeiten über die lokale Zeit setzen, damit Sommer-/Winterzeit stimmt
                for ($m = $minutes[0]; $m * 60 + $duration <= $minutes[1] * 60; $m += $step / 60) {
                    $start = $day->setTime(intdiv($m, 60), $m % 60)->getTimestamp();

                    if ($start >= $from && $start + $duration <= $to + 1) {
                        $slots[] = ['start' => $start, 'end' => $start + $duration];
                    }
                }
            }
        }

        return $slots;
    }

    /**
     * @return list<array{int, int}>
     */
    private function busy(array $calendar, int $from, int $to, int|null $excludeBooking, bool $fresh): array
    {
        $ids = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $calendar['googleBusyCalendars']) ?: []))));
        $ids = array_values(array_unique(array_filter([$calendar['googleCalendarId'], ...$ids])));

        if ('' === (string) $calendar['googleCalendarId']) {
            throw new ApiException('Im Kalender ist kein „Kalender für Buchungen“ eingetragen.');
        }

        $google = static fn (GoogleCalendar $api): array => $api->busy($calendar['googleCredentials'], $ids, $from, $to);

        // Für die Anzeige auf volle Tage runden, damit sich der Zwischenspeicher lohnt
        $busy = $fresh ? $google($this->google) : $this->cache->get(
            'gratbooking_busy_'.$calendar['id'].'_'.hash('xxh3', implode(',', $ids).'|'.intdiv($from, 86400).'|'.intdiv($to, 86400)),
            function (ItemInterface $item) use ($calendar, $ids, $from, $to): array {
                $item->expiresAfter(60);

                return $this->google->busy($calendar['googleCredentials'], $ids, intdiv($from, 86400) * 86400, (intdiv($to, 86400) + 1) * 86400);
            },
        );

        // Eigene Buchungen blockieren samt ihrem Puffer, damit die Pause nach einem Termin erhalten bleibt
        $own = $this->db->fetchAllAssociative(
            "SELECT b.id, b.start, b.end, COALESCE(t.bufferBefore, 0) AS bufferBefore, COALESCE(t.bufferAfter, 0) AS bufferAfter
             FROM tl_gb_booking b LEFT JOIN tl_gb_type t ON t.id = b.type
             WHERE b.calendar = ? AND b.status IN ('confirmed', 'pending') AND b.end > ? AND b.start < ?",
            [$calendar['id'], $from - 86400, $to + 86400],
        );

        $exclude = null;

        foreach ($own as $booking) {
            if ((int) $booking['id'] === $excludeBooking) {
                $exclude = [(int) $booking['start'], (int) $booking['end']];
                continue;
            }

            $busy[] = [(int) $booking['start'] - (int) $booking['bufferBefore'] * 60, (int) $booking['end'] + (int) $booking['bufferAfter'] * 60];
        }

        // Beim Umbuchen den eigenen Termin auch aus den Google-Zeiten nehmen (er steht ja dort)
        if (null !== $exclude) {
            $busy = array_values(array_filter($busy, static fn (array $period): bool => $period !== $exclude));
        }

        return $busy;
    }

    /**
     * @return array<string, int>
     */
    private function bookingsPerDay(array $type, \DateTimeZone $tz, int $from, int $to, int|null $excludeBooking): array
    {
        $counts = [];

        foreach ($this->db->fetchAllAssociative("SELECT id, start FROM tl_gb_booking WHERE type = ? AND status IN ('confirmed', 'pending') AND start BETWEEN ? AND ?", [$type['id'], $from - 86400, $to + 86400]) as $booking) {
            if ((int) $booking['id'] !== $excludeBooking) {
                $day = (new \DateTimeImmutable('@'.$booking['start']))->setTimezone($tz)->format('Y-m-d');
                $counts[$day] = ($counts[$day] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * „09:00-12:00“ → [540, 720] (Minuten seit Mitternacht).
     *
     * @return array{int, int}|null
     */
    private static function parseRange(string $range): array|null
    {
        if (!preg_match('/^\s*(\d{1,2})[:.]?(\d{2})?\s*[-–]\s*(\d{1,2})[:.]?(\d{2})?\s*$/u', $range, $m)) {
            return null;
        }

        $start = (int) $m[1] * 60 + (int) ($m[2] ?? 0);
        $end = (int) $m[3] * 60 + (int) ($m[4] ?? 0);

        return $start < $end && $end <= 1440 ? [$start, $end] : null;
    }
}
