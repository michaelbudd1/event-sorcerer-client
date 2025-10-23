<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamBuckets;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamWorkerManager;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemInterface;

final readonly class BucketedAvailableEvents implements AvailableEvents
{
    public function __construct(
        private StreamBuckets $streamBuckets,
        private StreamWorkerManager $streamWorkerManager
    ) {}

    public function add(ApplicationId $applicationId, array $event): void
    {
        $streamId = StreamId::fromString($event['stream']);

        $bucketIndex = $this->streamBuckets->bucketIndexForStreamId($streamId)
            ?? $this->streamBuckets->assignBucketIndexForStreamId($streamId);

        $this->streamBuckets->addStreamToBucket($bucketIndex, $streamId);
        $this->streamBuckets->addEventToBucket($bucketIndex, $event);
    }

    public function fetchOne(WorkerId $workerId, ApplicationId $applicationId): ?array
    {
        $bucketIndexes = $this->streamWorkerManager->bucketsForWorkerId($workerId);

        return $this->streamBuckets->fetchOneEvent($bucketIndexes);
    }

    public function remove(CacheItemInterface $availableEvents, array $event, int $index): void
    {
    }

    public function clear(ApplicationId $applicationId): void
    {
        $this->streamBuckets->clear();
        $this->streamWorkerManager->clear();
    }

    public function count(ApplicationId $applicationId): int
    {
        return $this->streamBuckets->count();
    }

    public function ack(StreamId $stream, Checkpoint $allStreamCheckpoint): void
    {
        // not needed because we remove event from the cache as soon as we fetch it
    }

    public function list(ApplicationId $applicationId): iterable
    {
        yield from $this->streamBuckets->listEvents();
    }

    public function declareWorker(WorkerId $workerId, ApplicationId $applicationId): void
    {
        $this->streamWorkerManager->declareWorker($workerId, $this->streamBuckets->bucketIndexes());
    }

    public function detachWorker(WorkerId $workerId): void
    {
        $this->streamWorkerManager->detachWorker($workerId, $this->streamBuckets->bucketIndexes());
    }

    public function hasWorkersRunning(): bool
    {
        return $this->streamWorkerManager->hasRegisteredWorkers();
    }

    public function hasNoWorkersRunning(): bool
    {
        return !$this->hasWorkersRunning();
    }

    public function summary(ApplicationId $applicationId): array
    {
        $bucketMaps = [];

        foreach ($this->streamWorkerManager->registeredWorkers() as $worker) {
            $bucketMaps[$worker] = $this->streamWorkerManager->bucketsForWorkerId(WorkerId::fromString($worker));
        }

        return [
            'numberOfEventsToProcess'  => $this->count($applicationId),
            'workerBucketDistribution' => $bucketMaps,
        ];
    }
}
