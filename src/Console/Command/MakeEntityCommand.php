<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Console\Generator;

/**
 * Génère une entité PHP et son repository.
 *
 * Usage :
 *   php bin/console make:entity Product
 *   php bin/console make:entity BlogPost      → table blog_posts (snake_case + pluriel)
 */
class MakeEntityCommand extends AbstractCommand
{
    public function __construct(private readonly Generator $generator) {}

    public function getName(): string        { return 'make:entity'; }
    public function getDescription(): string { return 'Génère une entité et son repository'; }

    public function execute(array $args): int
    {
        $name = trim($args[0] ?? '');

        if ($name === '') {
            $this->error('Usage : php bin/console make:entity <NomDeLEntité>');
            return 1;
        }

        // Normalise en PascalCase
        $className = ucfirst($name);
        $table     = $this->toSnakePlural($className);

        $entityPath     = "app/Entity/{$className}.php";
        $repositoryPath = "app/Repository/{$className}Repository.php";

        $this->title("make:entity {$className}");

        // ── Entité ────────────────────────────────────────────────────
        $entityStub = $this->entityStub($className, $table);

        if (!$this->generator->write($entityPath, $entityStub)) {
            $this->warning("  [skip] {$entityPath} existe déjà.");
        } else {
            $this->info("  [créé] {$entityPath}");
        }

        // ── Repository ────────────────────────────────────────────────
        $repositoryStub = $this->repositoryStub($className);

        if (!$this->generator->write($repositoryPath, $repositoryStub)) {
            $this->warning("  [skip] {$repositoryPath} existe déjà.");
        } else {
            $this->info("  [créé] {$repositoryPath}");
        }

        $this->line();
        $this->line("  N'oublie pas de créer la migration :");
        $this->line("  \033[36mphp bin/console make:migration Create" . $this->pluralize($className) . "Table\033[0m");
        $this->line();

        return 0;
    }

    // ------------------------------------------------------------------
    // Stubs
    // ------------------------------------------------------------------

    private function entityStub(string $className, string $table): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Entity;

        use App\\Repository\\{$className}Repository;
        use Framework\ORM\Attribute\Column;
        use Framework\ORM\Attribute\Entity;
        use Framework\ORM\Attribute\GeneratedValue;
        use Framework\ORM\Attribute\Id;

        #[Entity(table: '{$table}', repositoryClass: {$className}Repository::class)]
        class {$className}
        {
            #[Id]
            #[GeneratedValue]
            #[Column(type: 'integer')]
            private ?int \$id = null;

            #[Column(name: 'created_at', type: 'string', nullable: true)]
            private ?string \$createdAt = null;

            public function __construct()
            {
                \$this->createdAt = (new \\DateTimeImmutable())->format('Y-m-d H:i:s');
            }

            public function getId(): ?int        { return \$this->id; }
            public function getCreatedAt(): ?string { return \$this->createdAt; }
        }
        PHP;
    }

    private function repositoryStub(string $className): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Repository;

        use App\Entity\\{$className};
        use Framework\ORM\AbstractRepository;

        class {$className}Repository extends AbstractRepository
        {
            protected function getEntityClass(): string
            {
                return {$className}::class;
            }
        }
        PHP;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** BlogPost → blog_posts */
    private function toSnakePlural(string $className): string
    {
        $snake = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($className)));
        return $this->pluralize($snake);
    }

    private function pluralize(string $word): string
    {
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }

        if (preg_match('/(s|sh|ch|x|z)$/', $word)) {
            return $word . 'es';
        }

        return $word . 's';
    }
}
