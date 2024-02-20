<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Stream;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;

interface StreamRepository
{
    public function get(StreamId $id, Checkpoint $checkpoint): Stream;

    public function save(Stream $aggregate): void;
}
