<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\InFlightEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\Utils;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedInFlightEvents implements InFlightEvents
{
    private const string IN_FLIGHT_CATCHUP_REQUEST_CHECKPOINT = 'checkpoint';

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

        $this->setInFlightCheckpoint(Checkpoint::fromInt($event['allSequence']));
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

    public function inFlightCheckpoint(): ?Checkpoint
    {
        $checkpoint = $this
            ->inFlightMessages
            ->getItem(self::IN_FLIGHT_CATCHUP_REQUEST_CHECKPOINT)
            ->get();

        return $checkpoint
            ? Checkpoint::fromInt($checkpoint)
            : null;
    }

    public function setInFlightCheckpoint(Checkpoint $checkpoint): void
    {
        $checkpointCacheItem = $this
            ->inFlightMessages
            ->getItem(self::IN_FLIGHT_CATCHUP_REQUEST_CHECKPOINT)
            ->set($checkpoint->toInt());

        $this->inFlightMessages->save($checkpointCacheItem);
    }
}
