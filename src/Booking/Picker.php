<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Booking;

use Gratlinie\GratBookingBundle\Notification\Notifier;
use Symfony\Component\HttpFoundation\Request;

/**
 * Daten für die Terminauswahl: Monatskalender (Woche ab Montag) mit buchbaren Tagen und die Uhrzeiten des
 * gewählten Tags. Alles über Links mit GET-Parametern (monat, tag, zeit) – funktioniert ohne JavaScript.
 */
class Picker
{
    public function __construct(private readonly Availability $availability)
    {
    }

    /**
     * @param array<string, string> $keep Parameter, die in allen Links bleiben (z. B. termin, token)
     */
    public function build(array $type, array $calendar, Request $request, array $keep, string $anchor, int|null $excludeBooking = null): array
    {
        $tz = new \DateTimeZone($calendar['timezone'] ?: 'Europe/Berlin');
        $now = time();
        $slots = $this->availability->slots($type, $calendar, $now, $now + ((int) $type['horizon'] + 1) * 86400, $excludeBooking);

        $byDay = [];

        foreach ($slots as $slot) {
            $byDay[(new \DateTimeImmutable('@'.$slot['start']))->setTimezone($tz)->format('Y-m-d')][] = $slot;
        }

        $first = array_key_first($byDay);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query->get('monat')) ? (string) $request->query->get('monat') : substr((string) ($first ?? date('Y-m-d')), 0, 7);
        $monthStart = new \DateTimeImmutable($month.'-01', $tz);
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m');

        $day = (string) $request->query->get('tag');

        if (!isset($byDay[$day]) || !str_starts_with($day, $month)) {
            $day = (string) (array_values(array_filter(array_keys($byDay), static fn (string $d): bool => str_starts_with($d, $month)))[0] ?? '');
        }

        $url = static function (array $params) use ($request, $keep, $anchor): string {
            return $request->getBaseUrl().$request->getPathInfo().'?'.http_build_query(array_filter($keep + $params, static fn ($v) => null !== $v && '' !== $v)).'#'.$anchor;
        };

        $weeks = [];
        $cursor = $monthStart->modify('-'.((int) $monthStart->format('N') - 1).' days');
        $end = $monthStart->modify('last day of this month');

        while ($cursor <= $end) {
            $week = [];

            for ($i = 0; $i < 7; ++$i, $cursor = $cursor->modify('+1 day')) {
                $key = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $key,
                    'day' => (int) $cursor->format('j'),
                    'in_month' => $cursor->format('Y-m') === $month,
                    'available' => isset($byDay[$key]) && $cursor->format('Y-m') === $month,
                    'selected' => $key === $day,
                    'url' => $url(['monat' => $month, 'tag' => $key]),
                ];
            }

            $weeks[] = $week;
        }

        $selected = (int) $request->query->get('zeit');
        $times = array_map(static fn (array $slot): array => [
            'start' => $slot['start'],
            'label' => (new \DateTimeImmutable('@'.$slot['start']))->setTimezone($tz)->format('H:i'),
            'selected' => $slot['start'] === $selected,
            'url' => $url(['monat' => $month, 'tag' => $day, 'zeit' => $slot['start']]),
        ], $byDay[$day] ?? []);

        $lastMonth = $slots ? (new \DateTimeImmutable('@'.end($slots)['start']))->setTimezone($tz)->format('Y-m') : $today;
        $months = $GLOBALS['TL_LANG']['MONTHS'] ?? [];

        return [
            'month_label' => ($months[(int) $monthStart->format('n') - 1] ?? $monthStart->format('F')).' '.$monthStart->format('Y'),
            'prev_url' => $month > $today ? $url(['monat' => $monthStart->modify('-1 month')->format('Y-m')]) : null,
            'next_url' => $month < $lastMonth ? $url(['monat' => $monthStart->modify('+1 month')->format('Y-m')]) : null,
            'weekdays' => \array_slice(array_merge(\array_slice($GLOBALS['TL_LANG']['DAYS_SHORT'] ?? ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'], 1), \array_slice($GLOBALS['TL_LANG']['DAYS_SHORT'] ?? ['So'], 0, 1)), 0, 7),
            'weeks' => $weeks,
            'day' => $day,
            'day_label' => $day ? Notifier::format((new \DateTimeImmutable($day, $tz))->getTimestamp(), (new \DateTimeImmutable($day, $tz))->getTimestamp(), $calendar['timezone'])['date'] : '',
            'times' => $times,
            'selected' => \in_array($selected, array_column($times, 'start'), true) ? $selected : null,
            'selected_label' => \in_array($selected, array_column($times, 'start'), true) ? Notifier::format($selected, $selected + (int) $type['duration'] * 60, $calendar['timezone']) : null,
            'empty' => [] === $slots,
        ];
    }
}
