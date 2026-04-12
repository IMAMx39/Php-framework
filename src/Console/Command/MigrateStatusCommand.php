<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Migration\MigrationRunner;

/**
 * Affiche le statut de toutes les migrations.
 *
 * Usage : php bin/console migrate:status
 */
class MigrateStatusCommand extends AbstractCommand
{
    public function __construct(private readonly MigrationRunner $runner) {}

    public function getName(): string
    {
        return 'migrate:status';
    }

    public function getDescription(): string
    {
        return 'Affiche le statut de toutes les migrations';
    }

    public function execute(array $args): int
    {
        $this->title('Statut des migrations');

        $statuses = $this->runner->status();

        if (empty($statuses)) {
            $this->warning('Aucune migration trouvée dans le dossier migrations/.');

            return 0;
        }

        $rows = array_map(fn ($s) => [
            'Version'     => $s['version'],
            'Description' => $s['description'] ?: '—',
            'Statut'      => $s['status'] === 'Exécutée'
                ? "\033[32m✓ " . $s['status'] . "\033[0m"
                : "\033[33m⏳ " . $s['status'] . "\033[0m",
            'Batch'       => $s['batch'] ?? '—',
            'Exécutée le' => $s['executed_at'] ?? '—',
        ], $statuses);

        $this->table(['Version', 'Description', 'Statut', 'Batch', 'Exécutée le'], $rows);

        $total   = count($statuses);
        $done    = count(array_filter($statuses, fn ($s) => $s['status'] === 'Exécutée'));
        $pending = $total - $done;

        $this->line();
        $this->line("Total : $total  |  \033[32mExécutées : $done\033[0m  |  \033[33mEn attente : $pending\033[0m");

        return 0;
    }
}
