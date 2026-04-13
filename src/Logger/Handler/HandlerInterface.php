<?php

declare(strict_types=1);

namespace Framework\Logger\Handler;

interface HandlerInterface
{
    /** Traite un enregistrement de log. */
    public function handle(array $record): void;

    /** Indique si ce handler accepte le niveau donné. */
    public function isHandling(string $level): bool;
}
