<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

final readonly class Config
{
    public function __construct(
        public string $serverHost,
        public string $serverUrl,
        public int $serverPort,
        public string $eventSourcererApplicationId
    ) {}
}
