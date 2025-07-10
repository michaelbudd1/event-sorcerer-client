<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

interface ProcessReadModelEvent
{
    /**
     * @param array{name: string, number: int, payload: array, occurred: string} $event
     */
    public function handleEvent(array $event): void;
}
