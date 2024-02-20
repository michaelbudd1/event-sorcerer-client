<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

final readonly class Checkpoint implements IsInteger
{
    use FulfilIsInteger;
}
