<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEventsLocker;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;

final readonly class SymfonyLockAvailableEventsLocker implements AvailableEventsLocker
{
    private const string LOCK_KEY = 'availableEvents';

    public function __construct(private LockFactory $lockFactory) {}

    public static function create(): self
    {
        $store = new FlockStore();
        $factory = new LockFactory($store);

        return new self($factory);
    }

    public function lock(): bool
    {
        return $this->fetchLock()->acquire(true);
    }

    public function release(): void
    {
        $this->fetchLock()->release();
    }

    private function fetchLock(): SharedLockInterface
    {
        return $this->lockFactory->createLock(self::LOCK_KEY);
    }
}
