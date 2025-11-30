<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventName;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventVersion;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\UnixServer;

final readonly class Client
{
    public const string IPC_URI = '/tmp/eventsourcerer-shared-socket.sock';

    public function __construct(
        private Config $config,
//        private AvailableEvents $availableEvents,
//        private SharedProcessCommunication $sharedProcessCommunication,
        private ConnectionInterface|PromiseInterface|null $connection = null
    ) {}

    public function catchup(callable $newEventHandler): self
    {
        if (null !== $this->connection) {
            return $this;
        }

        $externalConnection = null;

        $loop = Loop::get();

        $this
            ->createConnection($loop)
            ->then(function (ConnectionInterface $connection) use ($newEventHandler, &$externalConnection, $loop) {
                self::deleteSockFile();

                $externalConnection = $connection;

                $localServer = new UnixServer(self::IPC_URI, loop: $loop);

                $localServer->on('connection', function (ConnectionInterface $localConnection) use ($connection) {
                    echo $localConnection->getLocalAddress() . ' has connected' . PHP_EOL;

                    $localConnection->on('data', function ($data) use ($connection) {
                        echo 'We have received a message! ' . $data;

                        $connection->write($data);
                    });
                });

                $connection->on('data', function (string $events) use ($newEventHandler) {
                    foreach (\array_filter(explode(MessageMarkup::NewEventParser->value, $events)) as $event) {
                        $decodedEvent = self::decodeEvent($event);

                        if (null === $decodedEvent) {
                            continue;
                        }

                        $newEventHandler($decodedEvent);
                    }
                });

                $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

                $connection->write(
                    CreateMessage::forProvidingIdentity(
                        ApplicationId::fromString($this->config->eventSourcererApplicationId),
                        $this->config->applicationType
                    )
                );

                $connection->write(
                    CreateMessage::forCatchupRequest(StreamId::allStream(), $applicationId)
                );

                return $connection;
            });

        return new self(
            $this->config,
//            $this->availableEvents,
//            $this->sharedProcessCommunication,
            $externalConnection
        );
    }

    public function createConnection(?LoopInterface $loop = null): PromiseInterface
    {
        return (new Connector(loop: $loop))
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
//
//    public function availableEventsCount(): int
//    {
//        return $this->availableEvents->count(
//            ApplicationId::fromString($this->config->eventSourcererApplicationId)
//        );
//    }
//
//    public function hasEventsAvailable(): bool
//    {
//        return 0 !== $this->availableEventsCount();
//    }
//
//    public function fetchOneMessage(WorkerId $workerId): ?array
//    {
//        return $this->availableEvents->fetchOne(
//            $workerId,
//            ApplicationId::fromString($this->config->eventSourcererApplicationId)
//        );
//    }
//
//    public function attachWorker(WorkerId $workerId): void
//    {
//        $this->availableEvents->declareWorker(
//            $workerId,
//            ApplicationId::fromString($this->config->eventSourcererApplicationId)
//        );
//    }
//
//    public function detachWorker(WorkerId $workerId): void
//    {
//        $this->availableEvents->detachWorker($workerId);
//        if ($this->availableEvents->hasNoWorkersRunning()) {
//        $this->sharedProcessCommunication->clear();
//        $this->availableEvents->clear(ApplicationId::fromString($this->config->eventSourcererApplicationId));
//        }
//    }

//    public function flagCatchupComplete(): void
//    {
//        $this->sharedProcessCommunication->flagCatchupIsNotInProgress();
//    }

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

    public function acknowledgeEvent(
        StreamId $stream,
        Checkpoint $streamCheckpoint,
        Checkpoint $allStreamCheckpoint
    ): void {
//        $this
//            ->connection
//            ?->write(
//                CreateMessage::forAcknowledgement(
//                    $stream,
//                    ApplicationId::fromString($this->config->eventSourcererApplicationId),
//                    $streamCheckpoint,
//                    $allStreamCheckpoint
//                )
//            );
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
//
//    public function list(string $applicationId): iterable
//    {
//        return $this->availableEvents->list(
//            ApplicationId::fromString($applicationId)
//        );
//    }
//
//    public function summary(ApplicationId $applicationId): array
//    {
//        return $this->availableEvents->summary($applicationId);
//    }

    private static function jsonDecodeErrorMessage(string $parsedEvent): string
    {
        return sprintf(
            'An error occurred attempting to decode message: %s',
            substr($parsedEvent, 0, 50)
        );
    }

//    private function addEventsForProcessing(ApplicationId $applicationId, string $events): void
//    {
//        foreach (\array_filter(explode(MessageMarkup::NewEventParser->value, $events)) as $event) {
//            $decodedEvent = self::decodeEvent($event);
//
//            if (null === $decodedEvent) {
//                continue;
//            }
//
//            $this->addEventToCache($applicationId, $decodedEvent);
//        }
//    }

//    private function addEventToCache(ApplicationId $applicationId, array $decodedEvent): void
//    {
//        if ($this->availableEvents->count($applicationId) >= 100) {
//            sleep(10);

//            $this->addEventToCache($applicationId, $decodedEvent);

//            return;
//        }

//        $this->availableEvents->add($applicationId, $decodedEvent);
//    }

//    private function createIPCServer(ConnectionInterface $externalConnection): void
//    {
//        self::deleteSockFile();
//
//        // Create IPC server for workers
//        $server = new UnixServer(self::IPC_URI);
//
//        $server->on('connection', function (ConnectionInterface $worker) use ($externalConnection) {
//            $worker->on('data', function ($data) use ($worker, $externalConnection) {
//                if ($externalConnection !== null) {
//                    $externalConnection->write($data);
//
////                    $loop->futureTick(function () use ($worker) {
////                        echo '[IPC Server] Closing worker connection' . PHP_EOL;
////                        $worker->close();
////                    });
//                } else {
//                    echo 'Warning: External connection not ready yet' . PHP_EOL;
//                }
//
//                /**
//                 * Worker connection must be closed here rather than in calling code, otherwise
//                 * the connection closes before the message has sent
//                 */
//            });
//
//            echo 'Worker connected' . PHP_EOL;
//        });
//    }

    private static function deleteSockFile(): void
    {
        if (file_exists(self::IPC_URI)) {
            unlink(self::IPC_URI);
        }
    }
}
