<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\AcknowledgeEvent;
use React\Socket\ConnectionInterface;

final readonly class SocketAcknowledgeEvent implements AcknowledgeEvent
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forStreamAndPosition(
        StreamId $streamId,
        Checkpoint $checkpoint,
        ApplicationId $applicationId
    ): void {
        // TODO: Implement forStreamAndPosition() method.
    }
}
