<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\SharedProcessCommunication;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventName;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventVersion;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\FixedUriConnector;
use React\Socket\UnixConnector;
use React\Socket\UnixServer;

final readonly class Client
{
    private const string IPC_URI = '/tmp/eventsourcerer-shared-socket.sock';

    public function __construct(
        private Config $config,
        private AvailableEvents $availableEvents,
        private SharedProcessCommunication $sharedProcessCommunication,
        private ?ConnectionInterface $connection = null
    ) {}

//    public function connect(WorkerId $workerId): self
//    {
//        if (null !== $this->connection) {
//            return $this;
//        }
//
//        // Use synchronous socket connection for worker processes
//        $socket = stream_socket_client(
//            self::IPC_URI,
//            $errno,
//            $errstr,
//            30
//        );
//
//        if (false === $socket) {
//            throw new \RuntimeException(
//                sprintf('Failed to connect to IPC socket: %s (%d)', $errstr, $errno)
//            );
//        }
//
//        // Wrap in ReactPHP connection interface for compatibility
//        $workerConnection = new Connection($socket, Loop::get());
//
//        $connector = new React\Socket\UnixConnector();
//
//        $connector->connect('/tmp/demo.sock')->then(function (React\Socket\ConnectionInterface $connection) {
//            $connection->write("HELLO\n");
//        });
//
//
//        $this->availableEvents->declareWorker(
//            $workerId,
//            ApplicationId::fromString($this->config->eventSourcererApplicationId)
//        );
//
//        return new self(
//            $this->config,
//            $this->availableEvents,
//            $this->sharedProcessCommunication,
//            $workerConnection
//        );
//    }

    public function connected(): bool
    {
        return null !== $this->connection;
    }

    public function runIPCServer(): void
    {
        if (file_exists(self::IPC_URI)) {
            unlink(self::IPC_URI);
        }

        $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

        (new Connector())
            ->connect(
                sprintf(
                    '%s:%d',
                    $this->config->serverHost,
                    $this->config->serverPort
                )
            )->then(function (ConnectionInterface $connection) use ($applicationId, &$externalConnection) {
                $externalConnection = $connection;

                $connection->write(CreateMessage::forProvidingIdentity($applicationId, $this->config->applicationType));

                if (!$this->sharedProcessCommunication->catchupInProgress()) {
                    $this->sharedProcessCommunication->flagCatchupIsInProgress();

                    $connection->write(
                        CreateMessage::forCatchupRequest(
                            StreamId::fromString('*'),
                            $applicationId
                        )
                    );
                }

                echo 'Connected to external service' . PHP_EOL;

                // Create IPC server for workers
                $server = new UnixServer(self::IPC_URI);
                $workers = [];

                $server->on('connection', function (ConnectionInterface $worker) use (&$workers, &$externalConnection) {
                    $workers[] = $worker;

                    $worker->on('data', function ($data) use (&$worker, &$externalConnection) {
                        echo 'YES we received a message!' . PHP_EOL;

                        $externalConnection->write($data);
//                        $worker->close();
                    });

                    $worker->on('close', function () use (&$workers, $worker) {
                        $workers = array_filter($workers, static fn ($w) => $w !== $worker);
                    });

                    echo 'Worker connected' . PHP_EOL;
                });

                $connection->on('data', function (string $events) use ($applicationId) {
                    echo 'processing events' . PHP_EOL;
                    $this->addEventsForProcessing($applicationId, $events);
                });

                echo 'Main process running' . PHP_EOL;
            });
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

    public function fetchOneMessage(WorkerId $workerId): ?array
    {
        return $this->availableEvents->fetchOne(
            $workerId,
            ApplicationId::fromString($this->config->eventSourcererApplicationId)
        );
    }

    public function attachWorker(WorkerId $workerId): void
    {
        $this->availableEvents->declareWorker(
            $workerId,
            ApplicationId::fromString($this->config->eventSourcererApplicationId)
        );
    }

    public function detachWorker(WorkerId $workerId): void
    {
        $this->availableEvents->detachWorker($workerId);

//        if ($this->availableEvents->hasNoWorkersRunning()) {
        $this->sharedProcessCommunication->clear();
        $this->availableEvents->clear(ApplicationId::fromString($this->config->eventSourcererApplicationId));
//        }
    }

    public function flagCatchupComplete(): void
    {
        $this->sharedProcessCommunication->flagCatchupIsNotInProgress();
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
        (new FixedUriConnector('unix://' . self::IPC_URI, new UnixConnector()))
            ->connect('')
            ->then(function (ConnectionInterface $connection) use ($stream, $streamCheckpoint, $allStreamCheckpoint) {
                $connection->write(
                    CreateMessage::forAcknowledgement(
                        $stream,
                        ApplicationId::fromString($this->config->eventSourcererApplicationId),
                        $streamCheckpoint,
                        $allStreamCheckpoint
                    )
                );

                $connection->end();
//                $connection->close();
            }
        );
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
                $connection->write(
                    CreateMessage::forWriteNewEvent(
                        $streamId,
                        $eventName,
                        $eventVersion,
                        $payload
                    )
                );

                $connection->end();
            });
    }

    public function list(string $applicationId): iterable
    {
        return $this->availableEvents->list(
            ApplicationId::fromString($applicationId)
        );
    }

    public function summary(ApplicationId $applicationId): array
    {
        return $this->availableEvents->summary($applicationId);
    }

    private static function jsonDecodeErrorMessage(string $parsedEvent): string
    {
        return sprintf(
            'An error occurred attempting to decode message: %s',
            substr($parsedEvent, 0, 50)
        );
    }

    private function addEventsForProcessing(ApplicationId $applicationId, string $events): void
    {
        foreach (\array_filter(explode(MessageMarkup::NewEventParser->value, $events)) as $event) {
            $decodedEvent = self::decodeEvent($event);

            if (null === $decodedEvent) {
                continue;
            }

            $this->addEventToCache($applicationId, $decodedEvent);
        }
    }

    private function addEventToCache(ApplicationId $applicationId, array $decodedEvent): void
    {
//        if ($this->availableEvents->count($applicationId) >= 100) {
//            sleep(10);

//            $this->addEventToCache($applicationId, $decodedEvent);

//            return;
//        }

        $this->availableEvents->add($applicationId, $decodedEvent);
    }
}
