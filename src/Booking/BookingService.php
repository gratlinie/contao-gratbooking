<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Booking;

use Contao\CoreBundle\Cache\CacheTagManager;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\ApiException;
use Gratlinie\GratBookingBundle\Google\GoogleCalendar;
use Gratlinie\GratBookingBundle\Notification\BookedNotificationType;
use Gratlinie\GratBookingBundle\Notification\CancelledNotificationType;
use Gratlinie\GratBookingBundle\Notification\Notifier;
use Gratlinie\GratBookingBundle\Notification\RescheduledNotificationType;
use Gratlinie\GratBookingBundle\Zoom\Zoom;
use Psr\Log\LoggerInterface;

/**
 * Buchen, Umbuchen, Absagen. Während einer Buchung ist der Kalender gesperrt (MySQL-Lock), der Termin wird frisch
 * gegen Google geprüft. Reihenfolge: Zoom-Meeting → Google-Eintrag → Buchung speichern → Mails; scheitert ein
 * Schritt, werden die vorherigen zurückgenommen.
 */
class BookingService
{
    public function __construct(
        private readonly Connection $db,
        private readonly Availability $availability,
        private readonly GoogleCalendar $google,
        private readonly Zoom $zoom,
        private readonly Notifier $notifier,
        private readonly CacheTagManager $cacheTagManager,
        private readonly LoggerInterface $contaoErrorLogger,
    ) {
    }

    /**
     * @param array{firstname: string, lastname: string, email: string, phone: string, message: string} $contact
     *
     * @throws SlotTakenException wenn der Termin inzwischen vergeben ist
     * @throws ApiException       wenn Google oder Zoom scheitern
     */
    public function book(array $type, array $calendar, int $start, array $contact): array
    {
        return $this->locked($calendar, function () use ($type, $calendar, $start, $contact): array {
            if (!$this->availability->isFree($type, $calendar, $start)) {
                throw new SlotTakenException();
            }

            $end = $start + (int) $type['duration'] * 60;
            $booking = [
                'tstamp' => time(),
                'calendar' => $calendar['id'],
                'type' => $type['id'],
                'start' => $start,
                'end' => $end,
                'status' => 'confirmed',
                'token' => bin2hex(random_bytes(24)),
                'createdAt' => time(),
                'privacyAt' => time(),
                'sequence' => 0,
                'zoomJoinUrl' => '',
                'zoomPassword' => '',
                'zoomMeetingId' => '',
                'googleEventId' => '',
                ...$contact,
            ];

            $meeting = null;

            try {
                if ($type['zoom']) {
                    $meeting = $this->zoom->create($calendar, $type['title'].': '.$contact['firstname'].' '.$contact['lastname'], $start, (int) $type['duration'], $calendar['timezone']);
                    $booking['zoomMeetingId'] = $meeting['id'];
                    $booking['zoomJoinUrl'] = $meeting['join_url'];
                    $booking['zoomPassword'] = $meeting['password'];
                }

                $booking['googleEventId'] = $this->google->insertEvent($calendar['googleCredentials'], $calendar['googleCalendarId'], $this->event($booking, $type, $calendar));
                $this->db->insert('tl_gb_booking', $booking);
                $booking['id'] = (int) $this->db->lastInsertId();
            } catch (\Throwable $exception) {
                $this->rollback($calendar, $booking, $meeting);

                throw $exception;
            }

            $this->notifier->send(BookedNotificationType::NAME, $booking);
            $this->cacheTagManager->invalidateTags(['contao.db.tl_gb_booking']);

            return $booking;
        });
    }

    public function reschedule(array $booking, array $type, array $calendar, int $start): array
    {
        return $this->locked($calendar, function () use ($booking, $type, $calendar, $start): array {
            if (!$this->availability->isFree($type, $calendar, $start, (int) $booking['id'])) {
                throw new SlotTakenException();
            }

            $previous = $booking;
            $booking['start'] = $start;
            $booking['end'] = $start + (int) $type['duration'] * 60;
            $booking['sequence'] = (int) $booking['sequence'] + 1;

            if ($booking['zoomMeetingId']) {
                $this->zoom->reschedule($calendar, $booking['zoomMeetingId'], $start, (int) $type['duration'], $calendar['timezone']);
            }

            if ($booking['googleEventId']) {
                $this->google->updateEvent($calendar['googleCredentials'], $calendar['googleCalendarId'], $booking['googleEventId'], array_intersect_key($this->event($booking, $type, $calendar), ['start' => 1, 'end' => 1]));
            }

            $this->db->update('tl_gb_booking', ['start' => $booking['start'], 'end' => $booking['end'], 'sequence' => $booking['sequence'], 'reminderSentAt' => 0, 'tstamp' => time()], ['id' => $booking['id']]);

            $when = Notifier::format((int) $previous['start'], (int) $previous['end'], $calendar['timezone']);
            $this->notifier->send(RescheduledNotificationType::NAME, $booking, ['previous_date' => $when['date'], 'previous_time' => $when['time']]);
            $this->cacheTagManager->invalidateTags(['contao.db.tl_gb_booking']);

            return $booking;
        });
    }

