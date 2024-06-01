<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Stream;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamName;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamRepository;
use PearTreeWeb\EventSourcerer\Client\Exception\CouldNotStoreEventException;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Config;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class HttpStreamRepository implements StreamRepository
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Config $config
    ) {}

    public function get(StreamId $id, Checkpoint $checkpoint): Stream
    {
        $response = $this->httpClient->request('GET', $this->url($id));

        $stream = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($stream['events'] as &$event) {
            $event['properties'] = self::keyPropertiesByName($event['properties']);
        }

        return new Stream($id, StreamName::fromString($stream['stream']), $stream['events']);
    }

    public function save(Stream $aggregate): void
    {
        foreach ($aggregate->events as $event) {
            try {
                $response = $this->saveEvent($aggregate, $event);

                if (201 !== $response->getStatusCode()) {
                    self::handleError($response->getContent(), $event);
                }
            } catch (TransportExceptionInterface|ClientException $e) {
                self::handleError($e->getResponse()->getContent(), $event);
            }
        }
    }

    public static function handleError(string $error, array $event): void
    {
        throw CouldNotStoreEventException::with($error, $event);
    }

    private static function keyPropertiesByName(array $events): array
    {
        return collect($events)
            ->keyBy('event')
            ->all();
    }

    private function saveEvent(Stream $aggregate, mixed $event): ResponseInterface
    {
        return $this->httpClient->request(
            'POST',
            $this->url(),
            [
                'json' => [
                    'event'      => $event['event'],
                    'properties' => $event,
                    'streamId'   => $aggregate->id->toString(),
                    'streamName' => $aggregate->name->toString(),
                ],
            ]
        );
    }

    private function url(?StreamId $id = null): string
    {
        $appendId = $id
            ? '?streamId=' . $id->toString()
            : '';

        return sprintf(
            '%s:%s/api/stream_events%s',
            $this->config->serverUrl,
            $this->config->serverPort,
            $appendId
        );
    }
}
