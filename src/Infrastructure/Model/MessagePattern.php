<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Model;

enum MessagePattern: string
{
    case Catchup     = 'CATCHUP %s %s %s';
    case Acknowledge = 'ACKNOWLEDGE %s %d';
}
