<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use Psr\Cache\CacheItemInterface;

interface AvailableEvents
{
    public function add(ApplicationId $applicationId, array $event): void;

    public function fetchOne(ApplicationId $applicationId): ?array;

    public function remove(CacheItemInterface $availableEvents, int $allSequenceIndex): void;
}
