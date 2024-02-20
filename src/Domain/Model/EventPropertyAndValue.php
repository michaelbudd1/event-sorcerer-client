<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

final readonly class EventPropertyAndValue
{
    public function __construct(public PropertyName $name, public mixed $value) {}
}
