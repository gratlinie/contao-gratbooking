<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Payment;

use Doctrine\DBAL\Connection;
use Gratlinie\GratBookingBundle\Booking\BookingService;
use Gratlinie\GratShopBundle\Event\CheckoutSessionEvent;
use Gratlinie\GratShopBundle\Event\OrderPaidEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Verbindet GratShop mit den Buchungen: die Checkout-Session trägt den Token der Reservierung und läuft mit ihr ab,
 * die bezahlte Bestellung bestätigt den Termin.
 */
class PaymentListener
{
    public function __construct(
        private readonly Connection $db,
        private readonly BookingService $bookings,
        private readonly LoggerInterface $contaoErrorLogger,
    ) {
    }

    private const DELAYED = ['sepa_debit', 'bacs_debit', 'au_becs_debit', 'acss_debit', 'us_bank_account', 'customer_balance', 'boleto', 'oxxo', 'konbini', 'multibanco'];

    #[AsEventListener]
    public function onCheckoutSession(CheckoutSessionEvent $event): void
    {
        $token = (string) ($event->values['gb_booking'] ?? '');

        if ('' === $token) {
            return;
        }

        $event->setMetadata('gb_booking', $token);
        // Die Session endet kurz vor der Reservierung, damit nach deren Ablauf niemand mehr zahlen kann
        $event->params['expires_at'] = (int) $event->values['gb_expires'] - 60;
        $event->params['payment_intent_data']['description'] = (string) $event->values['gb_label'];
        // Nur sofortige Zahlarten: eine Lastschrift o. Ä. wird erst nach Tagen bestätigt, wenn die Reservierung längst abgelaufen ist
        $event->params['excluded_payment_method_types'] = self::DELAYED;
    }

    #[AsEventListener]
    public function onOrderPaid(OrderPaidEvent $event): void
    {
        $token = $event->getMetadata('gb_booking');

        if ('' === $token) {
            return;
        }

        $booking = $this->db->fetchAssociative('SELECT * FROM tl_gb_booking WHERE token = ?', [$token]);

        // Ohne Buchung hilft auch eine erneute Zustellung nicht: protokollieren, die Bestellung bleibt bestehen
        if (false === $booking) {
            $this->contaoErrorLogger->error(\sprintf('GratBooking: Bestellung ID %d bezahlt, aber keine Buchung zum Token gefunden.', $event->order['id']));

            return;
        }

        $this->bookings->confirmPayment($booking, (int) $event->order['id'], (float) $event->order['amountTotal']);
    }
}
