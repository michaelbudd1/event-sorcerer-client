<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;

interface SendEvent
{
    public function requestCatchupFor(StreamId $streamId, Checkpoint $checkpoint): void;
}
