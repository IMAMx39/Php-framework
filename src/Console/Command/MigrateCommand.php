<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Migration\MigrationRunner;

/**
 * Exécute toutes les migrations en attente.
 *
 * Usage : php bin/console migrate
 */
class MigrateCommand extends AbstractCommand
{
    public function __construct(private readonly MigrationRunner $runner) {}

    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Exécute toutes les migrations en attente';
    }

    public function execute(array $args): int
    {
        $this->title('Migrations');

        $ran = $this->runner->migrate();

        if (empty($ran)) {
            $this->warning('Aucune migration en attente. La base de données est à jour.');

            return 0;
        }

        foreach ($ran as $version) {
            $this->info("  ✓ $version");
        }

        $this->line();
        $this->info(count($ran) . ' migration(s) exécutée(s).');

        return 0;
    }
}
