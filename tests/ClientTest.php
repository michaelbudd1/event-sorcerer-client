<?php

declare(strict_types=1);

use PearTreeWeb\EventSourcerer\Client\Infrastructure\Client;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Config;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository\LockedAvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository\SharedProcessCommunicationCache;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\SymfonyLockStreamLocker;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationType;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Yaml\Yaml;

final class ClientTest extends TestCase
{
    private const string MOCK_DATA_FILE = '/data/mockEvents.yaml';
    private const string TEST_APPLICATION_ID = 'bf245936-e880-47d4-8b03-7f52b011f9e9';
    private const string TEST_STREAM_1_ID = 'c71ff609-ad7c-44d5-97ba-13dc3bd60345';

    private ApplicationId $applicationId;
    private LockedAvailableEvents $availableEvents;
    private SymfonyLockStreamLocker $streamLocker;
    private SharedProcessCommunicationCache $sharedProcessCommunicationCache;

    protected function setUp(): void
    {
        $this->applicationId = ApplicationId::fromString(self::TEST_APPLICATION_ID);

        $this->streamLocker = new SymfonyLockStreamLocker(
            new LockFactory(
                new InMemoryStore()
            ),
            new ArrayAdapter()
        );

        $this->availableEvents = new LockedAvailableEvents(new ArrayAdapter(), $this->streamLocker);

        $this->sharedProcessCommunicationCache = new SharedProcessCommunicationCache(new ArrayAdapter());
    }

    protected function tearDown(): void
    {
        $this->availableEvents->removeAll($this->applicationId);
        $this->streamLocker->release(StreamId::fromString(self::TEST_STREAM_1_ID));
        $this->sharedProcessCommunicationCache->removeAll();
    }

    #[Test]
    public function itDoesASimpleLockOnOneStream(): void
    {
        $symfonyLocker = new SymfonyLockStreamLocker(
            new LockFactory(new InMemoryStore()),
            new ArrayAdapter()
        );

        $streamId = StreamId::fromString(self::TEST_STREAM_1_ID);

        $this->assertTrue($symfonyLocker->lock($streamId));
        $this->assertFalse($symfonyLocker->lock($streamId));
    }

    #[Test]
    public function itFetchesMessagesInCorrectOrder(): void
    {
        $this->addTestEvents();

        $client = new Client(
            new Config(
                ApplicationType::Unknown,
                '',
                '',
                9999,
                self::TEST_APPLICATION_ID
            ),
            $this->availableEvents,
            $this->sharedProcessCommunicationCache
        );

        $message1 = $client->fetchOneMessage();
        $message2 = $client->fetchOneMessage();
        $message3 = $client->fetchOneMessage();

        $this->assertIsArray($message1);
        $this->assertNull($message2);
        $this->assertNull($message3);
    }

    private function addTestEvents(): void
    {
        foreach (Yaml::parseFile(__DIR__ . self::MOCK_DATA_FILE) as $event) {
            $event['allSequence'] = (int) $event['allSequence'];

            $this->availableEvents->add($this->applicationId, $event);
        }
    }
}
