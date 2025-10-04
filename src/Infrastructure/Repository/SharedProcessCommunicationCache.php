<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\SharedProcessCommunication;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SharedProcessCommunicationCache implements SharedProcessCommunication
{
    public function __construct(private CacheItemPoolInterface $cacheItemPool) {}

    public function catchupInProgress(): bool
    {
        return $this->cacheItemPool->getItem(SharedProcessCommunicationItem::CatchupInProgress->value)->get() ?? false;
    }

    public function flagCatchupIsInProgress(): void
    {
        $cacheItem = $this->cacheItemPool->getItem(SharedProcessCommunicationItem::CatchupInProgress->value);

        $cacheItem->set(true);

        $this->cacheItemPool->save($cacheItem);
    }

    public function flagCatchupIsNotInProgress(): void
    {
        $cacheItem = $this->cacheItemPool->getItem(SharedProcessCommunicationItem::CatchupInProgress->value);

        $cacheItem->set(false);

        $this->cacheItemPool->save($cacheItem);
    }
}
