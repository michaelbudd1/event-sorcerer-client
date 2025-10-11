<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\StreamWorkerManager;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SharedCacheStreamWorkerManager implements StreamWorkerManager
{
    public function __construct(
        private CacheItemPoolInterface $workers
    ) {}

    public function workerForStreamId(StreamId $streamId): WorkerId
    {

    }

    private function reconfigure(array $bucketIndexes): void
    {
        // a new worker has connected so now we need to reassign workers to buckets

        var_dump($bucketIndexes);

        exit('we need to reconfigure!');
    }

    public function declareWorker(WorkerId $workerId, array $bucketIndexes): void
    {
        $cacheItem = $this->workers->getItem($workerId->toString());

        $isNewWorker = null === $cacheItem->get();

        $cacheItem->set(true);

        $this->workers->save($cacheItem);

        if ($isNewWorker) {
            $this->reconfigure($bucketIndexes);
        }
    }
}
