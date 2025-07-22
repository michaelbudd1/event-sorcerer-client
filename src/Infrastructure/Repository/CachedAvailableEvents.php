<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedAvailableEvents implements AvailableEvents
{
    public function __construct(private CacheItemPoolInterface $availableEvents) {}

    public function add(array $event): void
    {
        $this
            ->availableEvents
            ->getItem(self::uniqueKey($event['allSequence']))
            ->set($event);
    }

    public function fetchOne(): ?array
    {
        foreach ($this->availableEvents->getItems() as $cacheItem) {
            /** @var CacheItemInterface $cacheItem */
            $event = $cacheItem->get();

            $this->availableEvents->deleteItem(self::uniqueKey($event['allSequence']));

            return $event;
        }

        return null;
    }

    public function remove(int $index): void
    {
        $this
            ->availableEvents
            ->deleteItem(self::uniqueKey($index));
    }

    private static function uniqueKey(int $allSequence): string
    {
        return sprintf('event-%d', $allSequence);
    }
}
