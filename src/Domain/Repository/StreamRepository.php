<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Repository;

use PearTreeWeb\MicroManager\Client\Domain\Model\Checkpoint;
use PearTreeWeb\MicroManager\Client\Domain\Model\Stream;
use PearTreeWeb\MicroManager\Client\Domain\Model\StreamId;

interface StreamRepository
{
    public function get(StreamId $id, Checkpoint $checkpoint): Stream;

    public function save(Stream $aggregate): void;
}
