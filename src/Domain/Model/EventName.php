<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\FulfilIsString;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\IsString;

final class EventName implements IsString
{
    use FulfilIsString;
}
