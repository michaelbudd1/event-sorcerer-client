<?php

declare(strict_types=1);

namespace App\Tests;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\CatchupHandler;
use PHPUnit\Framework\TestCase;

final class CatchupHandlerTest extends TestCase
{
    public function testCatchupWithOutOfSequenceEvents(): void
    {
        $catchupHandler = CatchupHandler::create(Checkpoint::zero());

        $catchupHandler->handleReceivedEvent(static fn ($event) => null)(self::events());
    }

    private static function events(): string
    {
        return
            json_encode(['number' => 2]) . PHP_EOL .
            json_encode(['number' => 3]) . PHP_EOL .
            json_encode(['number' => 1]);
    }
}
