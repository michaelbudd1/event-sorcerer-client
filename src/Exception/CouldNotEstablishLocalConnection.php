<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Exception;

final class CouldNotEstablishLocalConnection extends \RuntimeException
{
    public static function because(string $reason, int $code): self
    {
        return new self(
            sprintf(
                'Could not establish local connection because: "%s". Error code: %d', $reason, $code
            )
        );
    }
}
