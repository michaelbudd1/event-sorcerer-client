<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Exception;

final class CannotFetchMessages extends \RuntimeException
{
    public static function beforeConnectionHasBeenEstablished(): self
    {
        return new self(
            'Cannot fetch messages as no connection has been established'
        );
    }
}
