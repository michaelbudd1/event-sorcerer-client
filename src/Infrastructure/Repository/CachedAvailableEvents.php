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

        $availableEvents[$event['allSequence']] = $event;

        $availableEventsCacheItem->set($availableEvents);

        $this->cache->save($availableEventsCacheItem);
    }

    public function fetchOne(ApplicationId $applicationId): ?array
    {
        $availableEventsCache = $this->availableMessages($applicationId);

        $availableEvents = $availableEventsCache->get() ?? [];

        foreach ($availableEvents as $event) {
            $this->remove($availableEventsCache, $event['allSequence']);

            return $event;
        }

        return null;
    }

    private function availableMessages(ApplicationId $applicationId): CacheItemInterface
    {
        return $this
            ->cache
            ->getItem(Utils::availableMessagesCacheKey($applicationId));
    }

    public function remove(CacheItemInterface $availableEvents, int $allSequenceIndex): void
    {
        $events = $availableEvents->get() ?? [];

        $events = array_splice($events, $allSequenceIndex, 1);

        $availableEvents->set($events);

        $this->cache->save($availableEvents);
    }
}
