<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\ApiException;
use Gratlinie\GratBookingBundle\Booking\Availability;
use Gratlinie\GratBookingBundle\Google\GoogleCalendar;
use Gratlinie\GratBookingBundle\Zoom\Zoom;

/**
 * Backend: Zugangsdaten beim Speichern prüfen (Google lesen, Zoom anmelden), Adresse des Dienstkontos anzeigen,
 * Wochenzeiten prüfen, Alias der Terminart, Darstellung der Listen.
 */
class BookingDcaListener
{
    public function __construct(
        private readonly Connection $db,
        private readonly GoogleCalendar $google,
        private readonly Zoom $zoom,
    ) {
    }

    #[AsCallback(table: 'tl_gb_calendar', target: 'fields.googleCredentials.save')]
    public function saveCredentials(string|null $value, DataContainer $dc): string|null
    {
        if (null === $value || '' === trim($value)) {
            return $value;
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

        if (!$calendar || !$calendar['googleCredentials'] || !$calendar['googleCalendarId']) {
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

    #[AsCallback(table: 'tl_gb_booking', target: 'list.label.label')]
    public function bookingLabel(array $row, string $label, DataContainer $dc, array $columns): array
    {
        $columns[0] = Date::parse(Date::getNumericDatimFormat(), (int) $row['start']);
        $columns[2] = StringUtil::specialchars(trim($row['firstname'].' '.$row['lastname']));

        return $columns;
    }
}
