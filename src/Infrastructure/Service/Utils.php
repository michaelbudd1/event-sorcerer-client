<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWebLtd\EventSourcererMessageUtilities\Model\ApplicationId;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\StreamId;

final class Utils
{
    public static function inFlightKey(
        ApplicationId $applicationId,
        StreamId $streamId
    ): string {
        return sprintf(
            '%s-%s',
            $applicationId,
            $streamId
        );
    }
}
