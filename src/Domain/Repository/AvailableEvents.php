<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemInterface;

interface AvailableEvents
{
    public function ack(StreamId $stream, Checkpoint $allStreamCheckpoint): void;

    public function add(ApplicationId $applicationId, array $event): void;

    public function fetchOne(WorkerId $workerId, ApplicationId $applicationId): ?array;

    public function remove(CacheItemInterface $availableEvents, array $event, int $index): void;

    public function count(ApplicationId $applicationId): int;

    public function list(ApplicationId $applicationId): iterable;

    public function removeAll(ApplicationId $applicationId): void;

    public function declareWorker(WorkerId $workerId, ApplicationId $applicationId): void;

    public function detachWorker(WorkerId $workerId);
}
