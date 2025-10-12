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
    private const string WORKER_1_ID = 'worker1';
    private const string WORKER_2_ID = 'worker2';
    private const string WORKER_3_ID = 'worker3';

    private BucketedAvailableEvents $availableEvents;
    private ApplicationId $applicationId;
    private SharedCacheStreamBuckets $streamBuckets;
    private MessageBucket $bucket1;
    private MessageBucket $bucket2;
    private MessageBucket $bucket3;
    private SharedCacheStreamWorkerManager $streamWorkerManager;
    private WorkerId $worker1;
    private WorkerId $worker2;
    private WorkerId $worker3;


    protected function setUp(): void
    {
        $this->bucket1 = self::createMessageBucket();
        $this->bucket2 = self::createMessageBucket();
        $this->bucket3 = self::createMessageBucket();

        $this->worker1 = WorkerId::fromString(self::WORKER_1_ID);
        $this->worker2 = WorkerId::fromString(self::WORKER_2_ID);
        $this->worker3 = WorkerId::fromString(self::WORKER_3_ID);

        $this->streamBuckets = new SharedCacheStreamBuckets(
            new ArrayAdapter(),
            $this->bucket1,
            $this->bucket2,
            $this->bucket3
        );

        $this->streamWorkerManager = new SharedCacheStreamWorkerManager(new ArrayAdapter());

        $this->availableEvents = new BucketedAvailableEvents($this->streamBuckets, $this->streamWorkerManager);

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
    public function itAssignsCorrectBucketToWorker(): void
    {
        $this->addTestEvents();

        $this->streamWorkerManager->declareWorker($this->worker1, $this->streamBuckets->bucketIndexes());
        $this->streamWorkerManager->declareWorker($this->worker2, $this->streamBuckets->bucketIndexes());
        $this->streamWorkerManager->declareWorker($this->worker3, $this->streamBuckets->bucketIndexes());

        $assignedBucket = $this->streamWorkerManager->bucketForWorkerId($this->worker1);

        $this->assertEquals(0, $assignedBucket);
    }

    #[Test]
    public function itEvenlyAssignsWorkers(): void
    {
        $this->addTestEvents();

        $this->streamWorkerManager->declareWorker($this->worker1, $this->streamBuckets->bucketIndexes());
        $this->streamWorkerManager->declareWorker($this->worker2, $this->streamBuckets->bucketIndexes());
        $this->streamWorkerManager->declareWorker($this->worker3, $this->streamBuckets->bucketIndexes());

        $event = $this->availableEvents->fetchOne($this->worker1, $this->applicationId);

        $this->assertEquals(1, $event['allSequence']);
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
