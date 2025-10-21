<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use ArrayIterator;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamWorkerManager;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SharedCacheStreamWorkerManager implements StreamWorkerManager
{
    private const string WORKERS_CACHE_KEY = 'workers';
    private const string BUCKET_INDEX_CACHE_KEY_PREFIX = 'bucketIndex';

    public function __construct(
        private CacheItemPoolInterface $cacheItemPool,
        private CacheItemPoolInterface $workers
    ) {}

    public function bucketsForWorkerId(WorkerId $workerId): array
    {
        return $this->cacheItemPool->getItem($workerId->toString())->get() ?? [];
    }

    private function reconfigure(array $bucketIndexes): void
    {
        $workerIterator = new \InfiniteIterator(
            new ArrayIterator(
                $this->workersCacheItem()->get() ?? []
            )
        );

        $workerIterator->rewind();

        foreach ($bucketIndexes as $bucketIndex) {
            $workerId = $workerIterator->current();

            if (null === $workerId) {
                // there are no workers
                break;
            }

            $this->mapBucketToWorker($bucketIndex, $workerId);
            $this->mapWorkerToBucket($workerId, $bucketIndex);

            $workerIterator->next();
        }
    }

    public function declareWorker(WorkerId $workerId, array $bucketIndexes): void
    {
        $cacheItem = $this->workersCacheItem();

        $workers = $cacheItem->get() ?? [];

        $isNewWorker = !isset($workers[$workerId->toString()]);

        $workers[$workerId->toString()] = $workerId->toString();

        $cacheItem->set($workers);

        $this->workers->save($cacheItem);

        if ($isNewWorker) {
            $this->reconfigure($bucketIndexes);
        }
    }

    public function detachWorker(WorkerId $workerId, array $bucketIndexes): void
    {
        $this->cacheItemPool->deleteItem($workerId->toString());

        $cacheItem = $this->workersCacheItem();

        $workers = $cacheItem->get() ?? [];

        unset($workers[$workerId->toString()]);

        $cacheItem->set($workers);

        $this->cacheItemPool->save($cacheItem);

        $this->reconfigure($bucketIndexes);
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

        $currentValue = $workerBucketItem->get() ?? [];

        $currentValue[$bucketIndex] = $bucketIndex;

        $workerBucketItem->set($currentValue);

        $this->cacheItemPool->save($workerBucketItem);
    }

    public function hasRegisteredWorkers(): bool
    {
        return !empty($this->workersCacheItem()->get());
    }

    private function workersCacheItem(): CacheItemInterface
    {
        return $this->workers->getItem(self::WORKERS_CACHE_KEY);
    }

    public function clear(): void
    {
        $this->cacheItemPool->clear();
        $this->workers->clear();
    }
}
