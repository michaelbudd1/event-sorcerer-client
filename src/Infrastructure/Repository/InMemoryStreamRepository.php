<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Stream;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamName;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamRepository;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

final class InMemoryStreamRepository implements StreamRepository
{
    public function get(StreamName $name, StreamId $id, Checkpoint $checkpoint): Stream
    {
        // TODO: Implement get() method.
    }

    public function save(Stream $aggregate): void
    {
        // TODO: Implement save() method.
    }
}
