<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

interface AvailableEvents
{
    public function add(array $event): void;

    public function fetchOne(): ?array;

    public function remove(int $index): void;
}
