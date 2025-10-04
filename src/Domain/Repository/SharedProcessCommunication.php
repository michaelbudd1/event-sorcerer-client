<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Domain\Repository;

interface SharedProcessCommunication
{
    public function catchupInProgress(): bool;

    public function flagCatchupIsInProgress(): void;

    public function flagCatchupIsNotInProgress(): void;

    /**
     * @return int[]
     */
    public function eventsBeingProcessedCurrently(): array;

    public function messageIsAlreadyBeingProcessed(int $allStreamCheckpoint): bool;
    
    public function addEventCurrentlyBeingProcessed(int $allStreamCheckpoint): void;
}
