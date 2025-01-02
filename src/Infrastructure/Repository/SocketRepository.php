<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\RealTimeRepository;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;

final readonly class SocketRepository implements RealTimeRepository
{
    private const string CATCHUP_REQUEST_PATTERN = 'CATCHUP %s %d';

    public function __construct(
        private ConnectorInterface $connector,
        private string $uri
    ) {}

    public function catchup(callable $eventHandler, Checkpoint $checkpoint, StreamId $stream): void
    {
        $this
            ->connector
            ->connect($this->uri)
            ->then(static function (ConnectionInterface $connection) use ($eventHandler, $stream, $checkpoint) {
                $connection->write(self::catchupRequest($stream, $checkpoint));
                $connection->on('data', function ($data) use ($eventHandler, $checkpoint) {
                    $events = array_filter(explode(PHP_EOL, $data));

                    foreach ($events as $event) {
                        $checkpoint = $checkpoint->increment();
                        $decoded    = json_decode($event, true);

//                        if ($decoded['number'] > $position) {
                            // a new event must have come in since running catchup request
                            // @todo cache the event until caught up!
//                        }

                        $eventHandler($decoded);
                    }
                });
            });
    }

    private static function catchupRequest(StreamId $stream, Checkpoint $checkpoint): string
    {
        return sprintf(self::CATCHUP_REQUEST_PATTERN, $stream, $checkpoint->toInt());
    }
}
