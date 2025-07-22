<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

interface AvailableEvents
{
    public function add(array $event): void;

    public function fetchOne(): ?string;

    public function remove(int $index): void;
}
