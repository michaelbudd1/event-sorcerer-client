<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

trait FulfilIsString
{
    private function __construct(private readonly string $value) {}

    public static function create(?string $value): self
    {
        self::validate($value);

        return $value
            ? new self($value)
            : new self(IsString::NULL_REPRESENTATION);
    }

    protected static function validate(?string $value): void
    {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
