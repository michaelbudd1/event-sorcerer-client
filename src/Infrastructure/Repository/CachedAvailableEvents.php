<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamLocker;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\Utils;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedAvailableEvents implements AvailableEvents
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private StreamLocker $streamLocker
    ) {}

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
            $streamId = StreamId::fromString($event['stream']);

            if ($this->streamLocker->isLocked($streamId)) {
                continue;
            }

            $this->streamLocker->lock($streamId);

            $this->remove($availableEventsCache, $event);

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

    public function remove(CacheItemInterface $availableEvents, array $event): void
    {
        $events = $availableEvents->get() ?? [];

        $events[$event['allSequence']] = null;

        $availableEvents->set(\array_filter($events));

        $this->cache->save($availableEvents);

        $this->streamLocker->release(StreamId::fromString($event['stream']));
    }

    public function count(ApplicationId $applicationId): int
    {
        return count($this->availableMessages($applicationId)->get() ?? []);
    }
}
