<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\CatchupHandler as CatchupHandlerInterface;

final class CatchupHandler implements CatchupHandlerInterface
{
    private function __construct(private Checkpoint $checkpoint, private $cachedEvents) {}

    public static function create(Checkpoint $checkpoint): self
    {
        return new self($checkpoint, []);
    }

    public function handleReceivedEvent(callable $eventHandler): callable
    {
        return function (string $data) use ($eventHandler) {
            $events = array_filter(explode(PHP_EOL, $data));

            foreach ($events as $event) {
                $this->checkpoint = $this->checkpoint->increment();
                $decoded          = json_decode($event, true, 512, JSON_THROW_ON_ERROR);

                if ($decoded['number'] > $this->checkpoint->toInt()) {
                    // a new event must have come in since running catchup request
                    $this->cachedEvents[$decoded['number']] = $event;

                    continue;
                }

                var_dump('we can handle', $event);

                $eventHandler($decoded);
            }
        };
    }
}
