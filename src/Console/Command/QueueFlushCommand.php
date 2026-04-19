<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Queue\QueueInterface;

class QueueFlushCommand extends AbstractCommand
{
    public function __construct(private readonly QueueInterface $queue) {}

    public function getName(): string        { return 'queue:flush'; }
    public function getDescription(): string { return 'Vide la queue (supprime tous les jobs en attente)'; }

    public function execute(array $args): int
    {
        $this->title('queue:flush');

        $size = $this->queue->size();

        if ($size === 0) {
            $this->info('  Queue déjà vide.');
            return 0;
        }

        $this->queue->flush();
        $this->info("  {$size} job(s) supprimé(s).");

        return 0;
    }
}
