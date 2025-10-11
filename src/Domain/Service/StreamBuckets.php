<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface StreamBuckets
{
    public function bucketIndexForStreamId(StreamId $streamId): ?int;

    public function assignBucketIndexForStreamId(StreamId $streamId): int;

    public function addEventToBucket(int $index, array $decodedEvent): void;

    public function addStreamToBucket(int $index, StreamId $streamId): void;
}
