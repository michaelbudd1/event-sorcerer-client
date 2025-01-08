<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\RealTimeRepository;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\CatchupHandler;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;

final readonly class SocketRepository implements RealTimeRepository
{
    private const string CATCHUP_REQUEST_PATTERN = 'CATCHUP %s %d';

    public function __construct(private ConnectorInterface $connector, private string $uri) {}

    public function catchup(callable $eventHandler, Checkpoint $checkpoint, StreamId $stream): void
    {
        $catchupHandler = CatchupHandler::create($checkpoint);

        $this
            ->connector
            ->connect($this->uri)
            ->then(function (ConnectionInterface $connection) use ($catchupHandler, $eventHandler, $stream, $checkpoint) {
                $connection->write(self::catchupRequest($stream, $checkpoint));
                $connection->on('data',  $catchupHandler->handleReceivedEvent($eventHandler));
            });
    }

    private static function catchupRequest(StreamId $stream, Checkpoint $checkpoint): string
    {
        return sprintf(self::CATCHUP_REQUEST_PATTERN, $stream, $checkpoint->toInt());
    }
}
