<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

interface CanBeCreatedFromArray
{
    public static function fromArray(array $item): self;
}
