<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Exception;

final class NoRepositoryAssignedToHandleEvent extends \RuntimeException
{
    public static function withName(string $event): self
    {
        return new self(
            sprintf(
              'There is no read model repository assigned to handle event "%s"',
                $event
            )
        );
    }
}
