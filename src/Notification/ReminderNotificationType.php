<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Notification;

class ReminderNotificationType extends AbstractBookingNotificationType
{
    public const NAME = 'gratbooking_reminder';

    public function getName(): string
    {
        return self::NAME;
    }
}
