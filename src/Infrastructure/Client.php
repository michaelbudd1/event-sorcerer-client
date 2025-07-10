<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

final readonly class Client
{
    public function __construct(
        private string $eventSourcererHost,
        private int $socketPort,
        private string $eventSourcererApplicationId
    ) {}

    public function fetchMessages(callable $eventHandler): void
    {
        /** @todo this infrastructure concerned code should be in the event-sourcerer-client repo! */
        (new Connector())
            ->connect(
                sprintf(
                    '%s:%d',
                    $this->eventSourcererHost,
                    $this->socketPort
                )
            )
            ->then(function (ConnectionInterface $connection) use ($eventHandler) {
                $applicationId = ApplicationId::fromString($this->eventSourcererApplicationId);
                $connection->write(CreateMessage::forProvidingIdentity($applicationId));
                $connection->on('data', function (string $event) use ($applicationId, $connection, $eventHandler)  {
                    $events = explode(MessageMarkup::NewEventParser->value, $event);

                    // @todo do not process event if skipped events

                    foreach ($events as $parsedEvent) {
                        if ('' === $parsedEvent) {
                            continue;
                        }

                        $decodedEvent = self::decodeEvent($parsedEvent);

                        $eventHandler($decodedEvent);

                        self::acknowledgeEvent($connection, $applicationId, $decodedEvent);
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
                StreamId::fromString($decodedEvent['catchupRequestStream'] ?? $decodedEvent['stream']),
                $applicationId,
                Checkpoint::fromInt($decodedEvent['number'])
            )
        );
    }
}
