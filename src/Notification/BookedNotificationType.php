<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Notification;

class BookedNotificationType extends AbstractBookingNotificationType
{
    public const NAME = 'gratbooking_booked';

    public function getName(): string
    {
        return self::NAME;
    }
}
