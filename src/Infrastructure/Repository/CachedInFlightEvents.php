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

    public function __construct(private CacheItemPoolInterface $cache) {}

    public function forApplicationIdAndStreamId(
        ApplicationId $applicationId,
        StreamId $streamId
    ): iterable {
        return $this
            ->cache
            ->getItem(Utils::inFlightCacheKey($applicationId, $streamId))
            ->get() ?? [];
    }

    public function containsEventsForApplicationIdAndStreamId(
        ApplicationId $applicationId,
        StreamId $streamId
    ): bool {
        return $this
            ->cache
            ->hasItem(Utils::inFlightCacheKey($applicationId, $streamId));
    }

    public function addEventForApplicationId(ApplicationId $applicationId, array $event): void
    {
        $streamId = StreamId::fromString($event['stream']);

        $inFlightKey = Utils::inFlightCacheKey($applicationId, $streamId);

        $events = $this->forApplicationIdAndStreamId($applicationId, $streamId);

        $events[$event['number']] = $event;

        $inFlightItem = $this
            ->cache
            ->getItem($inFlightKey)
            ->set($events);

        $this->cache->save($inFlightItem);

        $this->setInFlightCheckpoint(Checkpoint::fromInt($event['allSequence']));
    }

    public function removeEventForApplicationId(ApplicationId $applicationId, array $event): void
    {
        $streamId = StreamId::fromString($event['stream']);

        $inFlightKey = Utils::inFlightCacheKey($applicationId, $streamId);

        $events = $this->forApplicationIdAndStreamId($applicationId, $streamId);

        unset($events[$event['number']]);

        if (empty($events)) {
            $this
                ->cache
                ->deleteItem($inFlightKey);

            return;
        }

        $updatedEvents = $this->cache->getItem($inFlightKey);

        $this
            ->cache
            ->save(
                $updatedEvents->set($events)
            );
    }

    public function inFlightCheckpoint(): ?Checkpoint
    {
        $checkpoint = $this
            ->cache
            ->getItem(self::IN_FLIGHT_CATCHUP_REQUEST_CHECKPOINT)
            ->get();

        return $checkpoint
            ? Checkpoint::fromInt($checkpoint)
            : null;
    }

    public function setInFlightCheckpoint(Checkpoint $checkpoint): void
    {
        $checkpointCacheItem = $this
            ->cache
            ->getItem(self::IN_FLIGHT_CATCHUP_REQUEST_CHECKPOINT)
            ->set($checkpoint->toInt());

        $this->cache->save($checkpointCacheItem);
    }
}
