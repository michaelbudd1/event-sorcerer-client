<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface StreamLocker
{
    public function lock(StreamId $streamId): bool;

    public function release(StreamId $streamId): void;

    public function isLocked(StreamId $streamId): bool;
}
