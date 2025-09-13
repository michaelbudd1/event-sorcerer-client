<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWeb\EventSourcerer\Client\Infrastructure\Model\WorkerId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use Psr\Cache\CacheItemInterface;

interface AvailableEvents
{
    public function add(ApplicationId $applicationId, array $event): void;

    public function fetchOne(ApplicationId $applicationId): ?array;

    public function remove(CacheItemInterface $availableEvents, array $event): void;

    public function count(ApplicationId $applicationId): int;
}
