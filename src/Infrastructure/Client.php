<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\AvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\SharedProcessCommunication;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Exception\CannotFetchMessages;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\Checkpoint;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventName;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\EventVersion;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageMarkup;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessageType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Service\CreateMessage;
use React\EventLoop\Loop;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\SocketServer;

final readonly class Client
{
    /**
     * @param PromiseInterface<ConnectionInterface>|null $connection
     */
    public function __construct(
        private Config $config,
        private AvailableEvents $availableEvents,
        private SharedProcessCommunication $sharedProcessCommunication,
        private ?PromiseInterface $connection = null
    ) {}

    public function connect(): self
    {
        // @todo this only needs to connect to the IPC server
        return new self(
            $this->config,
            $this->availableEvents,
            $this->sharedProcessCommunication,
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

    public function runIPCServer(): void
    {
        $loop = Loop::get();

        $externalConnection = null;

        $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

        (new Connector(loop: $loop))
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

                $connection->on('data', function (string $events) use ($applicationId) {
                    $this->addEventsForProcessing($applicationId, $events);
                });

                echo 'Connected to external service' . PHP_EOL;
            });

        // Create IPC server for workers
        $server = new SocketServer('unix://eventsourcerer-shared-socket.sock', [], $loop);
        $workers = [];

        $server->on('connection', function (ConnectionInterface $worker) use ($applicationId, &$workers, &$externalConnection) {
            echo 'Worker connected' . PHP_EOL;
            $workers[] = $worker;

            // Forward data from external connection to all workers
//            $externalConnection?->on('data', function (string $events) use ($applicationId) {
//                foreach ($workers as $w) {
//                    $w->write($data);
//                }
//            });

            $worker->on('data', function ($data) use ($externalConnection) {
                $externalConnection?->write($data);
            });

            $worker->on('close', function () use (&$workers, $worker) {
                $workers = array_filter($workers, static fn ($w) => $w !== $worker);
            });
        });

        echo 'Main process running' . PHP_EOL;

        $loop?->run();
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
        // @todo this should connect to the IPC server

        if (null === $this->connection) {
            throw CannotFetchMessages::beforeConnectionHasBeenEstablished();
        }

        $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

        $this
            ->connection
            ->then(function (ConnectionInterface $connection) use ($applicationId) {
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

                $connection->on('data', function (string $events) use ($applicationId)  {
                    $this->addEventsForProcessing($applicationId, $events);
                });

                // Create IPC server for workers
                $ipcServer = new SocketServer('unix://eventsourcerer-shared-socket.sock');
                $workers = [];

                $ipcServer->on('connection', function (ConnectionInterface $worker) use (&$workers, &$connection) {
                    echo "Worker connected\n";
                    $workers[] = $worker;

                    // Forward data from external connection to all workers
                    $connection?->on('data', function ($data) use ($workers) {
                        foreach ($workers as $w) {
                            $w->write($data);
                        }
                    });

                    // Forward data from worker to external connection
                    $worker->on('data', function ($data) use ($connection) {
                        $connection?->write($data);
                    });

                    $worker->on('close', function () use (&$workers, $worker) {
                        $workers = array_filter($workers, static fn ($w) => $w !== $worker);
                    });
                });

                return new Promise(static fn () => $connection);
            });
    }

    public function fetchOneMessage(WorkerId $workerId): ?array
    {
        $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);

        $this->availableEvents->declareWorker($workerId, $applicationId);

        $message = $this->availableEvents->fetchOne($workerId, $applicationId);

        if (null === $message) {
            return null;
        }

        if ($this->sharedProcessCommunication->messageIsAlreadyBeingProcessed($message['allSequence'])) {
            return $this->fetchOneMessage($workerId);
        }

        $this->sharedProcessCommunication->addEventCurrentlyBeingProcessed($message['allSequence']);

        return $message;
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
        $connector = new Connector();
        $connector->connect('unix://eventsourcerer-shared-socket.sock')->then(
            function (ConnectionInterface $connection) use ($stream,  $streamCheckpoint, $allStreamCheckpoint) {
                $connection->write(
                    CreateMessage::forAcknowledgement(
                        $stream,
                        ApplicationId::fromString($this->config->eventSourcererApplicationId),
                        $streamCheckpoint,
                        $allStreamCheckpoint
                    )
                );

                $connection->close();
            }
        );

//        $this
//            ->connection
//            ->then(function (ConnectionInterface $connection) use ($stream, $streamCheckpoint, $allStreamCheckpoint) {
//                $connection->write(
//                    CreateMessage::forAcknowledgement(
//                        $stream,
//                        ApplicationId::fromString($this->config->eventSourcererApplicationId),
//                        $streamCheckpoint,
//                        $allStreamCheckpoint
//                    )
//                );
//            });

        $this->sharedProcessCommunication->removeEventCurrentlyBeingProcessed($allStreamCheckpoint->value);
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
//
//            $this->addEventToCache($applicationId, $decodedEvent);
//
//            return;
//        }

        $this->availableEvents->add($applicationId, $decodedEvent);
    }
}
