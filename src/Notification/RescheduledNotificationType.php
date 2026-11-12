<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Notification;

class RescheduledNotificationType extends AbstractBookingNotificationType
{
    public const NAME = 'gratbooking_rescheduled';

    public function getName(): string
    {
        return self::NAME;
    }
}
