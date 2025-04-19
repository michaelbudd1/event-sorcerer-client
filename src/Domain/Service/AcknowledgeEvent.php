<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface AcknowledgeEvent
{
    public function forStreamAndPosition(
        StreamId $streamId,
        Checkpoint $checkpoint,
        ApplicationId $applicationId
    ): void;
}
