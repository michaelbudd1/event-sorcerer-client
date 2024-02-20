<?php

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

interface IsInteger
{
    public static function fromInt(int $value): self;

    public function toInt(): int;
}
