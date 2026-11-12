<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Notification;

class CancelledNotificationType extends AbstractBookingNotificationType
{
    public const NAME = 'gratbooking_cancelled';

    public function getName(): string
    {
        return self::NAME;
    }
}
