<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Model;

use PearTreeWeb\EventSourcerer\Common\Model\FulfilIsString;
use PearTreeWeb\EventSourcerer\Common\Model\IsString;

final class PropertyName implements IsString
{
    use FulfilIsString;
}
