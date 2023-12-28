<?php

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

interface IsInteger
{
    public static function fromInt(int $value): self;

    public function toInt(): int;
}
