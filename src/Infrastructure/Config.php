<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationType;

final readonly class Config
{
    public function __construct(
        public ApplicationType $applicationType,
        public string $serverHost,
        public string $serverUrl,
        public int $serverPort,
        public string $eventSourcererApplicationId
    ) {}
}
