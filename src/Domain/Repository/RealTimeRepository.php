<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;

interface RealTimeRepository
{
    public function catchup(
        callable $eventHandler,
        ApplicationId $applicationId,
        StreamId $stream,
        ?Checkpoint $checkpoint = null
    ): void;
}
