<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;

interface AcknowledgeEvent
{
    public function forStreamAndPosition(
        StreamId $streamId,
        Checkpoint $checkpoint,
        ApplicationId $applicationId
    ): void;
}
