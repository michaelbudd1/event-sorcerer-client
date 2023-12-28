<?php

declare(strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Model;

final readonly class Stream
{
    public function __construct(public StreamId $id, public array $events) {}
}
