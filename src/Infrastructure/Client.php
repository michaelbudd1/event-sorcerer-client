<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Exception\CannotFetchMessages;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventName;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventVersion;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\Promise\Promise;
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
        private AvailableEvents $availableEvents,
        private ?PromiseInterface $connection = null
    ) {}

    public function connect(): self
    {
        return new self(
            $this->config,
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

    public function availableEventsCount(): int
    {
        return $this->availableEvents->count(
            ApplicationId::fromString($this->config->eventSourcererApplicationId)
        );
    }

    public function hasEventsAvailable(): bool
    {
        return 0 !== $this->availableEventsCount();
    }

    public function listenForMessages(): void
    {
        if (null === $this->connection) {
            throw CannotFetchMessages::beforeConnectionHasBeenEstablished();
        }

        $this
            ->connection
            ->then(function (ConnectionInterface $connection) {
                $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

                $connection->write(CreateMessage::forProvidingIdentity($applicationId, $this->config->applicationType));

                if (0 === $this->availableEventsCount()) {
                    $connection->write(
                        CreateMessage::forCatchupRequest(
                            StreamId::fromString('*'),
                            $applicationId
                        )
                    );
                }

                $connection->on('data', function (string $events) use ($applicationId)  {
                    $this->addEventsForProcessing($applicationId, $events);
                });

                return new Promise(static fn () => $connection);
            });
    }

    public function fetchOneMessage(): ?array
    {
        return $this->availableEvents->fetchOne(
            ApplicationId::fromString($this->config->eventSourcererApplicationId)
        );
    }

    private static function decodeEvent(string $event): ?array
    {
        try {
            $regex = sprintf('/%s {.+}/', MessageType::NewEvent->value);

            preg_match($regex, $event, $matches);

            if (!isset($matches[0])) {
                return null;
            }

            return json_decode(
                trim(
                    str_replace(MessageType::NewEvent->value, '', $matches[0])
                ),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            echo self::jsonDecodeErrorMessage($event);

            return null;
        }
    }

    public function acknowledgeEvent(
        StreamId $stream,
        Checkpoint $streamCheckpoint,
        Checkpoint $allStreamCheckpoint
    ): void {
        $this
            ->connection
            ->then(function (ConnectionInterface $connection) use ($stream, $streamCheckpoint, $allStreamCheckpoint) {
                $connection->end(
                    CreateMessage::forAcknowledgement(
                        $stream,
                        ApplicationId::fromString($this->config->eventSourcererApplicationId),
                        $streamCheckpoint,
                        $allStreamCheckpoint
                    )
                );
            });

        $this->availableEvents->ack($stream, $allStreamCheckpoint);
    }

    public function writeNewEvent(
        StreamId $streamId,
        EventName $eventName,
        EventVersion $eventVersion,
        array $payload
    ): void {
        $this
            ->connection
            ->then(function (ConnectionInterface $connection) use ($streamId, $eventName, $eventVersion, $payload) {
                $connection->end(
                    CreateMessage::forWriteNewEvent(
                        $streamId,
                        $eventName,
                        $eventVersion,
                        $payload
                    )
                );
            });
    }

    private static function jsonDecodeErrorMessage(string $parsedEvent): string
    {
        return sprintf(
            'An error occurred attempting to decode message: %s',
            $parsedEvent
        );
    }

    private function addEventsForProcessing(ApplicationId $applicationId, string $events): void
    {
        foreach (\array_filter(explode(MessageMarkup::NewEventParser->value, $events)) as $event) {
            $decodedEvent = self::decodeEvent($event);

            if (null === $decodedEvent) {
                continue;
            }

            $this->availableEvents->add($applicationId, $decodedEvent);
        }
    }
}
