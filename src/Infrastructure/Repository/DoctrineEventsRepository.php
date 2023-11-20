<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Infrastructure\Repository;

use PearTreeWeb\MicroManager\Client\Domain\Model\Checkpoint;
use PearTreeWeb\MicroManager\Client\Domain\Model\StreamId;
use PearTreeWeb\MicroManager\Client\Domain\Repository\Events;

final class DoctrineEventsRepository implements Events
{
    public function for(StreamId $id, Checkpoint $checkpoint): array
    {
        return [
            [
                'name' => 'ITEM_ADDED_TO_BASKET',
                'properties' => [
                    'type'  => 'number',
                    'value' => 1,
                ]
            ],
        ];
    }
}
