<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

interface IsString
{
    public const NULL_REPRESENTATION = '';

    public static function fromString(string $value): self;

    public function toString(): string;
}
