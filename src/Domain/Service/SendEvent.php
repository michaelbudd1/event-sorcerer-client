<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;


use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface SendEvent
{
    public function requestCatchupFor(StreamId $streamId, Checkpoint $checkpoint): void;
}
