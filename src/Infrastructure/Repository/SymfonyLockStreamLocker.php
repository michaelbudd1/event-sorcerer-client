<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamLocker;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;

final readonly class SymfonyLockStreamLocker implements StreamLocker
{
    public function __construct(private LockFactory $lockFactory) {}

    public static function create(): self
    {
        $store = new FlockStore();
        $factory = new LockFactory($store);

        return new self($factory);
    }

    public function lock(StreamId $streamId): bool
    {
        return $this->fetchLock($streamId)->acquire();
    }

    public function release(StreamId $streamId): void
    {
        $this->fetchLock($streamId)->release();
    }

    public function isLocked(StreamId $streamId): bool
    {
        return $this->fetchLock($streamId)->isAcquired();
    }

    private function fetchLock(StreamId $streamId): SharedLockInterface
    {
        return $this->lockFactory->createLock($streamId->toString());
    }
}
