<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

enum ConnectionUpdate
{
    case ConnectionClosed;
    case ConnectionEnded;
    case ConnectionErrored;
}
