<?php

declare(strict_types=1);

namespace Framework\Queue;

interface JobInterface
{
    /**
     * Exécute le job.
     * Les dépendances de handle() sont résolues automatiquement par le container.
     */
    // handle(...$dependencies): void
}
