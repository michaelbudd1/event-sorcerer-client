<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

interface Client
{
    public function checkpointFor(StreamId $streamId): Checkpoint;
}
