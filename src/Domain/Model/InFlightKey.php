<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use PearTreeWeb\EventSourcerer\Common\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Common\Model\StreamId;

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
