<?php

declare(strict_types=1);

namespace App\Tests;

use PearTreeWeb\EventSourcerer\Client\Exception\CouldNotProcessEvent;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\SocketReceiveEvent;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PHPUnit\Framework\TestCase;

final class CatchupHandlerTest extends TestCase
{
    public function testCatchupWithOutOfSequenceEvents(): void
    {
        $eventNumbers = [];

        SocketReceiveEvent::create(Checkpoint::zero())->handleReceivedEvent(static function ($event) use (&$eventNumbers) {
            $eventNumbers[] = $event['number'];
        })(
            json_encode(['number' => 3]) . PHP_EOL .
            json_encode(['number' => 1]) . PHP_EOL .
            json_encode(['number' => 2])
        );

        $this->assertEquals(
            [1, 2, 3],
            $eventNumbers
        );
    }

    public function testItThrowsWhenGapBetweenEventsAndCachedEvents(): void
    {
        $this->expectException(CouldNotProcessEvent::class);

        $eventNumbers = [];

        SocketReceiveEvent::create(Checkpoint::zero())->handleReceivedEvent(static function ($event) use (&$eventNumbers) {
            $eventNumbers[] = $event['number'];
        })(
            json_encode(['number' => 2]) . PHP_EOL .
            json_encode(['number' => 3]) . PHP_EOL .
            json_encode(['number' => 1]) . PHP_EOL .
            json_encode(['number' => 5])
        );
    }
}
