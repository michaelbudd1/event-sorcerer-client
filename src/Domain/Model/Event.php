<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

interface Event
{
    public static function name(): string;
}
