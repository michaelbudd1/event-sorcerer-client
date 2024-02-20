<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

interface CanBeCreatedFromArray
{
    public static function fromArray(array $item): self;
}
