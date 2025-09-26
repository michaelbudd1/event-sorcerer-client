<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemInterface;

interface AvailableEvents
{
    public function ack(StreamId $stream, Checkpoint $allStreamCheckpoint): void;

    public function add(ApplicationId $applicationId, array $event): void;

    public function fetchOne(ApplicationId $applicationId): ?array;

    public function remove(CacheItemInterface $availableEvents, array $event, int $index): void;

    public function count(ApplicationId $applicationId): int;
}
