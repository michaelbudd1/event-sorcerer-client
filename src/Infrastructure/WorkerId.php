<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Model;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\FulfilIsString;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\IsString;

final class WorkerId implements IsString
{
    use FulfilIsString;
}
