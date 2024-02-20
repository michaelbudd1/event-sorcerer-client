<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

trait FulfilICanBeCreatedFromArray
{
    private function __construct(private readonly array $items) {}

    public static function fromArray(array $items): self
    {
        return new self($items);
    }
}
