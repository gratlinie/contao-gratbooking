<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Gratlinie\GratBookingBundle\ApiException;
use Gratlinie\GratBookingBundle\Booking\BookingService;
use Gratlinie\GratBookingBundle\Booking\SlotTakenException;
use Gratlinie\GratBookingBundle\Notification\Notifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Buchung verwalten über den Link aus der Bestätigung (?token=…): Termin ansehen, absagen oder umbuchen – bis
 * zur Frist des Kalenders. Bezahlte Termine lassen sich nur umbuchen. Diese Seite als „Seite zum Verwalten“ im
 * Kalender wählen.
 */
#[AsFrontendModule('gb_manage', category: 'gratbooking')]
class ManageController extends AbstractBookingController
{
    private LoggerInterface|null $logger = null;

    #[Required]
    public function setLogger(LoggerInterface $contaoErrorLogger): void
    {
        $this->logger = $contaoErrorLogger;
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $anchor = 'gb-'.$model->id;
        $template->set('anchor', $anchor);
        $token = (string) $request->query->get('token');
        $booking = '' !== $token ? $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE token = ?', [$token]) : false;
        $calendar = $booking ? $this->calendar((int) $booking['calendar']) : null;
        $type = $booking ? $this->type((int) $booking['type']) : null;

        if (!$booking || null === $calendar || null === $type) {
            $template->set('step', 'missing');

            return $this->respond($template);
        }

        $base = $request->getBaseUrl().$request->getPathInfo();
        $self = $base.'?token='.rawurlencode($token).'#'.$anchor;
        $formId = 'gb_manage_'.$model->id;
        $errors = [];

        if (BookingService::canManage($booking, $calendar) && $request->isMethod('POST') && $formId === $request->request->get('FORM_SUBMIT')) {
            try {
                if ('cancel' === $request->request->get('action') && BookingService::canCancel($booking, $calendar)) {
                    $this->bookings->cancel($booking, $calendar);

                    throw new RedirectResponseException($base.'?'.http_build_query(['token' => $token, 'abgesagt' => 1]).'#'.$anchor, 303);
                }

                if ('reschedule' === $request->request->get('action')) {
                    $this->bookings->reschedule($booking, $type, $calendar, (int) $request->request->get('zeit'));

                    throw new RedirectResponseException($base.'?'.http_build_query(['token' => $token, 'umgebucht' => 1]).'#'.$anchor, 303);
                }
            } catch (SlotTakenException) {
                $errors['zeit'] = $GLOBALS['TL_LANG']['MSC']['gb_slot_taken'];
            } catch (ApiException $exception) {
                $this->logger?->error('GratBooking: Umbuchen fehlgeschlagen – '.$exception->getMessage());
                $errors['form'] = $GLOBALS['TL_LANG']['MSC']['gb_failed'];
            }
        }

        $template->set('booking', $booking);
        $template->set('booking_type', $type);
        $template->set('calendar', $calendar);
        $template->set('when', Notifier::format((int) $booking['start'], (int) $booking['end'], $calendar['timezone']));
        $template->set('deadline', Notifier::format((int) $booking['start'] - (int) $calendar['cancelHours'] * 3600, (int) $booking['start'], $calendar['timezone']));
        $template->set('can_manage', BookingService::canManage($booking, $calendar));
        $template->set('can_cancel', BookingService::canCancel($booking, $calendar));
        $template->set('notice', $request->query->get('abgesagt') ? 'cancelled' : ($request->query->get('umgebucht') ? 'rescheduled' : null));
        $template->set('form_id', $formId);
        $template->set('errors', $errors);
        $template->set('self_url', $self);
        $template->set('step', 'show');

        if (BookingService::canManage($booking, $calendar) && $request->query->has('umbuchen')) {
            try {
                $template->set('picker', $this->picker->build($type, $calendar, $request, ['token' => $token, 'umbuchen' => 1], $anchor, (int) $booking['id']));
                $template->set('step', 'reschedule');
            } catch (ApiException $exception) {
                $this->logger?->error('GratBooking: freie Termine nicht abrufbar – '.$exception->getMessage());
                $errors['form'] = $GLOBALS['TL_LANG']['MSC']['gb_failed'];
                $template->set('errors', $errors);
            }
        }

        $template->set('cancel_url', $base.'?'.http_build_query(['token' => $token, 'absagen' => 1]).'#'.$anchor);
        $template->set('confirm_cancel', $request->query->has('absagen'));
        $template->set('reschedule_url', $base.'?'.http_build_query(['token' => $token, 'umbuchen' => 1]).'#'.$anchor);

        return $this->respond($template);
    }
}
