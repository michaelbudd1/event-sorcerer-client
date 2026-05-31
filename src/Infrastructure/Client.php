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
    ) {
    }

    public function catchup(WorkerId $workerId, callable $newEventHandler, ?callable $logAction = null): self
    {
        if (null !== $this->connection) {
            return $this;
        }

        self::deleteSockFile();

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

    public function stopCatchup(): void
    {
        $this->deleteSockFile();

        if ($this->connection instanceof ConnectionInterface) {
            $this->connection->close();
        }
    }

    public function createConnection(): PromiseInterface
    {
        $connector = $this->config->createSecure
            ? self::secureConnector($this->config)
            : new Connector();

        return $connector
            ->connect(
                sprintf(
                    '%s:%d',
                    $this->config->serverHost,
                    $this->config->serverPort
                )
            );
    }

    private static function secureConnector(Config $config): Connector
    {
        $certPath = sprintf('%s/%s.pem', $config->localCertificateDirectory, $config->eventSourcererApplicationId);
        $certKeyPath = sprintf('%s/%s-key.pem', $config->localCertificateDirectory, $config->eventSourcererApplicationId);
        $caPath = sprintf('%s/%s', $config->localCertificateDirectory, $config->cafile);

        return new Connector([
            'tls' => [
                'local_cert'        => $certPath,
                'local_pk'          => $certKeyPath,
                'verify_peer'       => $config->verifyPeer,
                'verify_peer_name'  => $config->verifyPeerName,
                'allow_self_signed' => $config->allowSelfSigned,
                'cafile'            => $caPath,
            ],
        ]);
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

        return str_replace(MessageType::NewEvent->value, '', $matches[0])
                |> trim(...)
                |> (fn ($x) => json_decode($x, true, 512, JSON_THROW_ON_ERROR));
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

    /**
     * @return resource
     */
    public function openSocket()
    {
        $scheme  = $this->config->createSecure ? 'tls' : 'tcp';
        $address = sprintf('%s://%s:%d', $scheme, $this->config->serverHost, $this->config->serverPort);

        $context = stream_context_create();

        if ($this->config->createSecure) {
            $certPath    = sprintf('%s/%s.pem', $this->config->localCertificateDirectory, $this->config->eventSourcererApplicationId);
            $certKeyPath = sprintf('%s/%s-key.pem', $this->config->localCertificateDirectory, $this->config->eventSourcererApplicationId);

            $tlsOptions = [
                'local_cert'        => $certPath,
                'local_pk'          => $certKeyPath,
                'verify_peer'       => $this->config->verifyPeer,
                'verify_peer_name'  => $this->config->verifyPeerName,
                'allow_self_signed' => $this->config->allowSelfSigned,
            ];

            if (null !== $this->config->cafile) {
                $tlsOptions['cafile'] = $this->config->cafile;
            }

            stream_context_set_option($context, ['ssl' => $tlsOptions]);
        }

        $socket = stream_socket_client($address, $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);

        if (false === $socket) {
            throw new \RuntimeException('Could not connect to event sourcerer: ' . $errorMessage, $errorCode);
        }

        return $socket;
    }

    /**
     * @param resource|null $socket An already-open socket to reuse, or null to open a new one.
     */
    public function writeNewEvent(
        StreamId $streamId,
        EventName $eventName,
        EventVersion $eventVersion,
        array $payload,
        ?int $expectedCurrentVersion = 0,
        $socket = null,
    ): void {
        $message = CreateMessage::forWriteNewEvent(
            $streamId,
            $eventName,
            $eventVersion,
            $payload,
            $expectedCurrentVersion,
        );

        if (null !== $this->connection) {
            $this->connection->write($message);

            return;
        }

        $closeAfter = $socket === null;

        if ($closeAfter) {
            $socket = $this->openSocket();
        }

        fwrite($socket, $message->toString());

        if ($closeAfter) {
            fclose($socket);
        }
    }

    public function readStream(StreamId $streamId): \Generator
    {
        $scheme  = $this->config->createSecure ? 'tls' : 'tcp';
        $address = sprintf('%s://%s:%d', $scheme, $this->config->serverHost, $this->config->serverPort);
        $context = stream_context_create();

        if ($this->config->createSecure) {
            $certPath = sprintf('%s/%s.pem', $this->config->localCertificateDirectory, $this->config->eventSourcererApplicationId);
            $certKeyPath = sprintf('%s/%s-key.pem', $this->config->localCertificateDirectory, $this->config->eventSourcererApplicationId);
            $caPath = sprintf('%s/%s', $this->config->localCertificateDirectory, $this->config->cafile);

            $tlsOptions = [
                'local_cert'        => $certPath,
                'local_pk'          => $certKeyPath,
                'verify_peer'       => $this->config->verifyPeer,
                'verify_peer_name'  => $this->config->verifyPeerName,
                'allow_self_signed' => $this->config->allowSelfSigned,
            ];

            if (null !== $this->config->cafile) {
                $tlsOptions['cafile'] = $caPath;
            }

            stream_context_set_option($context, ['ssl' => $tlsOptions]);
        }

        $socket = stream_socket_client($address, $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);

        if (false === $socket) {
            throw new \RuntimeException('Could not connect to event sourcerer: ' . $errorMessage, $errorCode);
        }

        $applicationId = ApplicationId::fromString($this->config->eventSourcererApplicationId);
        $message       = CreateMessage::forReadingStream($streamId, $applicationId);

        fwrite($socket, $message->toString());

        $buffer    = '';
        $separator = MessageMarkup::NewEventParser->value;

        stream_set_blocking($socket, false);

        $idleTimeout  = 0.05;  // seconds of silence before we consider the stream done
        $idleSince    = null;

        while (true) {
            $read   = [$socket];
            $write  = null;
            $except = null;

            $ready = stream_select($read, $write, $except, 0, 50000); // 50ms poll

            if ($ready === false) {
                break;
            }

            if ($ready > 0) {
                $chunk = fread($socket, 8192);

                if (false === $chunk || ('' === $chunk && feof($socket))) {
                    break;
                }

                if ('' !== $chunk) {
                    $idleSince = null;
                    $buffer   .= $chunk;
                    $parts     = explode($separator, $buffer);
                    $buffer    = array_pop($parts);

                    foreach (array_filter($parts) as $event) {
                        yield self::decodeEvent($event);
                    }
                }
            } else {
                // No data available
                if ($idleSince === null) {
                    $idleSince = microtime(true);
                } elseif ((microtime(true) - $idleSince) >= $idleTimeout) {
                    break;
                }
            }
        }

        fclose($socket);
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
            self::deleteSockFile();
            throw MasterConnectionBroken::becauseOfAnError($e->getMessage());
        });

        $connection->on('close', function () {
            self::deleteSockFile();
            throw MasterConnectionBroken::becauseItClosed();
        });

        $connection->on('end', function () {
            self::deleteSockFile();
            throw MasterConnectionBroken::becauseItEnded();
        });
    }
}
