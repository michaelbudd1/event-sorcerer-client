<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

final readonly class EventPropertyAndValue
{
    public function __construct(public PropertyName $name, public mixed $value) {}
}
