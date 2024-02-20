<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

interface RealTimeRepository
{
    public function listenTo(string $stream): iterable;
}
