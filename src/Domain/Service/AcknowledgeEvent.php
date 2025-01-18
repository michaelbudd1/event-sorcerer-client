<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;

interface AcknowledgeEvent
{
    public function with(StreamId $streamId, Checkpoint $checkpoint): void;
}
