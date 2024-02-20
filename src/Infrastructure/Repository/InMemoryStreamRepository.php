<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Stream;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamRepository;

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
