<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Notification;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\Booking\Ics;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Terminal42\NotificationCenterBundle\BulkyItem\BulkyItemStorage;
use Terminal42\NotificationCenterBundle\BulkyItem\FileItem;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Parcel\Stamp\BulkyItemsStamp;

/**
 * Verschickt alle Benachrichtigungen eines Buchungs-Typs (Notification Center) mit den Tokens der Buchung und
 * der Kalenderdatei als Anhang (##booking_ics## in „Anhänge über Tokens“).
 */
class Notifier
{
    public function __construct(
        private readonly Connection $db,
        private readonly NotificationCenter $notificationCenter,
        private readonly BulkyItemStorage $bulkyItemStorage,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly UrlGeneratorInterface $router,
        private readonly ContaoFramework $framework,
        private readonly LoggerInterface $contaoErrorLogger,
    ) {
    }

    public function send(string $type, array $booking, array $extra = []): void
    {
        $ids = $this->db->fetchFirstColumn('SELECT id FROM tl_nc_notification WHERE type = ? ORDER BY id', [$type]);

        if ([] === $ids) {
            return;
        }

        try {
            $tokens = $this->tokens($booking, $type) + $extra;

            // Anhänge nimmt das Notification Center nur, wenn der Gutschein auch als Stempel am Versand hängt
            foreach ($ids as $id) {
                $stamps = $this->notificationCenter->createBasicStampsForNotification((int) $id, $tokens, 'de');
                $this->notificationCenter->sendNotificationWithStamps((int) $id, $stamps->with(new BulkyItemsStamp([$tokens['booking_ics']])));
            }
        } catch (\Throwable $exception) {
            $this->contaoErrorLogger->error(\sprintf('GratBooking: Benachrichtigung „%s“ zu Buchung ID %d nicht verschickt: %s', $type, $booking['id'], $exception->getMessage()));
        }
    }

    public function manageUrl(array $booking, array $calendar): string
    {
        $this->framework->initialize();
        $page = $calendar['managePage'] ? $this->framework->getAdapter(PageModel::class)->findById($calendar['managePage']) : null;

        if (null === $page) {
            return '';
        }

        return $this->contentUrlGenerator->generate($page, [], UrlGeneratorInterface::ABSOLUTE_URL).'?token='.$booking['token'];
    }

    /**
     * Datum und Uhrzeit in der Zeitzone des Kalenders, unabhängig von der Zeitzone des Servers.
     */
    public static function datetime(int $timestamp, string $timezone, string $format = 'd.m.Y H:i'): string
    {
        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone($timezone ?: 'Europe/Berlin'))->format($format);
    }

    /**
     * @return array{date: string, time: string}
     */
    public static function format(int $start, int $end, string $timezone): array
    {
        $tz = new \DateTimeZone($timezone ?: 'Europe/Berlin');
        $from = (new \DateTimeImmutable('@'.$start))->setTimezone($tz);
        $to = (new \DateTimeImmutable('@'.$end))->setTimezone($tz);
        $days = $GLOBALS['TL_LANG']['DAYS'] ?? ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $months = $GLOBALS['TL_LANG']['MONTHS'] ?? ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        return [
            'date' => $days[(int) $from->format('w')].', '.$from->format('j').'. '.$months[(int) $from->format('n') - 1].' '.$from->format('Y'),
            'time' => $from->format('H:i').'–'.$to->format('H:i').' Uhr',
        ];
    }

    private function tokens(array $booking, string $type): array
    {
        $this->framework->initialize();
        $bookingType = $this->db->fetchAssociative('SELECT * FROM tl_gb_type WHERE id = ?', [$booking['type']]) ?: [];
        $calendar = $this->db->fetchAssociative('SELECT * FROM tl_gb_calendar WHERE id = ?', [$booking['calendar']]) ?: [];
        $tz = $calendar['timezone'] ?? 'Europe/Berlin';
        $when = self::format((int) $booking['start'], (int) $booking['end'], $tz);
        $manage = $this->manageUrl($booking, $calendar);
        $host = parse_url($manage, PHP_URL_HOST) ?: 'gratbooking';
        $ics = Ics::create($booking, $bookingType, $calendar, $host, CancelledNotificationType::NAME === $type);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $ics);
        rewind($stream);
        $adminEmail = $this->framework->getAdapter(\Contao\Config::class)->get('adminEmail');

        return [
            'booking_id' => $booking['id'],
            'booking_type' => $bookingType['title'] ?? '',
            'booking_date' => $when['date'],
            'booking_time' => $when['time'],
            'booking_start' => self::datetime((int) $booking['start'], $tz, 'Y-m-d H:i'),
            'booking_end' => self::datetime((int) $booking['end'], $tz, 'Y-m-d H:i'),
            'booking_duration' => (string) intdiv((int) $booking['end'] - (int) $booking['start'], 60),
            'booking_firstname' => $booking['firstname'],
            'booking_lastname' => $booking['lastname'],
            'booking_email' => $booking['email'],
            'booking_phone' => $booking['phone'],
            'booking_message' => (string) $booking['message'],
            'booking_location' => $booking['zoomJoinUrl'] ? 'Zoom' : ($bookingType['location'] ?? ''),
            'zoom_url' => (string) $booking['zoomJoinUrl'],
            'zoom_password' => (string) $booking['zoomPassword'],
            'manage_url' => $manage,
            'cancel_deadline' => self::format((int) $booking['start'] - (int) ($calendar['cancelHours'] ?? 0) * 3600, (int) $booking['start'], $tz)['date'],
            'organizer_name' => $calendar['organizerName'] ?? '',
            'organizer_email' => $calendar['organizerEmail'] ?? '',
            'calendar_title' => $calendar['title'] ?? '',
            'admin_email' => $calendar['organizerEmail'] ?: $adminEmail,
            'backend_url' => $this->router->generate('contao_backend', ['do' => 'gb_bookings', 'act' => 'edit', 'id' => $booking['id']], UrlGeneratorInterface::ABSOLUTE_URL),
            'booking_ics' => $this->bulkyItemStorage->store(FileItem::fromStream($stream, 'termin.ics', 'text/calendar', \strlen($ics))),
        ];
    }
}
