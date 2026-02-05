<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Stream;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface StreamRepository
{
    /**
     * @return iterable<array{eventName: string, properties: array<string, mixed>, version: string}>
     */
    public function get(StreamId $id, Checkpoint $checkpoint): iterable;

    public function save(Stream $aggregate): void;
}
