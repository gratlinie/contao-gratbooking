<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\Notification\Notifier;
use Gratlinie\GratBookingBundle\Notification\ReminderNotificationType;

/**
 * Erinnerung vor dem Termin (Stunden laut Kalender, 0 = aus), höchstens einmal je Buchung bzw. Umbuchung.
 */
#[AsCronJob('*/15 * * * *')]
class ReminderCron
{
    public function __construct(
        private readonly Connection $db,
        private readonly Notifier $notifier,
    ) {
    }

    public function __invoke(): void
    {
        $now = time();
        $due = $this->db->fetchAllAssociative(
            "SELECT b.* FROM tl_gb_booking b JOIN tl_gb_calendar c ON c.id = b.calendar
             WHERE b.status = 'confirmed' AND b.reminderSentAt = 0 AND c.reminderHours > 0
               AND b.start > ? AND b.start - c.reminderHours * 3600 <= ? AND b.createdAt < b.start - c.reminderHours * 3600",
            [$now, $now],
        );

        foreach ($due as $booking) {
            $this->db->update('tl_gb_booking', ['reminderSentAt' => $now], ['id' => $booking['id']]);
            $this->notifier->send(ReminderNotificationType::NAME, $booking);
        }
    }
}
