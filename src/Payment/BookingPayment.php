<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Payment;

use Doctrine\DBAL\Connection;
use Gratlinie\GratShopBundle\Order\CheckoutService;
use Gratlinie\GratShopBundle\Order\FulfillmentService;
use Gratlinie\GratShopBundle\Stripe\Stripe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Bezahlte Terminarten über GratShop (Stripe Checkout; Karte, PayPal usw. je nach Stripe-Dashboard). Nur
 * registriert, wenn GratShop installiert ist. Der Preis kommt aus dem Produkt (Typ „Direktkauf“) der Terminart.
 */
class BookingPayment
{
    /**
     * So lange ist ein Termin während der Zahlung reserviert (Sekunden). Die Checkout-Session endet eine Minute
     * früher; Stripe verlangt mindestens 30 Minuten.
     */
    public const RESERVATION = 1920;

    public function __construct(
        private readonly Connection $db,
        private readonly CheckoutService $checkout,
        private readonly FulfillmentService $fulfillment,
        private readonly Stripe $stripe,
        #[Autowire('%gratshop.vat_rate%')]
        private readonly float $vatRate,
    ) {
    }

    /**
     * Das Produkt einer bezahlten Terminart, sonst null (kostenlos).
     */
    public function product(array $type): array|null
    {
        if (!(int) ($type['shopProduct'] ?? 0)) {
            return null;
        }

        return $this->db->fetchAssociative("SELECT * FROM tl_gs_product WHERE id = ? AND type = 'checkout' AND priceNet > 0", [$type['shopProduct']]) ?: null;
    }

    /**
     * Bruttopreis für Verbraucher, z. B. „2.499,00 €“.
     */
    public function grossPrice(array $product): string
    {
        return self::money((float) $product['priceNet'] * (1 + $this->vatRate / 100));
    }

    public static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }

    /**
     * Startet die Zahlung für eine Reservierung.
     *
     * @return array{url: string, id: string} Adresse der Stripe-Zahlungsseite und ID der Checkout-Session
     */
    public function start(array $booking, array $type, array $product, string $label, string $successUrl, string $cancelUrl): array
    {
        $session = $this->checkout->create($product, [
            'customer' => 'consumer',
            'firstname' => $booking['firstname'],
            'lastname' => $booking['lastname'],
            'company' => '',
            'email' => $booking['email'],
            'gb_booking' => $booking['token'],
            'gb_expires' => (int) $booking['expiresAt'],
            'gb_label' => $type['title'].', '.$label,
        ], $successUrl, $cancelUrl);

        return ['url' => (string) $session->url, 'id' => (string) $session->id];
    }

    /**
     * Rückkehr von Stripe: ist die Zahlung durch, die Bestellung gleich anlegen (sonst erledigt das der Webhook).
     * GratShop legt jede Session nur einmal an, die Buchung bestätigt PaymentListener.
     */
    public function complete(array $booking): void
    {
        if ('' === (string) $booking['shopCheckout']) {
            return;
        }

        $session = $this->stripe->client()->checkout->sessions->retrieve($booking['shopCheckout'])->toArray();

        if ('paid' === ($session['payment_status'] ?? '') && $booking['token'] === ($session['metadata']['gb_booking'] ?? '')) {
            $this->fulfillment->fulfill($session);
        }
    }

    /**
     * Zahlung abgebrochen: die Stripe-Session schließen, damit niemand mehr für den freigegebenen Termin zahlt.
     *
     * @return bool false, wenn die Session schon bezahlt ist (dann die Reservierung nicht freigeben)
     */
    public function abort(array $booking): bool
    {
        if ('' === (string) $booking['shopCheckout']) {
            return true;
        }

        $session = $this->stripe->client()->checkout->sessions->retrieve($booking['shopCheckout']);

        if ('complete' === $session->status) {
            return false;
        }

        if ('open' === $session->status) {
            $this->stripe->client()->checkout->sessions->expire($session->id);
        }

        return true;
    }
}
