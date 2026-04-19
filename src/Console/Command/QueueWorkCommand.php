<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Queue\Worker;

class QueueWorkCommand extends AbstractCommand
{
    public function __construct(private readonly Worker $worker) {}

    public function getName(): string        { return 'queue:work'; }
    public function getDescription(): string { return 'Démarre le worker — traite les jobs en continu'; }

    public function execute(array $args): int
    {
        $once = in_array('--once', $args, strict: true);

        $this->title($once ? 'queue:work --once' : 'queue:work');

        if ($once) {
            $processed = $this->worker->processOne();
            $this->info($processed ? '  [ok] 1 job traité.' : '  [vide] Aucun job en attente.');
            return 0;
        }

        $this->info('  Worker démarré. Ctrl+C pour arrêter.');
        $this->line();

        $count = 0;

        $this->worker->work(
            sleep: 1,
            onIdle: function () use (&$count): void {
                // Affiche un point toutes les 5 secondes pour montrer que le worker tourne
                static $ticks = 0;
                if (++$ticks % 5 === 0) {
                    echo '.';
                    flush();
                }
            },
        );

        return 0;
    }
}
