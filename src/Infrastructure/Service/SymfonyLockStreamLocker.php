<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamLocker;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;

final readonly class SymfonyLockStreamLocker implements StreamLocker
{
    public function __construct(
        private LockFactory $lockFactory,
        private CacheItemPoolInterface $sharedLockKeys
    ) {}

    public static function create(): self
    {
        $store = new FlockStore();
        $factory = new LockFactory($store);

        return new self($factory, new ArrayAdapter());
    }

    public function lock(StreamId $streamId): bool
    {
        return $this->fetchLock($streamId)->acquire();
    }

    public function release(StreamId $streamId): void
    {
        $this->fetchLock($streamId)->release();
        $this->sharedLockKeys->deleteItem($streamId->toString());
    }

    private function fetchLock(StreamId $streamId): SharedLockInterface
    {
        $cachedLockCacheItem = $this->sharedLockKeys->getItem($streamId->toString());
        $cachedLockKey = $cachedLockCacheItem->get();

        if (null === $cachedLockKey) {
            $cachedLockKey = new Key($streamId->toString());

            $cachedLockCacheItem->set($cachedLockKey);

            $this->sharedLockKeys->save($cachedLockCacheItem);
        }

        return $this->lockFactory->createLockFromKey(
            key: $cachedLockKey,
            autoRelease: false
        );
    }

    public function isLocked(StreamId $streamId): bool
    {
        return $this->fetchLock($streamId)->isAcquired();
    }
}
