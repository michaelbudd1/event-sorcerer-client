<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

final readonly class Stream
{
    public function __construct(
        public StreamId $id,
        public StreamName $name,
        public array $events
    ) {}
}
