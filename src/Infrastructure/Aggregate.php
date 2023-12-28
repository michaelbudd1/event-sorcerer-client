<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Infrastructure;

use PearTreeWeb\MicroManager\Client\Domain\Aggregate as AggregateInterface;
use PearTreeWeb\MicroManager\Client\Infrastructure\Attribute\EventProperty;
use ReflectionClass;

abstract readonly class Aggregate implements AggregateInterface
{
    private function resolveEventProperties(string $eventClass): array
    {
        $reflectionClass = new ReflectionClass($eventClass);

        $eventProperties = [];

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(EventProperty::class);

            foreach ($attributes as $attribute) {
                $event = $attribute->newInstance();

//                $eventProperties[] = [
//                    // The event that's configured on the attribute
//                    $listener->event,
//
//                    // The listener for this event
//                    [$eventClass, $method->getName()],
//                ];
            }
        }

        return $eventProperties;
    }
}
