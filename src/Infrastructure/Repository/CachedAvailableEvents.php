<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamLocker;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\Utils;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
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

        if (!empty($availableEvents) && self::firstCheckpointAvailable($availableEvents) > $event['allSequence']) {
            return;
        }

        $availableEvents[$event['allSequence']] = $event;

        $availableEventsCacheItem->set($availableEvents);

        $this->cache->save($availableEventsCacheItem);
    }

    public function fetchOne(ApplicationId $applicationId): ?array
    {
        $availableEventsCache = $this->availableMessages($applicationId);

        $availableEvents = $availableEventsCache->get() ?? [];

        foreach ($availableEvents as $key => $event) {
            $streamId = StreamId::fromString($event['stream']);

            if (!$this->streamLocker->lock($streamId)) {
                continue;
            }

            $this->remove($availableEventsCache, $event, $key);

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

    public function remove(CacheItemInterface $availableEvents, array $event, int $index): void
    {
        $events = $availableEvents->get() ?? [];

        unset($events[$index]);

        $availableEvents->set($events);

        $this->cache->save($availableEvents);
    }

    public function count(ApplicationId $applicationId): int
    {
        return count($this->availableMessages($applicationId)->get() ?? []);
    }

    private static function firstCheckpointAvailable(array $availableEvents): int
    {
        if (empty($availableEvents)) {
            return 0;
        }

        return min(array_keys($availableEvents));
    }

    public function ack(StreamId $stream, Checkpoint $allStreamCheckpoint): void
    {
        $this->streamLocker->release($stream);
    }
}
