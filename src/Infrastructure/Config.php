<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

final readonly class Config
{
    public function __construct(public string $serverUrl, public int $serverPort) {}
}
