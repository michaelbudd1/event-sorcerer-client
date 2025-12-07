<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;

interface WorkerMessages
{
    /**
     * @param array{
     *  allSequence: int,
     *  eventVersion: int,
     *  name: string,
     *  number: int,
     *  payload: array,
     *  stream: string,
     *  occurred: string,
     *  catchupRequestStream: string
     * } $message
     */
    public function addFor(WorkerId $workerId, array $message): void;

    /**
     * @return iterable<array{
     *  allSequence: int,
     *  eventVersion: int,
     *  name: string,
     *  number: int,
     *  payload: array,
     *  stream: string,
     *  occurred: string,
     *  catchupRequestStream: string
     * }>
     */
    public function getFor(WorkerId $workerId): iterable;

    public function removeFor(WorkerId $workerId, int $allSequence): void;

    public function clearFor(WorkerId $workerId): void;
}
