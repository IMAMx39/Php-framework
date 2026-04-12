<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Migration\MigrationRunner;

/**
 * Annule le dernier lot de migrations (batch).
 *
 * Usage : php bin/console migrate:rollback
 */
class MigrateRollbackCommand extends AbstractCommand
{
    public function __construct(private readonly MigrationRunner $runner) {}

    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Annule le dernier lot de migrations';
    }

    public function execute(array $args): int
    {
        $this->title('Rollback');

        $rolledBack = $this->runner->rollback();

        if (empty($rolledBack)) {
            $this->warning('Aucune migration à annuler.');

            return 0;
        }

        foreach ($rolledBack as $version) {
            $this->warning("  ✗ $version annulée");
        }

        $this->line();
        $this->info(count($rolledBack) . ' migration(s) annulée(s).');

        return 0;
    }
}
