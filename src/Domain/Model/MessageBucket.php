<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use Psr\Cache\CacheItemPoolInterface;

final readonly class MessageBucket
{
    private const string ALL_EVENTS = 'allEvents';
    private const string STREAM_COUNT = 'streamCount';
    private const string UNIQUE_STREAMS = 'uniqueStreams';
    private const string CACHE_ITEM_PREFIX = 'cacheItem';

    public function __construct(private CacheItemPoolInterface $events) {}

    public function numberOfStreamsWithin(): int
    {
        return $this->events->getItem(self::STREAM_COUNT)->get() ?? 0;
    }

    /**
     * @param array{allSequence: int, stream: string} $event
     */
    public function addEvent(array $event): void
    {
        $cacheItemKey = self::cacheItemKey($event['allSequence']);

        $this->cacheIndividualEvent($event, $cacheItemKey);
        $this->cacheEventIndex($event, $cacheItemKey);

        $this->updateNumberOfStreamsInBucket($event['stream']);
    }

    public function fetchOneEvent(): ?array
    {
        $allEventIndexesCacheItem = $this->events->getItem(self::ALL_EVENTS);
        $allEventIndexes = $allEventIndexesCacheItem->get();

        if (empty($allEventIndexes)) {
            return null;
        }

        $minIndex = min(array_keys($allEventIndexes));
        $eventCacheKey = $allEventIndexes[$minIndex];

        $fetchedEvent = $this->events->getItem($eventCacheKey)->get();

        unset($allEventIndexes[$minIndex]);

        $allEventIndexesCacheItem->set($allEventIndexes);

        $this->events->deleteItem($eventCacheKey);
        $this->events->save($allEventIndexesCacheItem);

        return $fetchedEvent;
    }

    private function cacheEventIndex(array $event, string $cacheItemKey): void
    {
        $cacheItem = $this->events->getItem(self::ALL_EVENTS);

        $eventIndexes = $cacheItem->get() ?? [];

        $eventIndexes[$event['allSequence']] = $cacheItemKey;

        $cacheItem->set($eventIndexes);

        $this->events->save($cacheItem);
    }

    private function cacheIndividualEvent(array $event, string $cacheItemKey): void
    {
        $cacheItem = $this->events->getItem($cacheItemKey);

        $cacheItem->set($event);

        $this->events->save($cacheItem);
    }

    private static function cacheItemKey(int $allSequence): string
    {
        return sprintf('%s-%d', self::CACHE_ITEM_PREFIX, $allSequence);
    }

    private function updateNumberOfStreamsInBucket(string $stream): void
    {
        $uniqueStreamsCacheItem = $this->events->getItem(self::UNIQUE_STREAMS);

        $uniqueStreams = $uniqueStreamsCacheItem->get() ?? [];

        $uniqueStreams[$stream] = $stream;

        $uniqueStreamsCacheItem->set($uniqueStreams);

        $this->events->save($uniqueStreamsCacheItem);

        $numberOfStreams = count($uniqueStreams);

        $streamCountCacheItem = $this->events->getItem(self::STREAM_COUNT);

        $streamCountCacheItem->set($numberOfStreams);

        $this->events->save($streamCountCacheItem);
    }
}
