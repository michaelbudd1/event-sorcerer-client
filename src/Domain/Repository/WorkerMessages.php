<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

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
    public function add(array $message): void;

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
    public function get(): iterable;
}
