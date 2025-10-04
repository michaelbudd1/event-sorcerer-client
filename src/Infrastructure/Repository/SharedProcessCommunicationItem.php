<?php

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

enum SharedProcessCommunicationItem: string
{
    case CatchupInProgress = 'catchupInProgress';
}
