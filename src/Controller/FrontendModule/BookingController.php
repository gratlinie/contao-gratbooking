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
use Gratlinie\GratBookingBundle\Payment\BookingPayment;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Termin buchen: Terminart wählen (bei mehreren), Tag und Uhrzeit im Kalender, Kontaktdaten, buchen. Danach
 * steht die Bestätigung an derselben Stelle (?gebucht=<token>).
 *
 * Bezahlte Terminart (GratShop): der Termin wird reserviert, die Zahlung läuft bei Stripe; zurück kommt der Kunde
 * auf ?gebucht=<token> (bestätigt nach der Zahlung) oder ?abgebrochen=<token> (Reservierung wird freigegeben).
 */
#[AsFrontendModule('gb_booking', category: 'gratbooking')]
class BookingController extends AbstractBookingController
{
    private LoggerInterface|null $logger = null;

    private BookingPayment|null $payment = null;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setLogger(LoggerInterface $contaoErrorLogger): void
    {
        $this->logger = $contaoErrorLogger;
    }

    /**
     * Nur mit GratShop vorhanden, sonst bleibt es bei kostenlosen Terminen.
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setPayment(BookingPayment|null $payment = null): void
    {
        $this->payment = $payment;
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

        $notice = null;
        $values = ['firstname' => '', 'lastname' => '', 'email' => '', 'phone' => '', 'message' => ''];

        // Zahlung abgebrochen: Reservierung freigeben, Termin und Angaben bleiben ausgewählt
        if ($token = (string) $request->query->get('abgebrochen')) {
            $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE token = ?', [$token]);

            if ($booking && 'pending' === $booking['status']) {
                $open = true;

                try {
                    $open = $this->payment?->abort($booking) ?? true;
                } catch (\Throwable $exception) {
                    $this->logger?->error('GratBooking: Stripe-Session von Buchung ID '.$booking['id'].' nicht geschlossen – '.$exception->getMessage());
                }

                if ($open) {
                    $this->bookings->release($booking);
                    $notice = 'aborted';
                    $values = array_intersect_key($booking, $values) + $values;
                    $this->select($request, (int) $booking['start'], $calendar);
                } else {
                    // Doch bezahlt (z. B. Zurück nach dem Zahlen): weiter zur Bestätigung
                    $request->query->set('gebucht', $token);
                }
            }
        }

        // Bestätigung nach dem Buchen bzw. nach der Rückkehr von der Zahlung
        if ($token = (string) $request->query->get('gebucht')) {
            $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE token = ?', [$token]);

            if ($booking && 'pending' === $booking['status'] && null !== $this->payment) {
                try {
                    $this->payment->complete($booking);
                } catch (\Throwable $exception) {
                    $this->logger?->error('GratBooking: Zahlung von Buchung ID '.$booking['id'].' nicht abgeschlossen – '.$exception->getMessage());
                }

                $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE token = ?', [$token]);
            }

            if ($booking && 'cancelled' !== $booking['status']) {
                $type = $this->type((int) $booking['type']);
                $template->set('step', 'confirmed' === $booking['status'] ? 'done' : 'waiting');
                $template->set('booking', $booking);
                $template->set('booking_type', $type);
                $template->set('when', Notifier::format((int) $booking['start'], (int) $booking['end'], $calendar['timezone']));
                $template->set('amount', (float) $booking['amount'] > 0 ? BookingPayment::money((float) $booking['amount']) : null);
                $template->set('reload_url', $request->getRequestUri());

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
        $product = $this->payment?->product($type);
        $errors = [];
        $formId = 'gb_booking_'.$model->id;

        if ($request->isMethod('POST') && $formId === $request->request->get('FORM_SUBMIT')) {
            foreach ($values as $key => $_) {
                $values[$key] = trim(StringUtil::decodeEntities((string) $request->request->get($key)));
            }

            $start = (int) $request->request->get('zeit');
            $this->select($request, $start, $calendar);
            $errors = $this->validate($values, $request, null !== $product);

            if ([] === $errors) {
                try {
                    if (null !== $product) {
                        throw new RedirectResponseException($this->checkout($type, $calendar, $product, $start, $values, $request->getSchemeAndHttpHost().$base, $keep, $anchor), 303);
                    }

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
        $template->set('notice', $notice);
        $template->set('privacy', $this->privacyLabel($model));
        $template->set('price', null !== $product ? $this->payment->grossPrice($product) : null);
        $template->set('consent', null !== $product ? $this->consentLabel($model) : null);
        $template->set('reserve_minutes', intdiv(BookingPayment::RESERVATION, 60) - 2);

        return $this->respond($template);
    }

    /**
     * Termin reservieren und die Zahlung starten; liefert die Adresse der Stripe-Zahlungsseite.
     *
     * @throws SlotTakenException
     * @throws ApiException       wenn Stripe die Zahlung nicht anlegt
     */
    private function checkout(array $type, array $calendar, array $product, int $start, array $values, string $url, array $keep, string $anchor): string
    {
        $booking = $this->bookings->reserve($type, $calendar, $start, $values, time() + BookingPayment::RESERVATION);
        $when = Notifier::format((int) $booking['start'], (int) $booking['end'], $calendar['timezone']);

        try {
            $checkout = $this->payment->start(
                $booking,
                $type,
                $product,
                $when['date'].', '.$when['time'],
                $url.'?'.http_build_query($keep + ['gebucht' => $booking['token']]).'#'.$anchor,
                $url.'?'.http_build_query($keep + ['abgebrochen' => $booking['token']]).'#'.$anchor,
            );
        } catch (\Throwable $exception) {
            $this->bookings->release($booking);

            throw new ApiException('Stripe: '.$exception->getMessage(), 0, $exception);
        }

        $this->bookings->setCheckout($booking, $checkout['id']);

        return $checkout['url'];
    }

    /**
     * Tag, Monat und Uhrzeit im Kalender vorauswählen.
     */
    private function select(Request $request, int $start, array $calendar): void
    {
        $request->query->set('zeit', (string) $start);
        $request->query->set('tag', (new \DateTimeImmutable('@'.$start))->setTimezone(new \DateTimeZone($calendar['timezone']))->format('Y-m-d'));
        $request->query->set('monat', substr((string) $request->query->get('tag'), 0, 7));
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $values, Request $request, bool $paid): array
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

        if ($paid && !$request->request->get('consent')) {
            $errors['consent'] = $msg['gb_consent_required'];
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

    /**
     * Zustimmung zum Beginn vor Ablauf der Widerrufsfrist (Dienstleistung, § 356 Abs. 4 BGB), mit Link zur
     * Widerrufsbelehrung, wenn eine Seite gewählt ist.
     */
    private function consentLabel(ModuleModel $model): string
    {
        $text = trim((string) $model->gbConsent) ?: $GLOBALS['TL_LANG']['MSC']['gb_consent_default'];

        if ($model->gbWithdrawalPage) {
            $text .= ' '.\sprintf($GLOBALS['TL_LANG']['MSC']['gb_withdrawal_link'], '{{link_url::'.(int) $model->gbWithdrawalPage.'}}');
        }

        return $text;
    }
}
