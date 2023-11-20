<?php

declare (strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Repository;

use PearTreeWeb\MicroManager\Client\Domain\Model\Checkpoint;
use PearTreeWeb\MicroManager\Client\Domain\Model\StreamId;

interface Events
{
    /**
     * @return array{array{name: string, properties: array{type: string, value: mixed}}
     */
    public function for(StreamId $id, Checkpoint $checkpoint): array;
}
