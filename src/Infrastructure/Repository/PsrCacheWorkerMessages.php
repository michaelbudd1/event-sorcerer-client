<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Repository\WorkerMessages;
use Psr\Cache\CacheItemPoolInterface;

final readonly class PsrCacheWorkerMessages implements WorkerMessages
{
    private const string MESSAGES_KEY = 'messages';

    public function __construct(private CacheItemPoolInterface $messages) {}

    public function add(array $message): void
    {
        $messagesCacheItem = $this->messages->getItem(self::MESSAGES_KEY);

        $messages = $messagesCacheItem->get() ?? [];

        $messages[$message['allSequence']] = $message;

        $messagesCacheItem->set($messages);

        $this->messages->save($messagesCacheItem);
    }

    public function get(): iterable
    {
        foreach ($this->messages->getItem(self::MESSAGES_KEY)->get() ?? [] as $message) {
            yield $message;
        }
    }
}
