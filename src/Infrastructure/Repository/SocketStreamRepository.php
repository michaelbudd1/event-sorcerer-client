<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Infrastructure\Client;
use PearTreeWeb\EventSourcerer\Common\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Common\Model\EventName;
use PearTreeWeb\EventSourcerer\Common\Model\EventVersion;
use PearTreeWeb\EventSourcerer\Common\Model\Stream;
use PearTreeWeb\EventSourcerer\Common\Model\StreamId;
use PearTreeWeb\EventSourcerer\Common\Repository\StreamRepository;

final readonly class SocketStreamRepository implements StreamRepository
{
    public function __construct(private Client $eventSourcererClient) {}

    public function get(StreamId $id, Checkpoint $checkpoint): iterable
    {
        return $this->eventSourcererClient->readStream($id);
    }

    public function save(Stream $aggregate): void
    {
        if (empty($aggregate->events)) {
            return;
        }

        $nextVersion = $aggregate->nextVersion - count($aggregate->events);
        $socket      = $this->eventSourcererClient->openSocket();

        try {
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
                    $socket,
                );
            }
        } finally {
            fclose($socket);
        }
    }
}
