<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\InFlightEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Exception\CannotFetchMessages;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

final readonly class Client
{
    /**
     * @param PromiseInterface<ConnectionInterface>|null $connection
     */
    public function __construct(
        private Config $config,
        private InFlightEvents $inFlightEvents,
        private ?PromiseInterface $connection = null
    ) {}

    public function connect(): self
    {
        return new self(
            $this->config,
            $this->inFlightEvents,
            (new Connector())
                ->connect(
                    sprintf(
                        '%s:%d',
                        $this->config->serverHost,
                        $this->config->serverPort
                    )
                )
        );
    }

    public function fetchMessages(callable $eventHandler): void
    {
        if (null === $this->connection) {
            throw CannotFetchMessages::beforeConnectionHasBeenEstablished();
        }

        $this
            ->connection
            ->then(function (ConnectionInterface $connection) use ($eventHandler) {
                $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

                $connection->write(CreateMessage::forProvidingIdentity($applicationId));
                $connection->write(CreateMessage::forCatchupRequest(StreamId::fromString('*'), $applicationId));

                $connection->on('data', function (string $event) use ($applicationId, $connection, $eventHandler)  {
                    $events = explode(MessageMarkup::NewEventParser->value, $event);

                    foreach ($events as $parsedEvent) {
                        if ('' === $parsedEvent) {
                            continue;
                        }

                        try {
                            $decodedEvent = self::decodeEvent($parsedEvent);
                        } catch (\JsonException $e) {
                            echo $e->getMessage();
                            var_dump($parsedEvent);
                            die;
                        }

                        $streamId = StreamId::fromString($decodedEvent['stream']);

                        if ($this->inFlightEvents->containsEventsForApplicationIdAndStreamId($applicationId, $streamId)) {
                            $this->addInFlightEvent($applicationId, $decodedEvent);

                            continue;
                        }

                        $this->addInFlightEvent($applicationId, $decodedEvent);
                        $this->processEvent($connection, $applicationId, $decodedEvent, $eventHandler);

                        foreach ($this->inFlightEvents($applicationId, $streamId) as $inFlightEvent) {
                            $this->processEvent($connection, $applicationId, $inFlightEvent, $eventHandler);
                        }
                    }
                });
            });
    }

    private static function decodeEvent(string $event): array
    {
        return json_decode(
            trim(
                str_replace(MessageType::NewEvent->value, '', $event)
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private static function acknowledgeEvent(
        ConnectionInterface $connection,
        ApplicationId $applicationId,
        array $decodedEvent
    ): void {
        $connection->write(
            CreateMessage::forAcknowledgement(
                StreamId::fromString($decodedEvent['stream']),
                $applicationId,
                Checkpoint::fromInt($decodedEvent['number']),
                Checkpoint::fromInt($decodedEvent['allSequence'])
            )
        );
    }

    private function addInFlightEvent(ApplicationId $applicationId, array $decodedEvent): void
    {
        $this->inFlightEvents->addEventForApplicationId($applicationId, $decodedEvent);
    }

    private function processEvent(
        ConnectionInterface $connection,
        ApplicationId $applicationId,
        array $decodedEvent,
        callable $eventHandler
    ): void {
        $eventHandler($decodedEvent);
        self::acknowledgeEvent($connection, $applicationId, $decodedEvent);

        $this->inFlightEvents->removeEventForApplicationId($applicationId, $decodedEvent);
    }

    private function inFlightEvents(ApplicationId $applicationId, StreamId $streamId): iterable
    {
        return $this->inFlightEvents->forApplicationIdAndStreamId($applicationId, $streamId);
    }
}
