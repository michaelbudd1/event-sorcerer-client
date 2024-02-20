<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

final readonly class StreamId implements IsString
{
    use FulfilIsString;
}
