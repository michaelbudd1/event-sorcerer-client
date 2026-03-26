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
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\UnixServer;
use function React\Async\await;

final readonly class Client
{
    public const string IPC_URI = '/tmp/eventsourcerer-shared-socket.sock';

    public function __construct(
        private Config $config,
        private ConnectionInterface|PromiseInterface|null $connection = null
    ) {
    }

    public function catchup(WorkerId $workerId, callable $newEventHandler, callable $logAction = null): self
    {
        if (null !== $this->connection) {
            return $this;
        }

        $externalConnection = null;

        $this
            ->createConnection()
            ->then(function (ConnectionInterface $connection) use (
                $workerId,
                $newEventHandler,
                $logAction,
                &
                $externalConnection
            ) {
                self::deleteSockFile();

                $externalConnection = $connection;

                $localServer = new UnixServer(self::IPC_URI);
                $logAction   = $logAction ?? self::nullLogActionHandler();

                $localServer->on('connection',
                    function (ConnectionInterface $localConnection) use ($connection, $logAction) {
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

                // Buffer for incomplete events
                $buffer = '';

                $connection->on('data', function (string $data) use ($newEventHandler, &$buffer) {
                    $buffer .= $data;

                    $parts = explode(MessageMarkup::NewEventParser->value, $buffer);

                    // Keep the last part as it might be incomplete
                    $buffer = array_pop($parts);

                    foreach (array_filter($parts) as $event) {
                        $newEventHandler(self::decodeEvent($event));
                    }
                });

                $this->handleConnectionErrors($connection);

                $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

                $connection->write(
                    CreateMessage::forProvidingIdentity($applicationId, $this->config->applicationType, $workerId)
                );

                sleep(2);

                $connection->write(
                    CreateMessage::forCatchupRequest(StreamId::allStream(), $applicationId, $workerId)
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
        $regex = sprintf('/%s {.+}/', MessageType::NewEvent->value);

        preg_match($regex, $event, $matches);

        return json_decode(
            trim(
                str_replace(MessageType::NewEvent->value, '', $matches[0])
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
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
        StreamId     $streamId,
        EventName    $eventName,
        EventVersion $eventVersion,
        array        $payload,
        ?int         $expectedNextVersion = 0,
    ): void {
        $message = CreateMessage::forWriteNewEvent(
            $streamId,
            $eventName,
            $eventVersion,
            $payload,
            $expectedNextVersion,
        );

        if (null !== $this->connection) {
            // Long-lived connection — attach a one-time data handler
            $deferred = new Deferred();

            $this->connection->once('data', function (string $data) use ($deferred) {
                $decoded = $this->decodeAck($data);
                if ('ok' === $decoded['status']) {
                    $deferred->resolve($decoded);
                } else {
                    $deferred->reject(new \RuntimeException($decoded['error'] ?? 'Write rejected'));
                }
            });

            $this->connection->write($message);
            await($deferred->promise());

            return;
        }

        // Short-lived connection path
        $deferred = new Deferred();

        $this->createConnection()
             ->then(function (ConnectionInterface $connection) use ($message, $deferred) {
                 $buffer = '';
                 $connection->on('data', function (string $data) use ($connection, $deferred, &$buffer) {
                     $buffer .= $data;
                     $decoded = $this->decodeAck($buffer);
                     if ($decoded !== null) {
                         $connection->end();
                         if ('ok' === $decoded['status']) {
                             $deferred->resolve($decoded);
                         } else {
                             $deferred->reject(new \RuntimeException($decoded['error'] ?? 'Write rejected'));
                         }
                     }
                 });

                 $connection->on('error', fn(\Exception $e) => $deferred->reject($e));
                 $connection->write($message);
             });

        await($deferred->promise());

//        /** must wait for promise to resolve or writing sequence could become distorted */
//        /** @var ConnectionInterface $connection */
//        $connection = await($this->createConnection());
//
//        if (false === $connection->write($message)) {
//
//        }
//
//        $connection->end();
    }

    public function readStream(StreamId $streamId): \Generator
    {
        $buffer = '';
        $eventQueue = [];
        $streamEnded = false;
        $deferred = new Deferred();

        $this
            ->createConnection()
            ->then(function (ConnectionInterface $connection) use ($streamId, &$eventQueue, &$buffer, &$deferred, &$streamEnded) {
                $connection->on('data', function (string $data) use (&$buffer, &$eventQueue) {
                    $buffer .= $data;
                    $parts = explode(MessageMarkup::NewEventParser->value, $buffer);
                    $buffer = array_pop($parts);

                    foreach (array_filter($parts) as $event) {
                        $eventQueue[] = self::decodeEvent($event);
                    }
                });

                $connection->on('end', function () use (&$streamEnded, &$deferred) {
                    $streamEnded = true;
                    $deferred->resolve(null);
                });

                $connection->on('error', function (\Exception $e) use ($connection) {
                    $connection->close();
                });

                $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);
                $connection->write(CreateMessage::forReadingStream($streamId, $applicationId));
            });

        // Process events as they arrive
        while (!$streamEnded || !empty($eventQueue)) {
            while (!empty($eventQueue)) {
                yield array_shift($eventQueue);
            }

            // Give the event loop a chance to run
            if (!$streamEnded) {
                $tick = new Deferred();
                Loop::futureTick(function() use ($tick) {
                    $tick->resolve(null);
                });
                await($tick->promise());
            }
        }
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

    private function handleConnectionErrors(ConnectionInterface $connection): void
    {
        $connection->on('error', function (\Exception $e) {
            throw MasterConnectionBroken::becauseOfAnError($e->getMessage());
        });

        $connection->on('close', function () {
            throw MasterConnectionBroken::becauseItClosed();
        });

        $connection->on('end', function () {
            throw MasterConnectionBroken::becauseItEnded();
        });
    }

    private function decodeAck(string $data): ?array
    {
        $regex = sprintf('/%s {.+}/', MessageType::NewEventAccepted->value);
        if (!preg_match($regex, $data, $matches)) {
            return null;
        }

        return json_decode(
            trim(str_replace(MessageType::NewEventAccepted->value, '', $matches[0])),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
