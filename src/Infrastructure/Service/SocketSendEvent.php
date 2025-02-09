<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\SendEvent as AcknowledgeEventInterface;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessagePattern;
use React\Socket\ConnectionInterface;

final readonly class SocketSendEvent implements AcknowledgeEventInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function requestCatchupFor(StreamId $streamId, Checkpoint $checkpoint): void
    {
        $this->connection->write(
            sprintf(
                MessagePattern::Catchup->value,
                $streamId,
                $checkpoint->toInt()
            )
        );
    }
}
