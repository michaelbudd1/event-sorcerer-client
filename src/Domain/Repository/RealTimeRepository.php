<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface RealTimeRepository
{
    public function requestCatchup(
        ApplicationId $applicationId,
        StreamId $stream,
        ?Checkpoint $checkpoint = null
    ): void;
}
