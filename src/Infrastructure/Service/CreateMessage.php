<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Service;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\ApplicationId;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\Checkpoint;
use PearTreeWeb\EventSourcerer\Client\Domain\Model\StreamId;
use PearTreeWeb\EventSourcerer\Client\Infrastructure\Model\Message;
use PearTreeWebLtd\EventSourcererMessageUtilities\Model\MessagePattern;

final readonly class CreateMessage
{
    public static function forCatchupRequest(
        StreamId $streamId,
        ApplicationId $applicationId,
        ?Checkpoint $checkpoint = null
    ): Message {
        return Message::fromString(
            sprintf(
                MessagePattern::Catchup->value,
                $streamId,
                $applicationId,
                $checkpoint?->toString() ?? ''
            )
        );
    }

    public static function forProvidingIdentity(ApplicationId $applicationId): Message
    {
        return Message::fromString(
            sprintf(
                MessagePattern::ProvideIdentity->value,
                $applicationId
            )
        );
    }

    public static function forAcknowledgement(
        StreamId $streamId,
        ApplicationId $applicationId,
        Checkpoint $checkpoint
    ): Message {
        return Message::fromString(
            sprintf(
                MessagePattern::Acknowledgement->value,
                $streamId,
                $applicationId,
                $checkpoint->toString()
            )
        );
    }
}
