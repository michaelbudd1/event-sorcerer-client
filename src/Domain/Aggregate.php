<?php

declare (strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain;

use PearTreeWeb\MicroManager\Client\Domain\Model\Checkpoint;
use PearTreeWeb\MicroManager\Client\Domain\Model\StreamId;

interface Aggregate
{
    public function createFromStream(StreamId $id, Checkpoint $checkpoint): self;
}
