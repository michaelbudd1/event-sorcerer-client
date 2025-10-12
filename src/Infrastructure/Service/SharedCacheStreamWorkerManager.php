<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use ArrayIterator;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamWorkerManager;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SharedCacheStreamWorkerManager implements StreamWorkerManager
{
    private const string WORKERS_CACHE_KEY = 'workers';
    private const string BUCKET_INDEX_CACHE_KEY_PREFIX = 'bucketIndex';

    public function __construct(
        private CacheItemPoolInterface $cacheItemPool
    ) {}

    public function workerForStreamId(StreamId $streamId): WorkerId
    {
//        return $this->cacheItemPool->getItem()
    }

    public function bucketForWorkerId(WorkerId $workerId): int
    {
        return $this->cacheItemPool->getItem($workerId->toString())->get();
    }

    private function reconfigure(array $bucketIndexes): void
    {
        // a new worker has connected so now we need to reassign workers to buckets

        $workerIterator = new \InfiniteIterator(
            new ArrayIterator(
                $this->cacheItemPool->getItem(self::WORKERS_CACHE_KEY)->get() ?? []
            )
        );

        $workerIterator->rewind();

        foreach ($bucketIndexes as $bucketIndex) {
            $workerId = $workerIterator->current();

            $this->mapBucketToWorker($bucketIndex, $workerId);
            $this->mapWorkerToBucket($workerId, $bucketIndex);

            $workerIterator->next();
        }
    }

    public function declareWorker(WorkerId $workerId, array $bucketIndexes): void
    {
        $cacheItem = $this->cacheItemPool->getItem(self::WORKERS_CACHE_KEY);

        $workers = $cacheItem->get() ?? [];

        $isNewWorker = !isset($workers[$workerId->toString()]);

        $workers[$workerId->toString()] = $workerId->toString();

        $cacheItem->set($workers);

        $this->cacheItemPool->save($cacheItem);

        if ($isNewWorker) {
            $this->reconfigure($bucketIndexes);
        }
    }

    private static function bucketIndexCacheKey(mixed $bucketIndex): string
    {
        return sprintf('%s-%d', self::BUCKET_INDEX_CACHE_KEY_PREFIX, $bucketIndex);
    }

    private function mapBucketToWorker(int $bucketIndex, string $workerId): void
    {
        $bucketWorkerItem = $this->cacheItemPool->getItem(self::bucketIndexCacheKey($bucketIndex));

        $bucketWorkerItem->set($workerId);

        $this->cacheItemPool->save($bucketWorkerItem);
    }

    private function mapWorkerToBucket(string $workerId, int $bucketIndex): void
    {
        $workerBucketItem = $this->cacheItemPool->getItem($workerId);

        $workerBucketItem->set($bucketIndex);

        $this->cacheItemPool->save($workerBucketItem);
    }
}
