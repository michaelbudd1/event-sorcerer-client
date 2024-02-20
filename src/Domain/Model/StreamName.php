<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

final class StreamName implements IsString
{
    use FulfilIsString;
}
