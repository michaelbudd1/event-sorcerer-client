<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

interface InFlightEvents
{
    public function addEventForApplicationId(
        ApplicationId $applicationId,
        array $event
    ): void;

    public function containsEventsForApplicationIdAndStreamId(
        ApplicationId $applicationId,
        StreamId $streamId
    ): bool;

    public function forApplicationIdAndStreamId(
        ApplicationId $applicationId,
        StreamId $streamId
    ): iterable;

    public function removeEventForApplicationId(
        ApplicationId $applicationId,
        array $event
    ): void;
}
