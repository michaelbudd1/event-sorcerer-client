<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

final readonly class InstantiateEventFromArray
{
    /**
     * @param class-string $classFilepath
     * @param array{array{name: string, type: string, value: mixed}}  $properties
     */
    public static function with(string $classFilepath, array $properties): mixed
    {
        return call_user_func(
            sprintf(
                '%s::%s',
                $classFilepath,
                'fromArray'
            ),
            self::mapProperties($properties)
        );
    }

    private static function mapProperties(array $properties): array
    {
        $return = [];

        foreach ($properties as $property) {
            $return[$property['name']] = $property['serializedValue'];
        }

        return $return;
    }
}
