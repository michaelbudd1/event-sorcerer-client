<?php

declare(strict_types=1);

use PearTreeWeb\EventSourcerer\Client\Domain\Model\MessageBucket;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository\BucketedAvailableEvents;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\SharedCacheStreamBuckets;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Service\SharedCacheStreamWorkerManager;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Yaml\Yaml;

final class ClientTest extends TestCase
{
    private const string MOCK_DATA_FILE = '/data/mockEvents.yaml';
    private const string TEST_APPLICATION_ID = 'bf245936-e880-47d4-8b03-7f52b011f9e9';
    private const string TEST_STREAM_1_ID = 'c71ff609-ad7c-44d5-97ba-13dc3bd60345';
    private const string WORKER_1_ID = 'worker1';

    private BucketedAvailableEvents $availableEvents;
    private ApplicationId $applicationId;
    private SharedCacheStreamBuckets $streamBuckets;
    private MessageBucket $bucket1;
    private MessageBucket $bucket2;
    private MessageBucket $bucket3;

    protected function setUp(): void
    {
        $this->bucket1 = self::createMessageBucket();
        $this->bucket2 = self::createMessageBucket();
        $this->bucket3 = self::createMessageBucket();

        $this->streamBuckets = new SharedCacheStreamBuckets(
            new ArrayAdapter(),
            $this->bucket1,
            $this->bucket2,
            $this->bucket3
        );

        $this->availableEvents = new BucketedAvailableEvents($this->streamBuckets);

        $this->applicationId = ApplicationId::fromString(self::TEST_APPLICATION_ID);
    }

    #[Test]
    public function itEvenlyAddsStreamsToBuckets(): void
    {
        $this->addTestEvents();

        $this->assertEquals(2, $this->bucket1->numberOfStreamsWithin());
        $this->assertEquals(1, $this->bucket2->numberOfStreamsWithin());
        $this->assertEquals(1, $this->bucket3->numberOfStreamsWithin());
    }

    #[Test]
    public function itEvenlyAssignsWorkers(): void
    {
        $streamWorkerManager = new SharedCacheStreamWorkerManager(new ArrayAdapter());

        $worker1 = WorkerId::fromString(self::WORKER_1_ID);

        $streamWorkerManager->declareWorker($worker1, $this->streamBuckets->bucketIndexes());
    }

    private function addTestEvents(): void
    {
        foreach (Yaml::parseFile(__DIR__ . self::MOCK_DATA_FILE) as $event) {
            $event['allSequence'] = (int) $event['allSequence'];

            $this->availableEvents->add($this->applicationId, $event);
        }
    }

    private static function createMessageBucket(): MessageBucket
    {
        return new MessageBucket(new ArrayAdapter());
    }
}
