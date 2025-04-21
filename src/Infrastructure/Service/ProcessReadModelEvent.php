<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Exception\NoRepositoryAssignedToHandleEvent;

class ProcessReadModelEvent
{
    /**
     * @param array{name: string, number: int, payload: array, occurred: string} $event
     */
    private function handleEvent(array $event): void
    {
        match ($event['name']) {
            default => throw NoRepositoryAssignedToHandleEvent::withName($event['name'])
//            'item-added-to-basket' => $this->basketItemReadModelRepository->create(self::basketItem($event))
        };
    }
}
