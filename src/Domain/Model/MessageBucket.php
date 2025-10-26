<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use Psr\Cache\CacheItemInterface;
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

        $this->incrementNumberOfEventsForStream($event['stream']);
        $this->updateNumberOfStreamsInBucket();
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

        if (null !== $fetchedEvent) {
            $this->decrementNumberOfEventsForStream($fetchedEvent['stream']);
        }

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

    private function updateNumberOfStreamsInBucket(): void
    {
        $numberOfStreams = count($this->uniqueStreamsCacheItem()->get() ?? []);

        $streamCountCacheItem = $this->events->getItem(self::STREAM_COUNT);

        $streamCountCacheItem->set($numberOfStreams);

        $this->events->save($streamCountCacheItem);
    }

    public function eventCount(): int
    {
        $allEventIndexesCacheItem = $this->events->getItem(self::ALL_EVENTS)->get() ?? [];

        return count($allEventIndexesCacheItem);
    }

    public function isEmpty(): bool
    {
        return 0 === $this->eventCount();
    }

    public function clear(): void
    {
        $this->events->clear();
    }

    private function incrementNumberOfEventsForStream(string $stream): void
    {
        $cacheItem = $this->uniqueStreamsCacheItem();

        $uniqueStreams = $cacheItem->get() ?? [];

        $currentNumberOfEvents = $uniqueStreams[$stream] ?? 0;

        $uniqueStreams[$stream] = $currentNumberOfEvents + 1;

        $cacheItem->set($uniqueStreams);

        $this->events->save($cacheItem);
    }

    private function decrementNumberOfEventsForStream(string $stream): void
    {
        $cacheItem = $this->uniqueStreamsCacheItem();

        $uniqueStreams = $cacheItem->get() ?? [];

        $currentNumberOfEvents = $uniqueStreams[$stream] ?? 0;

        $newNumberOfEvents = $currentNumberOfEvents - 1;

        if (0 === $newNumberOfEvents) {
            unset($uniqueStreams[$stream]);
        } else {
            $cacheItem->set($uniqueStreams);
        }

        $this->events->save($cacheItem);
    }

    private function uniqueStreamsCacheItem(): CacheItemInterface
    {
        return $this->events->getItem(self::UNIQUE_STREAMS);
    }

    public function listEvents(): iterable
    {
        $allEventsIndexesCacheItem = $this->events->getItem(self::ALL_EVENTS)->get() ?? [];

        foreach ($allEventsIndexesCacheItem as $allEventIndex) {
            yield $this->events->getItem($allEventIndex)->get();
        }
    }
}
