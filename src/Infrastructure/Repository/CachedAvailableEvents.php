<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\Utils;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedAvailableEvents implements AvailableEvents
{
    public function __construct(private CacheItemPoolInterface $cache) {}

    public function add(ApplicationId $applicationId, array $event): void
    {
        $availableEventsCacheItem = $this
            ->cache
            ->getItem(Utils::availableMessagesCacheKey($applicationId));

        $availableEvents = $availableEventsCacheItem->get() ?? [];

        $availableEvents[self::uniqueKey($event['allSequence'])] = $event;

        $availableEventsCacheItem->set($availableEvents);

        $this->cache->save($availableEventsCacheItem);
    }

    public function fetchOne(ApplicationId $applicationId): ?array
    {
        $availableEventsCacheItem = $this->availableMessages($applicationId);
        $availableEvents = $availableEventsCacheItem->get();

        foreach ($availableEvents as $availableEvent) {
            $availableEventsCacheItem->set($availableEvents);

            $this->cache->save($availableEventsCacheItem);

            return $availableEvent;
        }

        return null;
    }

    private static function uniqueKey(int $allSequence): string
    {
        return sprintf('event-%d', $allSequence);
    }

    private function availableMessages(ApplicationId $applicationId): CacheItemInterface
    {
        return $this
            ->cache
            ->getItem(Utils::availableMessagesCacheKey($applicationId));
    }

    public function remove(ApplicationId $applicationId, int $allSequenceIndex): void
    {
        $itemsCache = $this->availableMessages($applicationId);
        $events = $itemsCache->get();

        unset($events[$allSequenceIndex]);

        $itemsCache->set($events);

        $this->cache->save($itemsCache);
    }
}
