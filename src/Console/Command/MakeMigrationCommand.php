<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Console\Generator;

/**
 * Génère un fichier de migration versionné.
 *
 * Usage :
 *   php bin/console make:migration CreateProductsTable
 *   php bin/console make:migration AddPriceToProducts
 */
class MakeMigrationCommand extends AbstractCommand
{
    public function __construct(private readonly Generator $generator) {}

    public function getName(): string        { return 'make:migration'; }
    public function getDescription(): string { return 'Génère un fichier de migration vide'; }

    public function execute(array $args): int
    {
        $description = trim($args[0] ?? '');

        if ($description === '') {
            $this->error('Usage : php bin/console make:migration <Description>');
            $this->line('Exemples :');
            $this->line('  php bin/console make:migration CreateProductsTable');
            $this->line('  php bin/console make:migration AddPriceToProducts');
            return 1;
        }

        // Normalise : retire les espaces, force PascalCase
        $description = str_replace(' ', '', ucwords($description));
        $version     = (new \DateTimeImmutable())->format('YmdHis');
        $className   = "Version{$version}{$description}";
        $path        = "migrations/{$className}.php";

        $this->title("make:migration {$description}");

        $stub = $this->migrationStub($className, $description);

        if (!$this->generator->write($path, $stub)) {
            $this->warning("  [skip] {$path} existe déjà.");
            return 1;
        }

        $this->info("  [créé] {$path}");
        $this->line();
        $this->line("  Lance la migration :");
        $this->line("  \033[36mphp bin/console migrate\033[0m");
        $this->line();

        return 0;
    }

    // ------------------------------------------------------------------
    // Stub
    // ------------------------------------------------------------------

    private function migrationStub(string $className, string $description): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Migrations;

        use Framework\Migration\AbstractMigration;

        class {$className} extends AbstractMigration
        {
            public function getDescription(): string
            {
                return '{$description}';
            }

            public function up(): void
            {
                // \$this->execute('CREATE TABLE ...');
            }

            public function down(): void
            {
                // \$this->execute('DROP TABLE ...');
            }
        }
        PHP;
    }
}
