<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
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
        private AvailableEvents $availableEvents,
        private ?PromiseInterface $connection = null
    ) {}

    public function connect(): self
    {
        return new self(
            $this->config,
            $this->inFlightEvents,
            $this->availableEvents,
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

                $connection->write(
                    CreateMessage::forCatchupRequest(
                        StreamId::fromString('*'),
                        $applicationId,
                        $this->inFlightEvents->inFlightCheckpoint()
                    )
                );

                $connection->on('data', function (string $events) use ($applicationId, $connection, $eventHandler)  {
                    $this->addEventsForProcessing($applicationId, $events);

                    while ($decodedEvent = $this->availableEvents->fetchOne($applicationId)) {
                        $streamId = StreamId::fromString($decodedEvent['stream']);

                        if ($this->isAlreadyBeingProcessedByAnotherProcess($decodedEvent)) {
                            continue;
                        }

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
        try {
            return json_decode(
                trim(
                    str_replace(MessageType::NewEvent->value, '', $event)
                ),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            echo self::jsonDecodeErrorMessage($event);

            die;
        }
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
        $this->availableEvents->remove($decodedEvent['allSequence']);
    }

    private function inFlightEvents(ApplicationId $applicationId, StreamId $streamId): iterable
    {
        return $this->inFlightEvents->forApplicationIdAndStreamId($applicationId, $streamId);
    }

    private static function jsonDecodeErrorMessage(string $parsedEvent): string
    {
        return sprintf(
            'An error occurred attempting to decode message: %s',
            $parsedEvent
        );
    }

    private function isAlreadyBeingProcessedByAnotherProcess(array $decodedEvent): bool
    {
        return $this->inFlightEvents->inFlightCheckpoint()?->isGreaterThan(
            Checkpoint::fromInt($decodedEvent['allSequence'])
        ) ?? false;
    }

    private function addEventsForProcessing(ApplicationId $applicationId, string $events): void
    {
        foreach (\array_filter(explode(MessageMarkup::NewEventParser->value, $events)) as $event) {
            $this->availableEvents->add($applicationId, self::decodeEvent($event));
        }
    }
}
