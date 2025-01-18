<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\AcknowledgeEvent as AcknowledgeEventInterface;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Model\MessagePattern;
use React\Socket\ConnectionInterface;

final readonly class AcknowledgeEvent implements AcknowledgeEventInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function with(StreamId $streamId, Checkpoint $checkpoint): void
    {
        $this->connection->write(
            sprintf(
                MessagePattern::Acknowledge->value,
                $streamId,
                $checkpoint->toInt()
            )
        );
    }
}
