<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Infrastructure\Attribute;

use Attribute;

#[Attribute]
final readonly class EventProperty
{
    public function __construct(
        public string $propertyName,
        public string $classFilepath
    ) {}
}
