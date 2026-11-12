<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Controller\Backend;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\Booking\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Operation „Absagen“ in der Liste der Buchungen: löscht Google-Eintrag und Zoom-Meeting und schickt die
 * Absage-Mail. Die Rückfrage stellt die Operation in der Liste.
 */
#[Route('/%contao.backend.route_prefix%/gratbooking/cancel', name: 'gratbooking_cancel', defaults: ['_scope' => 'backend'])]
class CancelController extends AbstractController
{
    public function __construct(
        private readonly Connection $db,
        private readonly BookingService $bookings,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'gb_bookings');
        $this->framework->initialize();
        $message = $this->framework->getAdapter(Message::class);
        $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE id = ?', [$request->query->getInt('id')]);
        $back = $this->generateUrl('contao_backend', ['do' => 'gb_bookings']);

        if (!$booking || 'cancelled' === $booking['status']) {
            $message->addInfo($GLOBALS['TL_LANG']['tl_gb_booking']['alreadyCancelled'] ?? 'Bereits abgesagt.');

            return $this->redirect($back);
        }

        $calendar = $this->db->fetchAssociative('SELECT * FROM tl_gb_calendar WHERE id = ?', [$booking['calendar']]) ?: [];
        $this->bookings->cancel($booking, $calendar);
        $message->addConfirmation(\sprintf($GLOBALS['TL_LANG']['tl_gb_booking']['cancelledMsg'] ?? 'Buchung %s abgesagt.', $booking['id']));

        return $this->redirect($back);
    }
}
