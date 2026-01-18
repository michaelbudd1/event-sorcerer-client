<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWeb\EventSourcerer\Client\Exception\CouldNotEstablishLocalConnection;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Exception\MasterConnectionBroken;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventName;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventVersion;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\WorkerId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\UnixServer;

final readonly class Client
{
    public const string IPC_URI = '/tmp/eventsourcerer-shared-socket.sock';

    public function __construct(
        private Config $config,
        private ConnectionInterface|PromiseInterface|null $connection = null
    ) {}

    public function catchup(WorkerId $workerId, callable $newEventHandler, callable $logAction = null): self
    {
        if (null !== $this->connection) {
            return $this;
        }

        $externalConnection = null;

        $this
            ->createConnection()
            ->then(function (ConnectionInterface $connection) use ($workerId, $newEventHandler, $logAction) {
                self::deleteSockFile();

                $localServer = new UnixServer(self::IPC_URI);
                $logAction = $logAction ?? self::nullLogActionHandler();

                $localServer->on('connection', function (ConnectionInterface $localConnection) use ($connection, $logAction) {
                    $localConnection->on('data', function ($data) use ($connection) {
                        $connection->write($data);
                    });

                    $localConnection->on('error', function (\Exception $e) use ($logAction) {
                        $logAction(ConnectionUpdate::ConnectionErrored, $e->getMessage());
                    });

                    $localConnection->on('close', function () use ($logAction) {
                        $logAction(ConnectionUpdate::ConnectionClosed);
                    });

                    $localConnection->on('end', function () use ($logAction) {
                        $logAction(ConnectionUpdate::ConnectionEnded);
                    });
                });

                $connection->on('data', function (string $events) use ($newEventHandler) {
                    foreach (\array_filter(explode(MessageMarkup::NewEventParser->value, $events)) as $event) {
                        $decodedEvent = self::decodeEvent($event);

                        echo 'received ' . $event . PHP_EOL;

                        if (null === $decodedEvent) {
                            continue;
                        }

                        $newEventHandler($decodedEvent);
                    }
                });

                $connection->on('error', function (\Exception $e) {
                    throw MasterConnectionBroken::becauseOfAnError($e->getMessage());
                });

                $connection->on('close', function () {
                    throw MasterConnectionBroken::becauseItClosed();
                });

                $connection->on('end', function () {
                    throw MasterConnectionBroken::becauseItEnded();
                });

                $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

                $connection->write(
                    CreateMessage::forProvidingIdentity(
                        ApplicationId::fromString($this->config->eventSourcererApplicationId),
                        $this->config->applicationType,
                        $workerId
                    )
                );

                sleep(2);

                $connection->write(
                    CreateMessage::forCatchupRequest(
                        StreamId::allStream(),
                        $applicationId,
                        $workerId
                    )
                );

                return $connection;
            });

        return new self($this->config, $externalConnection);
    }

    public function createConnection(): PromiseInterface
    {
        return (new Connector())
            ->connect(
                sprintf(
                    '%s:%d',
                    $this->config->serverHost,
                    $this->config->serverPort
                )
            );
    }

    public function connected(): bool
    {
        return null !== $this->connection;
    }

    public function connection(): ?ConnectionInterface
    {
        return $this->connection;
    }

    public function applicationId(): ApplicationId
    {
        return ApplicationId::fromString($this->config->eventSourcererApplicationId);
    }

    /**
     * @return array{
     *      allSequence: int,
     *      eventVersion: int,
     *      name: string,
     *      number: int,
     *      payload: array,
     *      stream: string,
     *      occurred: string,
     *      catchupRequestStream: string
     * }|null
     */
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

    /**
     * @return resource
     */
    public function createLocalConnection()
    {
        try {
            $connection = stream_socket_client('unix://' . self::IPC_URI, $errorCode, $errorMessage);

            if (false === $connection) {
                throw CouldNotEstablishLocalConnection::because($errorMessage, $errorCode);
            }
        } catch (\Throwable) {
            throw CouldNotEstablishLocalConnection::because($errorMessage, $errorCode);
        }

        return $connection;
    }

    /**
     * @param resource $localConnection
     */
    public function acknowledgeEvent(
        StreamId $stream,
        StreamId $catchupStreamId,
        WorkerId $workerId,
        Checkpoint $streamCheckpoint,
        Checkpoint $allStreamCheckpoint,
        $localConnection
    ): void {
        $ackMessage = CreateMessage::forAcknowledgement(
            $stream,
            $catchupStreamId,
            $this->applicationId(),
            $workerId,
            $streamCheckpoint,
            $allStreamCheckpoint
        );

        fwrite($localConnection, $ackMessage->toString());
        fflush($localConnection);
    }

    public function writeNewEvent(
        StreamId $streamId,
        EventName $eventName,
        EventVersion $eventVersion,
        array $payload
    ): void {
        $this
            ?->connection
            ->write(
                CreateMessage::forWriteNewEvent(
                    $streamId,
                    $eventName,
                    $eventVersion,
                    $payload
                )
            );
    }

    private static function jsonDecodeErrorMessage(string $parsedEvent): string
    {
        return sprintf(
            'An error occurred attempting to decode message: %s',
            substr($parsedEvent, 0, 50)
        );
    }

    private static function deleteSockFile(): void
    {
        if (file_exists(self::IPC_URI)) {
            unlink(self::IPC_URI);
        }
    }

    private static function nullLogActionHandler(): callable
    {
        return static function (ConnectionUpdate $update, ?string $message = null) {
            echo sprintf(
                'Connection update: %s %s',
                $update->name,
                $message ?? '',
            ) . PHP_EOL;
        };
    }
}
