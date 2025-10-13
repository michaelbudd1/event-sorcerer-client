<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;

interface StreamWorkerManager
{
    public function bucketsForWorkerId(WorkerId $workerId): array;

    /**
     * @param int[] $bucketIndexes
     */
    public function declareWorker(WorkerId $workerId, array $bucketIndexes): void;

    public function detachWorker(WorkerId $workerId, array $bucketIndexes): void;
}
