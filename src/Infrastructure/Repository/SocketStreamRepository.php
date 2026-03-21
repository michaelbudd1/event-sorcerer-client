<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Stream;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamRepository;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Client;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventName;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventVersion;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

final readonly class SocketStreamRepository implements StreamRepository
{
    public function __construct(private Client $eventSourcererClient) {}

    public function get(StreamId $id, Checkpoint $checkpoint): iterable
    {
        return $this->eventSourcererClient->readStream($id);
    }

    public function save(Stream $aggregate): void
    {
        $nextVersion = $aggregate->nextVersion - count($aggregate->events);

        foreach ($aggregate->events as $event) {
            $nextVersion++;

            $payload = $event;
            unset($payload['event']);

            $this->eventSourcererClient->writeNewEvent(
                $aggregate->id,
                EventName::fromString($event['event']),
                EventVersion::fromInt($event['version']),
                $payload,
                $nextVersion,
            );
        }
    }
}
