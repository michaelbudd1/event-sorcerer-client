<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

trait FulfilIsString
{
    private function __construct(private readonly string $value) {}

    public static function create(?string $value): self
    {
        return $value
            ? new self($value)
            : new self(IsString::NULL_REPRESENTATION);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
