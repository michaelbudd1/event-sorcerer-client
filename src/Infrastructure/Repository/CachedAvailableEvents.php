<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedAvailableEvents implements AvailableEvents
{
    public function __construct(private CacheItemPoolInterface $availableEvents) {}

    public function add(array $event): void
    {
        $this
            ->availableEvents
            ->getItem($event['allSequence'])
            ->set($event);
    }

    public function fetchOne(): ?string
    {
        $items = $this->availableEvents->getItems();

        dd($items);
    }

    public function remove(int $index): void
    {
        $this
            ->availableEvents
            ->deleteItem($index);
    }
}
