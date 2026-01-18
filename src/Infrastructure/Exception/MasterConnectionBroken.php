<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Exception;

final class MasterConnectionBroken extends \RuntimeException
{
    public static function becauseOfAnError(string $message): self
    {
        return new self(
            sprintf(
                '%s. An error occurred: %s',
                self::masterConnectionBrokenMessage(),
                $message
            )
        );
    }

    public static function becauseItEnded(): self
    {
        return new self(
            sprintf(
                '%s. It ended somewhere/somehow',
                self::masterConnectionBrokenMessage(),
            )
        );
    }

    public static function becauseItClosed(): self
    {
        return new self(
            sprintf(
                '%s. It closed somewhere/somehow',
                self::masterConnectionBrokenMessage(),
            )
        );
    }

    private static function masterConnectionBrokenMessage(): string
    {
        return 'The master connection is broken';
    }
}
