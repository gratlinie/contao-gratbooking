<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Validator;
use Gratlinie\GratBookingBundle\ApiException;
use Gratlinie\GratBookingBundle\Booking\SlotTakenException;
use Gratlinie\GratBookingBundle\Notification\Notifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Termin buchen: Terminart wählen (bei mehreren), Tag und Uhrzeit im Kalender, Kontaktdaten, buchen. Danach
 * steht die Bestätigung an derselben Stelle (?gebucht=<token>).
 */
#[AsFrontendModule('gb_booking', category: 'gratbooking')]
class BookingController extends AbstractBookingController
{
    private LoggerInterface|null $logger = null;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setLogger(LoggerInterface $contaoErrorLogger): void
    {
        $this->logger = $contaoErrorLogger;
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $calendar = $this->calendar((int) $model->gbCalendar);
        $anchor = 'gb-'.$model->id;
        $template->set('anchor', $anchor);

        if (null === $calendar) {
            return new Response();
        }

        $selection = array_map('intval', StringUtil::deserialize($model->gbTypes, true));
        $types = array_values(array_filter(
            $this->db->fetchAllAssociative('SELECT * FROM tl_gb_type WHERE pid = ? AND published = 1 ORDER BY sorting', [$calendar['id']]),
            static fn (array $type): bool => [] === $selection || \in_array((int) $type['id'], $selection, true),
        ));

        // Bestätigung nach dem Buchen
        if ($token = (string) $request->query->get('gebucht')) {
            $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE token = ?', [$token]);

            if ($booking) {
                $type = $this->type((int) $booking['type']);
                $template->set('step', 'done');
                $template->set('booking', $booking);
                $template->set('booking_type', $type);
                $template->set('when', Notifier::format((int) $booking['start'], (int) $booking['end'], $calendar['timezone']));

                return $this->respond($template);
            }
        }

        $alias = (string) $request->query->get('termin');
        $type = 1 === \count($types) ? $types[0] : (array_values(array_filter($types, static fn (array $t): bool => $t['alias'] === $alias || (string) $t['id'] === $alias))[0] ?? null);
        $base = $request->getBaseUrl().$request->getPathInfo();

        $template->set('gb_types', array_map(static fn (array $t): array => $t + ['url' => $base.'?termin='.rawurlencode($t['alias'] ?: (string) $t['id']).'#'.$anchor], $types));
        $template->set('gb_type', $type);
        $template->set('change_url', \count($types) > 1 ? $base.'#'.$anchor : null);

        if (null === $type) {
            $template->set('step', 'types');

            return $this->respond($template);
        }

        $keep = \count($types) > 1 ? ['termin' => $type['alias'] ?: (string) $type['id']] : [];
        $values = ['firstname' => '', 'lastname' => '', 'email' => '', 'phone' => '', 'message' => ''];
        $errors = [];
        $formId = 'gb_booking_'.$model->id;

        if ($request->isMethod('POST') && $formId === $request->request->get('FORM_SUBMIT')) {
            foreach ($values as $key => $_) {
                $values[$key] = trim(StringUtil::decodeEntities((string) $request->request->get($key)));
            }

            $start = (int) $request->request->get('zeit');
            $request->query->set('zeit', (string) $start);
            $request->query->set('tag', (new \DateTimeImmutable('@'.$start))->setTimezone(new \DateTimeZone($calendar['timezone']))->format('Y-m-d'));
            $request->query->set('monat', substr((string) $request->query->get('tag'), 0, 7));
            $errors = $this->validate($values, $request);

            if ([] === $errors) {
                try {
                    $booking = $this->bookings->book($type, $calendar, $start, $values);

                    throw new RedirectResponseException($base.'?'.http_build_query($keep + ['gebucht' => $booking['token']]).'#'.$anchor, 303);
                } catch (SlotTakenException) {
                    $errors['zeit'] = $GLOBALS['TL_LANG']['MSC']['gb_slot_taken'];
                    $request->query->remove('zeit');
                } catch (ApiException $exception) {
                    $this->logger?->error('GratBooking: Buchung fehlgeschlagen – '.$exception->getMessage());
                    $errors['form'] = $GLOBALS['TL_LANG']['MSC']['gb_failed'];
                }
            }
        }

        try {
            $picker = $this->picker->build($type, $calendar, $request, $keep, $anchor);
        } catch (ApiException $exception) {
            $this->logger?->error('GratBooking: freie Termine nicht abrufbar – '.$exception->getMessage());
            $template->set('step', 'unavailable');

            return $this->respond($template);
        }

        $template->set('step', 'pick');
        $template->set('picker', $picker);
        $template->set('form_id', $formId);
        $template->set('values', $values);
        $template->set('errors', $errors);
        $template->set('privacy', $this->privacyLabel($model));

        return $this->respond($template);
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $values, Request $request): array
    {
        $msg = $GLOBALS['TL_LANG']['MSC'];
        $errors = [];

        foreach (['firstname', 'lastname', 'email'] as $field) {
            if ('' === $values[$field]) {
                $errors[$field] = $msg['gb_required'];
            }
        }

        if ('' !== $values['email'] && !Validator::isEmail($values['email'])) {
            $errors['email'] = $msg['gb_email'];
        }

        if (!$request->request->get('privacy')) {
            $errors['privacy'] = $msg['gb_privacy_required'];
        }

        // Falle für Bots: verstecktes Feld muss leer bleiben
        if ('' !== (string) $request->request->get('website')) {
            $errors['form'] = $msg['gb_failed'];
        }

        if ((int) $request->request->get('zeit') <= time()) {
            $errors['zeit'] = $msg['gb_pick_time'];
        }

        return $errors;
    }

    private function privacyLabel(ModuleModel $model): string
    {
        $text = trim((string) $model->gbPrivacy);

        return '' !== $text ? $text : $GLOBALS['TL_LANG']['MSC']['gb_privacy_default'];
    }
}
