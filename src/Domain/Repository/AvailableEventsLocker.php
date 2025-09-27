<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

interface AvailableEventsLocker
{
    public function lock(): bool;

    public function release(): void;
}
