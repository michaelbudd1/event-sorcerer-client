<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

final readonly class InFlightKey
{
    public function __construct(
        public ApplicationId $applicationId,
        public StreamId $streamId
    ) {}

    public function toString(): string
    {
        return sprintf(
            '%s-%s',
            $this->applicationId,
            $this->streamId
        );
    }
}
