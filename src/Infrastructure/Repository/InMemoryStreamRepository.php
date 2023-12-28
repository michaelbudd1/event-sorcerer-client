<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Infrastructure\Repository;

use PearTreeWeb\MicroManager\Client\Domain\Model\Checkpoint;
use PearTreeWeb\MicroManager\Client\Domain\Model\Stream;
use PearTreeWeb\MicroManager\Client\Domain\Model\StreamId;
use PearTreeWeb\MicroManager\Client\Domain\Repository\StreamRepository;

final class InMemoryStreamRepository implements StreamRepository
{
    public function get(StreamId $id, Checkpoint $checkpoint): Stream
    {
        // TODO: Implement get() method.
    }

    public function save(Stream $aggregate): void
    {
        // TODO: Implement save() method.
    }
}
