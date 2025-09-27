<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEventsLocker;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\StreamLocker;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\Utils;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class LockedAvailableEvents implements AvailableEvents
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private StreamLocker $streamLocker,
        private AvailableEventsLocker $availableEventsLocker
    ) {}

    public function add(ApplicationId $applicationId, array $event): void
    {
        $this->availableEventsLocker->lock();

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

        $this->availableEventsLocker->release();
    }

    public function fetchOne(ApplicationId $applicationId): ?array
    {
        $this->availableEventsLocker->lock();

        $availableEventsCache = $this->availableMessages($applicationId);

        $availableEvents = $availableEventsCache->get() ?? [];

        foreach ($availableEvents as $key => $event) {
            $streamId = StreamId::fromString($event['stream']);

            if (!$this->streamLocker->lock($streamId)) {
                continue;
            }

            $this->remove($availableEventsCache, $event, $key);

            $this->availableEventsLocker->release();

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
        $this->availableEventsLocker->lock();

        $events = $availableEvents->get() ?? [];

        unset($events[$index]);

        $availableEvents->set($events);

        $this->cache->save($availableEvents);

        $this->availableEventsLocker->release();
    }

    public function count(ApplicationId $applicationId): int
    {
        $this->availableEventsLocker->lock();

        $count = count($this->availableMessages($applicationId)->get() ?? []);

        $this->availableEventsLocker->release();

        return $count;
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
