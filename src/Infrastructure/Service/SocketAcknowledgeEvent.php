<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Service\AcknowledgeEvent;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use React\Socket\ConnectionInterface;

/**
 * @todo is this used???
 */
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
