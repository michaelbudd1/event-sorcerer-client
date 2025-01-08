<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

interface CatchupHandler
{
    public function handleReceivedEvent(callable $eventHandler): callable;
}
