<?php

declare(strict_types=1);

namespace App\Tests;

use PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository\CachedInFlightEvents;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class InFlightEventsCacheTest extends TestCase
{
    protected CachedInFlightEvents $cache;
    private ApplicationId $applicationId;
    private StreamId $streamId;

    protected function setUp(): void
    {
        $this->cache = new CachedInFlightEvents(
            new ArrayAdapter()
        );

        $this->applicationId = ApplicationId::fromString('00000000-0000-0000-0000-000000000000');
        $this->streamId = StreamId::fromString('00000000-0000-0000-0000-000000000000');
    }

    #[Test]
    public function itReturnsCorrectEvents(): void
    {
        $this->cache->addEventForApplicationId($this->applicationId, self::event(1));
        $this->cache->addEventForApplicationId($this->applicationId, self::event(2));

        $items = \iterator_to_array($this->cache->forApplicationIdAndStreamId($this->applicationId, $this->streamId));

        $this->assertCount(2, $items);

        $this->assertTrue(true);
    }

    #[Test]
    public function itRemovesIndividualEvent(): void
    {
        $eventTwo = self::event(2);

        $this->cache->addEventForApplicationId($this->applicationId, self::event(1));
        $this->cache->addEventForApplicationId($this->applicationId, $eventTwo);
        $this->cache->addEventForApplicationId($this->applicationId, self::event(3));

        $this->cache->removeEventForApplicationId($this->applicationId, $eventTwo);

        $items = \iterator_to_array($this->cache->forApplicationIdAndStreamId($this->applicationId, $this->streamId));

        $this->assertEquals(1, $items[1]['number']);
        $this->assertEquals(3, $items[3]['number']);
    }

    #[Test]
    public function itReturnsEmptyWhenAllEventsRemoved(): void
    {
        $eventOne   = self::event(1);
        $eventTwo   = self::event(2);
        $eventThree = self::event(3);

        $this->cache->addEventForApplicationId($this->applicationId, $eventOne);
        $this->cache->addEventForApplicationId($this->applicationId, $eventTwo);
        $this->cache->addEventForApplicationId($this->applicationId, $eventThree);

        $this->cache->removeEventForApplicationId($this->applicationId, $eventOne);
        $this->cache->removeEventForApplicationId($this->applicationId, $eventTwo);
        $this->cache->removeEventForApplicationId($this->applicationId, $eventThree);

        $this->assertEmpty(
            $this->cache->forApplicationIdAndStreamId($this->applicationId, $this->streamId)
        );
    }

    private static function event(int $number): array
    {
        return [
            'number' => $number,
            'stream' => '00000000-0000-0000-0000-000000000000',
        ];
    }
}
