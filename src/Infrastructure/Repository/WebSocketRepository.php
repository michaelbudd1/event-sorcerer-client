<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Infrastructure\Repository;

use Amp\Websocket\Client\WebsocketConnection;
use PearTreeWeb\MicroManager\Client\Domain\Repository\RealTimeRepository;
use function Amp\Websocket\Client\connect;

final readonly class WebSocketRepository implements RealTimeRepository
{
    private static function iterator(WebsocketConnection $connection): iterable
    {
        foreach ($connection as $message) {
            yield $message->buffer();
        }
    }

    public function listenTo(string $stream): iterable
    {
        return self::iterator(connect('ws://localhost:8080'));
    }
}
