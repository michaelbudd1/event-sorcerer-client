<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamBuckets;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemInterface;

final readonly class BucketedAvailableEvents implements AvailableEvents
{
    public function __construct(private StreamBuckets $streamBuckets) {}

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
        return $this->streamBuckets->fetchOneEvent($workerId, $applicationId);
    }

    public function remove(CacheItemInterface $availableEvents, array $event, int $index): void
    {

    }

    public function removeAll(ApplicationId $applicationId): void
    {

    }

    public function count(ApplicationId $applicationId): int
    {
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

    private function availableMessages(ApplicationId $applicationId): CacheItemInterface
    {

    }
}
