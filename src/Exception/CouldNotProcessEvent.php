<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Exception;

final class CouldNotProcessEvent extends \RuntimeException
{
    public static function eventOutOfSequence(int $expected, int $actual): self
    {
        return new self(
            sprintf(
                'Could not process event, expected "%d" but received "%d"',
                $expected,
                $actual
            )
        );
    }
}
