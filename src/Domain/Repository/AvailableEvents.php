<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;

interface AvailableEvents
{
    public function add(ApplicationId $applicationId, array $event): void;

    public function fetchOne(ApplicationId $applicationId): ?array;
}
