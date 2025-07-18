<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\InFlightEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\Utils;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedInFlightEvents implements InFlightEvents
{
    public function __construct(private CacheItemPoolInterface $inFlightMessages) {}

    public function forApplicationIdAndStreamId(
        ApplicationId $applicationId,
        StreamId $streamId
    ): iterable {
        return $this
            ->inFlightMessages
            ->getItem(Utils::inFlightKey($applicationId, $streamId))
            ->get() ?? [];
    }

    public function containsEventsForApplicationIdAndStreamId(
        ApplicationId $applicationId,
        StreamId $streamId
    ): bool {
        return $this
            ->inFlightMessages
            ->hasItem(Utils::inFlightKey($applicationId, $streamId));
    }

    public function addEventForApplicationId(ApplicationId $applicationId, array $event): void
    {
        $streamId = StreamId::fromString($event['stream']);

        $inFlightKey = Utils::inFlightKey($applicationId, $streamId);

        $events = $this->forApplicationIdAndStreamId($applicationId, $streamId);

        $events[$event['number']] = $event;

        $inFlightItem = $this
            ->inFlightMessages
            ->getItem($inFlightKey)
            ->set($events);

        $this->inFlightMessages->save($inFlightItem);
    }

    public function removeEventForApplicationId(ApplicationId $applicationId, array $event): void
    {
        $streamId = StreamId::fromString($event['stream']);

        $inFlightKey = Utils::inFlightKey($applicationId, $streamId);

        $events = $this->forApplicationIdAndStreamId($applicationId, $streamId);

        unset($events[$event['number']]);

        if (empty($events)) {
            $this
                ->inFlightMessages
                ->deleteItem($inFlightKey);

            return;
        }

        $updatedEvents = $this->inFlightMessages->getItem($inFlightKey);

        $this
            ->inFlightMessages
            ->save(
                $updatedEvents->set($events)
            );
    }
}
