<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Exception;

final class CouldNotEstablishLocalConnection extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(
            sprintf(
                'Could not establish local connection because: "%s"', $reason
            )
        );
    }
}
