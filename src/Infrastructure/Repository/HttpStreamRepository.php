<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Infrastructure\Repository;

use PearTreeWeb\MicroManager\Client\Domain\Model\Checkpoint;
use PearTreeWeb\MicroManager\Client\Domain\Model\Stream;
use PearTreeWeb\MicroManager\Client\Domain\Model\StreamId;
use PearTreeWeb\MicroManager\Client\Domain\Repository\StreamRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpStreamRepository implements StreamRepository
{
    public function __construct(private HttpClientInterface $httpClient) {}

    public function get(StreamId $id, Checkpoint $checkpoint): Stream
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('http://127.0.0.1:8000/%s/stream', $id)
        );

        $events = json_decode($response->getContent(), true)['events'];

        foreach ($events as &$event) {
            $event['properties'] = self::keyPropertiesByName($event['properties']);
        }

        return new Stream($id, $events);
    }

    public function save(Stream $aggregate): void
    {
        // TODO: Implement save() method.
    }

    private static function keyPropertiesByName(array $events): array
    {
        return collect($events)
            ->keyBy('name')
            ->all();
    }
}
