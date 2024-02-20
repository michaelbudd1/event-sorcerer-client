<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Exception;

final class CouldNotStoreEventException extends \RuntimeException
{
    public static function with(string $error, array $event): self
    {
        return new self(
            sprintf(
                'Could not store event with payload "%s". Error received from server: "%s"',
                $error,
                \json_encode($event)
            )
        );
    }
}
