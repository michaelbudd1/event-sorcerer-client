<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\SharedProcessCommunication;
use Psr\Cache\CacheItemInterface;
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

    public function eventsBeingProcessedCurrently(): array
    {
        return $this->currentlyBeingProcessedCacheItem()->get() ?? [];
    }

    public function addEventCurrentlyBeingProcessed(int $allStreamCheckpoint): void
    {
        $currentlyBeingProcessedCacheItem = $this->currentlyBeingProcessedCacheItem();

        $currentlyBeingProcessed = $currentlyBeingProcessedCacheItem->get() ?? [];

        $currentlyBeingProcessed[$allStreamCheckpoint] = $allStreamCheckpoint;

        $currentlyBeingProcessedCacheItem->set($currentlyBeingProcessed);

        $this->cacheItemPool->save($currentlyBeingProcessedCacheItem);
    }

    public function removeEventCurrentlyBeingProcessed(int $allStreamCheckpoint): void
    {
        $currentlyBeingProcessedCacheItem = $this->currentlyBeingProcessedCacheItem();

        $currentlyBeingProcessed = $currentlyBeingProcessedCacheItem->get() ?? [];

        $currentlyBeingProcessed = array_filter(
            $currentlyBeingProcessed,
            fn (int $checkpoint) => $checkpoint !== $allStreamCheckpoint
        );

        $currentlyBeingProcessedCacheItem->set($currentlyBeingProcessed);

        $this->cacheItemPool->save($currentlyBeingProcessedCacheItem);
    }

    private function currentlyBeingProcessedCacheItem(): CacheItemInterface
    {
        return $this->cacheItemPool->getItem(SharedProcessCommunicationItem::EventsBeingProcessedCurrently->value);
    }

    public function messageIsAlreadyBeingProcessed(int $allStreamCheckpoint): bool
    {
        $beingProcessed = $this->currentlyBeingProcessedCacheItem()->get() ?? [];

        return isset($beingProcessed[$allStreamCheckpoint]);
    }

    public function removeAll(): void
    {
        $cacheItem = $this->cacheItemPool->getItem(SharedProcessCommunicationItem::EventsBeingProcessedCurrently->value);

        $cacheItem->set([]);

        $this->cacheItemPool->save($cacheItem);
    }
}
