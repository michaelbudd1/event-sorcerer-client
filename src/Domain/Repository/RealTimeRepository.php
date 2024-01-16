<?php

namespace PearTreeWeb\MicroManager\Client\Domain\Repository;

interface RealTimeRepository
{
    public function listenTo(string $stream): iterable;
}
