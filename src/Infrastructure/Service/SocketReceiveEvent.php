<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Service\SendEvent;
use PearTreeWeb\EventSourcerer\Client\Domain\Service\ReceiveEvent;
use PearTreeWeb\EventSourcerer\Client\Exception\CouldNotProcessEvent;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

/**
 * @todo is this used anywhere??????
 */
final class SocketReceiveEvent implements ReceiveEvent
{
    private function __construct(
        private readonly SendEvent $sendEvent,
        private ?Checkpoint $checkpoint,
        private readonly array $cachedEvents = []
    ) {}

    public static function create(SendEvent $acknowledgeEvent, ?Checkpoint $checkpoint = null): self
    {
        return new self($acknowledgeEvent, $checkpoint);
    }

    public function handleReceivedEvent(callable $eventHandler): callable
    {
        return function (string $data) use ($eventHandler) {
            $events = array_filter(explode(PHP_EOL, $data));

            foreach ($events as $event) {
                $decoded = json_decode($event, true, 512, JSON_THROW_ON_ERROR);

                $this->checkpoint = null === $this->checkpoint
                    ? Checkpoint::fromInt($decoded['number'])
                    : $this->checkpoint->increment();

                if ($decoded['number'] > $this->checkpoint->toInt()) {
                    // a new event must have come in since running catchup request
                    $this->cachedEvents[$decoded['number']] = $decoded;

                    continue;
                }

                $this->processEvent($eventHandler, $decoded);
            }

            foreach ($this->cachedEvents as $event) {
                $nextEventNumber = $this->checkpoint->toInt();

                if ($event['number'] !== $nextEventNumber) {
                    throw CouldNotProcessEvent::eventOutOfSequence($nextEventNumber, $event['number']);
                }

                $this->checkpoint = $this->checkpoint->increment();

                $this->processEvent($eventHandler, $event);
            }
        };
    }

    private function processEvent(callable $eventHandler, array $decoded): void
    {
        $eventHandler($decoded);

        $this->sendEvent->requestCatchupFor(
            StreamId::fromString('some stream'), // @todo get stream!
            Checkpoint::fromInt($decoded['number'])
        );
    }
}
