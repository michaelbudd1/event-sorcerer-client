<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\RealTimeRepository;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\CreateMessage;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;

final readonly class SocketRepository implements RealTimeRepository
{
    public function __construct(private ConnectorInterface $connector, private string $eventSourcererHost) {}

    public function requestCatchup(
        ApplicationId $applicationId,
        StreamId $stream,
        ?Checkpoint $checkpoint = null
    ): void {
        $this
            ->connector
            ->connect($this->eventSourcererHost)
            ->then(function (ConnectionInterface $connection) use ($stream, $applicationId, $checkpoint) {
                $connection->write(CreateMessage::forCatchupRequest($stream, $applicationId, $checkpoint)->toString());
            });
    }
}
