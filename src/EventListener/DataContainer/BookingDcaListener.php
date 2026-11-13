<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\ApiException;
use Gratlinie\GratBookingBundle\Booking\Availability;
use Gratlinie\GratBookingBundle\Booking\BookingService;
use Gratlinie\GratBookingBundle\Google\GoogleCalendar;
use Gratlinie\GratBookingBundle\Notification\Notifier;
use Gratlinie\GratBookingBundle\Zoom\Zoom;

/**
 * Backend: Zugangsdaten beim Speichern prüfen (Google lesen, Zoom anmelden), Adresse des Dienstkontos anzeigen,
 * Wochenzeiten prüfen, Alias der Terminart, Darstellung der Listen, beim Löschen einer Buchung Google und Zoom aufräumen.
 */
class BookingDcaListener
{
    public function __construct(
        private readonly Connection $db,
        private readonly GoogleCalendar $google,
        private readonly Zoom $zoom,
        private readonly BookingService $bookings,
    ) {
    }

    /**
     * Den privaten Schlüssel nach dem Speichern nicht mehr anzeigen; leer lassen behält ihn.
     */
    #[AsCallback(table: 'tl_gb_calendar', target: 'fields.googleCredentials.load')]
    public function loadCredentials(string|null $value): string
    {
        return '';
    }

    #[AsCallback(table: 'tl_gb_calendar', target: 'fields.googleCredentials.save')]
    public function saveCredentials(string|null $value, DataContainer $dc): string|null
    {
        if (null === $value || '' === trim($value)) {
            return $this->db->fetchOne('SELECT googleCredentials FROM tl_gb_calendar WHERE id = ?', [$dc->id]) ?: null;
        }

        // Wirft eine verständliche Meldung, wenn der JSON-Schlüssel nicht passt
        $account = GoogleCalendar::parseCredentials($value);
        $this->db->update('tl_gb_calendar', ['googleAccount' => $account['client_email']], ['id' => $dc->id]);

        return $value;
    }

    #[AsCallback(table: 'tl_gb_calendar', target: 'config.onsubmit')]
    public function testConnections(DataContainer $dc): void
    {
        $calendar = $this->db->fetchAssociative('SELECT * FROM tl_gb_calendar WHERE id = ?', [$dc->id]);

        if (!$calendar || !$calendar['googleCredentials']) {
            return;
        }

        // Erst Schlüssel speichern, dann (mit der angezeigten Adresse) freigeben und die Kalender-ID eintragen
        if (!$calendar['googleCalendarId']) {
            Message::addInfo(\sprintf($GLOBALS['TL_LANG']['tl_gb_calendar']['calendarMissing'], $calendar['googleAccount']));

            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $calendar['googleBusyCalendars']) ?: []))));

        try {
            $this->google->busy($calendar['googleCredentials'], array_values(array_unique([$calendar['googleCalendarId'], ...$ids])), time(), time() + 86400);
            Message::addConfirmation(\sprintf($GLOBALS['TL_LANG']['tl_gb_calendar']['googleOk'], $calendar['googleAccount']));
        } catch (ApiException $exception) {
            Message::addError($exception->getMessage());
        }

        if (Zoom::isConfigured($calendar)) {
            try {
                $this->zoom->testConnection($calendar);
                Message::addConfirmation($GLOBALS['TL_LANG']['tl_gb_calendar']['zoomOk']);
            } catch (ApiException $exception) {
                Message::addError($exception->getMessage());
            }
        }
    }

    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursMo.save')]
    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursTu.save')]
    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursWe.save')]
    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursTh.save')]
    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursFr.save')]
    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursSa.save')]
    #[AsCallback(table: 'tl_gb_type', target: 'fields.hoursSu.save')]
    public function saveHours(string $value): string
    {
        if ($invalid = Availability::validateHours($value)) {
            throw new \RuntimeException(\sprintf($GLOBALS['TL_LANG']['tl_gb_type']['hoursInvalid'], implode(', ', $invalid)));
        }

        return $value;
    }

    #[AsCallback(table: 'tl_gb_absence', target: 'fields.endDate.save')]
    public function saveAbsenceEnd(mixed $value, DataContainer $dc): mixed
    {
        $start = (int) ($dc->getCurrentRecord()['startDate'] ?? 0);
        $submitted = \Contao\Input::post('startDate');

        // Beim Speichern steht das neue Startdatum noch nicht im Datensatz
        if (null !== $submitted && '' !== $submitted) {
            try {
                $start = (int) (new \Contao\Date((string) $submitted, \Contao\Date::getNumericDateFormat()))->tstamp;
            } catch (\Throwable) {
            }
        }

        if ('' !== (string) $value && (int) $value < $start) {
            throw new \RuntimeException($GLOBALS['TL_LANG']['tl_gb_absence']['endBeforeStart']);
        }

        return $value;
    }

    #[AsCallback(table: 'tl_gb_absence', target: 'list.sorting.child_record')]
    public function absenceRecord(array $row): string
    {
        $from = date('d.m.Y', (int) $row['startDate']);
        $to = date('d.m.Y', (int) $row['endDate']);

        return \sprintf('<strong>%s</strong>%s', $from === $to ? $from : $from.' – '.$to, $row['title'] ? ' <span style="color:#999">'.StringUtil::specialchars($row['title']).'</span>' : '');
    }

    #[AsCallback(table: 'tl_gb_type', target: 'fields.alias.save')]
    public function saveAlias(string $value, DataContainer $dc): string
    {
        return '' !== $value ? $value : StringUtil::generateAlias((string) $dc->activeRecord?->title).'-'.$dc->id;
    }

    #[AsCallback(table: 'tl_gb_type', target: 'list.sorting.child_record')]
    public function typeRecord(array $row): string
    {
        $days = array_filter(array_map(static fn (string $d): string => $row['hours'.$d] ? $d : '', Availability::DAYS));

        return \sprintf('<strong>%s</strong> <span style="color:#999">– %d min%s · %s</span>', StringUtil::specialchars($row['title']), $row['duration'], $row['zoom'] ? ' · Zoom' : '', implode(' ', $days) ?: '–');
    }

    #[AsCallback(table: 'tl_module', target: 'fields.gbTypes.options')]
    public function typeOptions(DataContainer|null $dc = null): array
    {
        $calendar = (int) ($dc?->activeRecord?->gbCalendar ?? 0);

        return $this->db->fetchAllKeyValue('SELECT id, title FROM tl_gb_type WHERE pid = ? ORDER BY sorting', [$calendar]);
    }

    /**
     * „Löschen“ entfernt sonst nur den Datensatz – Google-Eintrag und Zoom-Meeting blieben stehen.
     */
    #[AsCallback(table: 'tl_gb_booking', target: 'config.ondelete')]
    public function deleteBooking(DataContainer $dc): void
    {
        $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE id = ?', [$dc->id]);

        if (false !== $booking) {
            $this->bookings->discard($booking);
        }
    }

    #[AsCallback(table: 'tl_gb_booking', target: 'list.label.label')]
    public function bookingLabel(array $row, string $label, DataContainer $dc, array $columns): array
    {
        $timezone = (string) ($this->db->fetchOne('SELECT timezone FROM tl_gb_calendar WHERE id = ?', [$row['calendar']]) ?: 'Europe/Berlin');
        $columns[0] = Notifier::datetime((int) $row['start'], $timezone);
        $columns[2] = StringUtil::specialchars(trim($row['firstname'].' '.$row['lastname']));

        return $columns;
    }
}
