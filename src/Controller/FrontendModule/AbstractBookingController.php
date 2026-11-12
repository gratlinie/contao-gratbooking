<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\Booking\BookingService;
use Gratlinie\GratBookingBundle\Booking\Picker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gemeinsames der Buchungsmodule: Daten laden, CSRF-Token, Assets, Antwort nicht zwischenspeichern.
 */
abstract class AbstractBookingController extends AbstractFrontendModuleController
{
    public function __construct(
        protected readonly Connection $db,
        protected readonly Picker $picker,
        protected readonly BookingService $bookings,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    protected function calendar(int $id): array|null
    {
        return $this->db->fetchAssociative('SELECT * FROM tl_gb_calendar WHERE id = ? AND published = 1', [$id]) ?: null;
    }

    protected function type(int $id): array|null
    {
        return $this->db->fetchAssociative('SELECT * FROM tl_gb_type WHERE id = ?', [$id]) ?: null;
    }

    protected function respond(FragmentTemplate $template): Response
    {
        $template->set('request_token', $this->csrfTokenManager->getDefaultTokenValue());
        $response = $template->getResponse();

        // Freie Termine und Buchungsdaten nie im Seiten-Cache
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
