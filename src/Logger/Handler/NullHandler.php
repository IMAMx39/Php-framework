<?php

declare(strict_types=1);

namespace Framework\Logger\Handler;

/** Ignore silencieusement tous les logs. Utile en tests. */
class NullHandler implements HandlerInterface
{
    public function handle(array $record): void {}

    public function isHandling(string $level): bool
    {
        return true;
    }
}
