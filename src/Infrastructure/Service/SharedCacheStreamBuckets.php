<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\MessageBucket;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamBuckets;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SharedCacheStreamBuckets implements StreamBuckets
{
    /**
     * @var MessageBucket[]
     */
    private array $buckets;

    public function __construct(
        private CacheItemPoolInterface $bucketForStreamIndexes,
        MessageBucket ...$buckets,
    ) {
        $this->buckets = $buckets;
    }

    public function bucketIndexForStreamId(StreamId $streamId): ?int
    {
        return $this->bucketForStreamIndexes->getItem($streamId->toString())->get();
    }

    public function assignBucketIndexForStreamId(StreamId $streamId): int
    {
        return collect($this->buckets)
            ->sortBy(static fn (MessageBucket $messageBucket) => $messageBucket->numberOfStreamsWithin())
            ->keys()
            ->first();
    }

    public function addEventToBucket(int $index, array $decodedEvent): void
    {
        $this->buckets[$index]->addEvent($decodedEvent);
    }

    public function fetchOneEvent(array $bucketIndexes): ?array
    {
        foreach ($bucketIndexes as $bucketIndex) {
            $event = $this->buckets[$bucketIndex]->fetchOneEvent();

            if (null !== $event) {
                return $event;
            }
        }

        return null;
    }

    public function bucketIndexes(): array
    {
        return collect($this->buckets)->keys()->all();
    }

    public function addStreamToBucket(int $index, StreamId $streamId): void
    {
        $cacheItem = $this->bucketForStreamIndexes->getItem($streamId->toString());

        $cacheItem->set($index);

        $this->bucketForStreamIndexes->save($cacheItem);
    }

    public function count(): int
    {
        return collect($this->buckets)
            ->sum(static fn (MessageBucket $bucket) => $bucket->eventCount());
    }

    public function clear(): void
    {
        $this->bucketForStreamIndexes->clear();

        foreach ($this->buckets as $bucket) {
            $bucket->clear();
        }
    }

    public function listEvents(): iterable
    {
        foreach ($this->buckets as $bucket) {
            yield from $bucket->listEvents();
        }
    }

    public function mappings(): array
    {
        $mappings = [];

        return [];
    }
}
