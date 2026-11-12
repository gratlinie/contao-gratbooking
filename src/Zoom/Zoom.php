<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle\Zoom;

use Gratlinie\GratBookingBundle\ApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Zoom-Meetings über eine Server-to-Server-OAuth-App im Zoom-Konto des Kunden (Account-ID, Client-ID,
 * Client-Secret; Scopes meeting:write:meeting:admin, meeting:update:meeting:admin und meeting:delete:meeting:admin,
 * ältere Apps meeting:write:admin).
 *
 * GRATBOOKING_ZOOM_URL ersetzt die Basis-URL von API und Anmeldung (Tests gegen einen Mock-Server).
 */
class Zoom
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire('%env(default::GRATBOOKING_ZOOM_URL)%')]
        private readonly string|null $baseUrl = null,
    ) {
    }

    public static function isConfigured(array $calendar): bool
    {
        return '' !== $calendar['zoomAccountId'] && '' !== $calendar['zoomClientId'] && '' !== $calendar['zoomClientSecret'];
    }

    /**
     * Anmelden genügt als Test der Zugangsdaten, ohne ein Meeting anzulegen.
     */
    public function testConnection(array $calendar): void
    {
        $this->token($calendar);
    }

    /**
     * @return array{id: string, join_url: string, password: string}
     */
    public function create(array $calendar, string $topic, int $start, int $minutes, string $timezone): array
    {
        $data = $this->request($calendar, 'POST', 'users/me/meetings', ['json' => [
            'topic' => mb_substr($topic, 0, 200),
            'type' => 2,
            'start_time' => (new \DateTimeImmutable('@'.$start))->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d\TH:i:s'),
            'timezone' => $timezone,
            'duration' => $minutes,
            'settings' => ['join_before_host' => false, 'waiting_room' => true],
        ]]);

        return ['id' => (string) $data['id'], 'join_url' => (string) $data['join_url'], 'password' => (string) ($data['password'] ?? '')];
    }

    public function reschedule(array $calendar, string $meetingId, int $start, int $minutes, string $timezone): void
    {
        $this->request($calendar, 'PATCH', 'meetings/'.rawurlencode($meetingId), ['json' => [
            'start_time' => (new \DateTimeImmutable('@'.$start))->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d\TH:i:s'),
            'timezone' => $timezone,
            'duration' => $minutes,
        ]]);
    }

    public function delete(array $calendar, string $meetingId): void
    {
        $this->request($calendar, 'DELETE', 'meetings/'.rawurlencode($meetingId), [], [404]);
    }

    private function request(array $calendar, string $method, string $path, array $options, array $ignore = []): array
    {
        $options['auth_bearer'] = $this->token($calendar);

        try {
            $response = $this->httpClient->request($method, ($this->baseUrl ?: 'https://api.zoom.us/').'v2/'.$path, $options);
            $status = $response->getStatusCode();

            if (\in_array($status, $ignore, true) || 204 === $status) {
                return [];
            }

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new ApiException('Zoom nicht erreichbar: '.$exception->getMessage(), 0, $exception);
        }

        if ($status >= 400) {
            throw new ApiException(\sprintf('Zoom meldet %d: %s', $status, $data['message'] ?? 'unbekannter Fehler'));
        }

        return $data;
    }

    private function token(array $calendar): string
    {
        if (!self::isConfigured($calendar)) {
            throw new ApiException('Zoom ist im Kalender nicht eingerichtet.');
        }

        return $this->cache->get('gratbooking_zoom_'.hash('sha256', $calendar['zoomAccountId'].$calendar['zoomClientId'].$calendar['zoomClientSecret']), function (ItemInterface $item) use ($calendar): string {
            try {
                $response = $this->httpClient->request('POST', ($this->baseUrl ?: 'https://zoom.us/').'oauth/token', [
                    'auth_basic' => [$calendar['zoomClientId'], $calendar['zoomClientSecret']],
                    'body' => ['grant_type' => 'account_credentials', 'account_id' => $calendar['zoomAccountId']],
                ]);
                $data = $response->toArray(false);
            } catch (ExceptionInterface $exception) {
                throw new ApiException('Zoom-Anmeldung nicht erreichbar: '.$exception->getMessage(), 0, $exception);
            }

            if (empty($data['access_token'])) {
                throw new ApiException('Zoom-Anmeldung abgelehnt: '.($data['reason'] ?? $data['error'] ?? 'unbekannter Fehler'));
            }

            $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 3600) - 300));

            return (string) $data['access_token'];
        });
    }
}
