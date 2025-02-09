<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Model;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\FulfilIsString;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\IsString;

final class Message implements IsString
{
    use FulfilIsString;
}
