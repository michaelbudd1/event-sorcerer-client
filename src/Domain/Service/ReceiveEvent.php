<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Service;

interface ReceiveEvent
{
    public function handleReceivedEvent(callable $eventHandler): callable;
}
