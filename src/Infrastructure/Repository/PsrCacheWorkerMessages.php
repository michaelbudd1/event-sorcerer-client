<?php

declare(strict_types=1);

namespace PearTreeWeb\EventSourcerer\Client\Infrastructure\Repository;

use PearTreeWeb\EventSourcerer\Client\Domain\Model\WorkerId;
use PearTreeWeb\EventSourcerer\Client\Domain\Repository\WorkerMessages;
use Psr\Cache\CacheItemPoolInterface;

final readonly class PsrCacheWorkerMessages implements WorkerMessages
{
    private const string MESSAGES_KEY_PREFIX = 'messages';

    public function __construct(private CacheItemPoolInterface $messages) {}

    public function addFor(WorkerId $workerId, array $message): void
    {
        $messagesCacheItem = $this->messages->getItem(self::cacheKey($workerId));

        $messages = $messagesCacheItem->get() ?? [];

        $messages[$message['allSequence']] = $message;

        $messagesCacheItem->set($messages);

        $this->messages->save($messagesCacheItem);
    }

    public function getFor(WorkerId $workerId): iterable
    {
        foreach ($this->messages->getItem(self::cacheKey($workerId))->get() ?? [] as $message) {
            yield $message;
        }
    }

    public function clearFor(WorkerId $workerId): void
    {
        $this->messages->deleteItem(self::cacheKey($workerId));
    }

    private static function cacheKey(WorkerId $workerId): string
    {
        return sprintf(
            '%s-%s',
            $workerId,
            self::MESSAGES_KEY_PREFIX
        );
    }
}
