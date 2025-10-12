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
        $bucketIndex = $this->streamWorkerManager->bucketForWorkerId($workerId);

        return $this->streamBuckets->fetchOneEvent($bucketIndex);
    }

    public function remove(CacheItemInterface $availableEvents, array $event, int $index): void
    {

    }

    public function removeAll(ApplicationId $applicationId): void
    {

    }

    public function count(ApplicationId $applicationId): int
    {
        return $this->streamBuckets->count();
    }

    public function ack(StreamId $stream, Checkpoint $allStreamCheckpoint): void
    {
    }

    public function list(ApplicationId $applicationId): iterable
    {

    }

    private static function firstCheckpointAvailable(array $availableEvents): int
    {

    }

    public function declareWorker(WorkerId $workerId, ApplicationId $applicationId): void
    {
        $this->streamWorkerManager->declareWorker($workerId, $this->streamBuckets->bucketIndexes());
    }
}
