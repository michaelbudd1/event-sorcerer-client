<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface StreamWorkerManager
{
    public function workerForStreamId(StreamId $streamId): WorkerId;

    /**
     * @param int[] $bucketIndexes
     */
    public function declareWorker(WorkerId $workerId, array $bucketIndexes): void;
}
