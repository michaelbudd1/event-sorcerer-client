<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

trait FulfilIsInteger
{
    private function __construct(readonly int $value) {}

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function increment(): self
    {
        return new self($this->value + 1);
    }
}
