<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Booking;

/**
 * Der gewählte Termin ist inzwischen vergeben (oder der Kalender gerade gesperrt).
 */
class SlotTakenException extends \RuntimeException
{
}