    public function cancel(array $booking, array $calendar): void
    {
        if ('cancelled' === $booking['status']) {
            return;
        }

        // Absagen soll nie an Google oder Zoom scheitern: Fehler loggen, Buchung trotzdem absagen
        try {
            if ($booking['googleEventId']) {
                $this->google->deleteEvent($calendar['googleCredentials'], $calendar['googleCalendarId'], $booking['googleEventId']);
            }
        } catch (ApiException $exception) {
            $this->contaoErrorLogger->error('GratBooking: Google-Eintrag von Buchung ID '.$booking['id'].' nicht gelöscht – '.$exception->getMessage());
        }

        try {
            if ($booking['zoomMeetingId']) {
                $this->zoom->delete($calendar, $booking['zoomMeetingId']);
            }
        } catch (ApiException $exception) {
            $this->contaoErrorLogger->error('GratBooking: Zoom-Meeting von Buchung ID '.$booking['id'].' nicht gelöscht – '.$exception->getMessage());
        }

        $booking['status'] = 'cancelled';
        $booking['sequence'] = (int) $booking['sequence'] + 1;
        $this->db->update('tl_gb_booking', ['status' => 'cancelled', 'cancelledAt' => time(), 'sequence' => $booking['sequence'], 'tstamp' => time()], ['id' => $booking['id']]);
        $this->notifier->send(CancelledNotificationType::NAME, $booking);
        $this->cacheTagManager->invalidateTags(['contao.db.tl_gb_booking']);
    }

    /**
     * Absagen und Umbuchen durch den Kunden: nur bestätigte Termine und nur bis zur Frist des Kalenders.
     */
    public static function canManage(array $booking, array $calendar): bool
    {
        return 'confirmed' === $booking['status'] && (int) $booking['start'] - (int) $calendar['cancelHours'] * 3600 > time();
    }

    private function event(array $booking, array $type, array $calendar): array
    {
        $description = implode("\n", array_filter([
            $booking['firstname'].' '.$booking['lastname'],
            $booking['email'],
            $booking['phone'],
            $booking['message'] ? "\n".$booking['message'] : '',
            $booking['zoomJoinUrl'] ? "\nZoom: ".$booking['zoomJoinUrl'] : '',
        ]));

        return [
            'summary' => $type['title'].': '.$booking['firstname'].' '.$booking['lastname'],
            'description' => $description,
            'location' => $booking['zoomJoinUrl'] ?: $type['location'],
            'start' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', (int) $booking['start']), 'timeZone' => $calendar['timezone']],
            'end' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', (int) $booking['end']), 'timeZone' => $calendar['timezone']],
            'extendedProperties' => ['private' => ['gratbooking' => $booking['token']]],
        ];
    }

    private function rollback(array $calendar, array $booking, array|null $meeting): void
    {
        try {
            if (null !== $meeting) {
                $this->zoom->delete($calendar, $meeting['id']);
            }

            if ($booking['googleEventId']) {
                $this->google->deleteEvent($calendar['googleCredentials'], $calendar['googleCalendarId'], $booking['googleEventId']);
            }
        } catch (\Throwable $exception) {
            $this->contaoErrorLogger->error('GratBooking: Zurücknehmen einer fehlgeschlagenen Buchung unvollständig – '.$exception->getMessage());
        }
    }

    /**
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    private function locked(array $calendar, \Closure $callback): mixed
    {
        $name = 'gratbooking_'.$calendar['id'];

        if (1 !== (int) $this->db->fetchOne('SELECT GET_LOCK(?, 10)', [$name])) {
            throw new SlotTakenException();
        }

        try {
            return $callback();
        } finally {
            $this->db->fetchOne('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }
}
