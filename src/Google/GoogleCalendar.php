<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Google;

use Gratlinie\GratBookingBundle\ApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google Calendar API mit einem Dienstkonto: Der Kunde legt im Cloud-Projekt ein Dienstkonto an, lädt dessen
 * JSON-Schlüssel ins Backend und gibt seinen Kalender für die Adresse des Dienstkontos frei („Änderungen an
 * Terminen vornehmen“). Das Zugriffstoken (1 Stunde) entsteht aus einem selbst signierten JWT und wird
 * zwischengespeichert. Einladungen an Gäste verschickt ein Dienstkonto ohne Workspace nicht – brauchen wir nicht,
 * die Bestätigung samt .ics kommt aus dem Notification Center.
 *
 * GRATBOOKING_GOOGLE_URL ersetzt die Basis-URL der API (Tests gegen einen Mock-Server).
 */
class GoogleCalendar
{
    private const BASE = 'https://www.googleapis.com/calendar/v3/';

    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire('%env(default::GRATBOOKING_GOOGLE_URL)%')]
        private readonly string|null $baseUrl = null,
    ) {
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri: string}
     */
    public static function parseCredentials(string|null $json): array
    {
        $data = json_decode((string) $json, true);

        if (!\is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new ApiException('Der Google-Schlüssel ist kein gültiger JSON-Schlüssel eines Dienstkontos.');
        }

        return [
            'client_email' => (string) $data['client_email'],
            'private_key' => (string) $data['private_key'],
            'token_uri' => (string) ($data['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
        ];
    }

    /**
     * Belegte Zeiten aller Kalender als Unix-Zeitstempel.
     *
     * @param list<string> $calendarIds
     *
     * @return list<array{int, int}>
     */
    public function busy(string $credentials, array $calendarIds, int $from, int $to): array
    {
        $data = $this->request($credentials, 'POST', 'freeBusy', ['json' => [
            'timeMin' => gmdate('Y-m-d\TH:i:s\Z', $from),
            'timeMax' => gmdate('Y-m-d\TH:i:s\Z', $to),
            'items' => array_map(static fn (string $id): array => ['id' => $id], $calendarIds),
        ]]);

        $busy = [];

        foreach ($calendarIds as $id) {
            $calendar = $data['calendars'][$id] ?? null;

            // Nicht freigegebene Kalender meldet Google als Fehler je Kalender, nicht als HTTP-Fehler
            if (null === $calendar || !empty($calendar['errors'])) {
                throw new ApiException(\sprintf('Kalender „%s“ ist nicht lesbar (%s). Ist er für das Dienstkonto freigegeben?', $id, $calendar['errors'][0]['reason'] ?? 'nicht gefunden'));
            }

            foreach ($calendar['busy'] ?? [] as $period) {
                $busy[] = [(int) strtotime($period['start']), (int) strtotime($period['end'])];
            }
        }

        return $busy;
    }

    /**
     * @param array<string, mixed> $event Felder nach https://developers.google.com/calendar/api/v3/reference/events
     */
    public function insertEvent(string $credentials, string $calendarId, array $event): string
    {
        return (string) $this->request($credentials, 'POST', 'calendars/'.rawurlencode($calendarId).'/events', ['json' => $event])['id'];
    }

    public function updateEvent(string $credentials, string $calendarId, string $eventId, array $event): void
    {
        $this->request($credentials, 'PATCH', 'calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId), ['json' => $event]);
    }

    public function deleteEvent(string $credentials, string $calendarId, string $eventId): void
    {
        $this->request($credentials, 'DELETE', 'calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId), [], [404, 410]);
    }

    /**
     * @param list<int> $ignore Statuscodes, die kein Fehler sind (z. B. schon gelöscht)
     */
    private function request(string $credentials, string $method, string $path, array $options, array $ignore = []): array
    {
        $options['auth_bearer'] = $this->token($credentials);

        try {
            $response = $this->httpClient->request($method, ($this->baseUrl ?: self::BASE).$path, $options);
            $status = $response->getStatusCode();

            if (\in_array($status, $ignore, true) || 204 === $status) {
                return [];
            }

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new ApiException('Google Kalender nicht erreichbar: '.$exception->getMessage(), 0, $exception);
        }

        if ($status >= 400) {
            throw new ApiException(\sprintf('Google Kalender meldet %d: %s', $status, $data['error']['message'] ?? 'unbekannter Fehler'));
        }

        return $data;
    }

    private function token(string $credentials): string
    {
        $account = self::parseCredentials($credentials);

        return $this->cache->get('gratbooking_google_'.hash('sha256', $account['client_email'].$account['private_key']), function (ItemInterface $item) use ($account): string {
            $now = time();
            $segments = [
                self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                self::base64Url(json_encode(['iss' => $account['client_email'], 'scope' => self::SCOPE, 'aud' => $account['token_uri'], 'iat' => $now, 'exp' => $now + 3600])),
            ];

            if (!openssl_sign(implode('.', $segments), $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new ApiException('Der private Schlüssel des Dienstkontos lässt sich nicht verwenden.');
            }

            $segments[] = self::base64Url($signature);

            try {
                $response = $this->httpClient->request('POST', $account['token_uri'], ['body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => implode('.', $segments),
                ]]);
                $data = $response->toArray(false);
            } catch (ExceptionInterface $exception) {
                throw new ApiException('Google-Anmeldung nicht erreichbar: '.$exception->getMessage(), 0, $exception);
            }

            if (empty($data['access_token'])) {
                throw new ApiException('Google-Anmeldung abgelehnt: '.($data['error_description'] ?? $data['error'] ?? 'unbekannter Fehler'));
            }

            $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 3600) - 300));

            return (string) $data['access_token'];
        });
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
