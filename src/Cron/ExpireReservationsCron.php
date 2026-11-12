<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;

/**
 * Reservierungen bezahlter Termine, deren Zahlung nie abgeschlossen wurde, auf „abgesagt“ setzen. Frei ist der
 * Termin schon ab Ablauf (siehe Availability); das hier hält nur die Liste im Backend sauber.
 */
#[AsCronJob('hourly')]
class ExpireReservationsCron
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function __invoke(): void
    {
        $this->db->executeStatement(
            "UPDATE tl_gb_booking SET status = 'cancelled', cancelledAt = expiresAt, tstamp = ? WHERE status = 'pending' AND expiresAt > 0 AND expiresAt < ?",
            [time(), time() - 3600],
        );
    }
}
