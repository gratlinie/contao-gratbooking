<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Notification;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\FileTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

/**
 * Gemeinsame Tokens der Buchungs-Benachrichtigungen. Die Kalenderdatei als Anhang: Feld „Anhänge über Tokens“
 * der Nachricht auf ##booking_ics## setzen.
 */
abstract class AbstractBookingNotificationType implements NotificationTypeInterface
{
    public const TEXT_TOKENS = [
        'booking_id', 'booking_type', 'booking_date', 'booking_time', 'booking_start', 'booking_end', 'booking_duration',
        'booking_firstname', 'booking_lastname', 'booking_phone', 'booking_message', 'booking_location',
        'zoom_url', 'zoom_password', 'manage_url', 'cancel_deadline', 'previous_date', 'previous_time',
        'organizer_name', 'calendar_title', 'backend_url',
    ];

    public function __construct(private readonly TokenDefinitionFactoryInterface $factory)
    {
    }

    public function getTokenDefinitions(): array
    {
        $definitions = array_map(fn (string $token) => $this->factory->create(TextTokenDefinition::class, $token, 'gratbooking.'.$token), self::TEXT_TOKENS);
        $definitions[] = $this->factory->create(EmailTokenDefinition::class, 'booking_email', 'gratbooking.booking_email');
        $definitions[] = $this->factory->create(EmailTokenDefinition::class, 'organizer_email', 'gratbooking.organizer_email');
        $definitions[] = $this->factory->create(EmailTokenDefinition::class, 'admin_email', 'gratbooking.admin_email');
        $definitions[] = $this->factory->create(FileTokenDefinition::class, 'booking_ics', 'gratbooking.booking_ics');

        return $definitions;
    }
}
