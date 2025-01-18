<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\RealTimeRepository;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Model\MessagePattern;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\AcknowledgeEvent;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\CatchupHandler;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;

final readonly class SocketRepository implements RealTimeRepository
{
    public function __construct(private ConnectorInterface $connector, private string $uri) {}

    public function catchup(
        callable $eventHandler,
        ApplicationId $applicationId,
        StreamId $stream,
        ?Checkpoint $checkpoint = null
    ): void {
        $this
            ->connector
            ->connect($this->uri)
            ->then(function (ConnectionInterface $connection) use ($eventHandler, $stream, $applicationId, $checkpoint) {
                $connection->write(self::catchupRequest($stream, $applicationId, $checkpoint));
                /** @todo THIS NEEDS REFACTORING */
                $connection->on('data', CatchupHandler::create(new AcknowledgeEvent($connection))->handleReceivedEvent($eventHandler));
            });
    }

    private static function catchupRequest(
        StreamId $stream,
        ApplicationId $applicationId,
        ?Checkpoint $checkpoint
    ): string {
        return sprintf(
            MessagePattern::Catchup->value,
            $stream,
            $applicationId,
            $checkpoint?->toString() ?? ''
        );
    }
}
