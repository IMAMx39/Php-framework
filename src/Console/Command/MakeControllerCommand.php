<?php

declare(strict_types=1);

namespace Framework\Console\Command;

use Framework\Console\AbstractCommand;
use Framework\Console\Generator;

/**
 * Génère un contrôleur avec une action index.
 *
 * Usage :
 *   php bin/console make:controller Home
 *   php bin/console make:controller ProductController  (le suffixe Controller est optionnel)
 */
class MakeControllerCommand extends AbstractCommand
{
    public function __construct(private readonly Generator $generator) {}

    public function getName(): string        { return 'make:controller'; }
    public function getDescription(): string { return 'Génère un contrôleur avec une action index'; }

    public function execute(array $args): int
    {
        $name = trim($args[0] ?? '');

        if ($name === '') {
            $this->error('Usage : php bin/console make:controller <NomDuContrôleur>');
            return 1;
        }

        // Normalise — retire le suffixe Controller s'il est déjà là, puis le rajoute
        $baseName       = ucfirst(preg_replace('/Controller$/i', '', $name));
        $className      = "{$baseName}Controller";
        $routePrefix    = '/' . strtolower($baseName);
        $routeName      = strtolower($baseName);
        $path           = "app/Controller/{$className}.php";

        $this->title("make:controller {$className}");

        $stub = $this->controllerStub($className, $routePrefix, $routeName);

        if (!$this->generator->write($path, $stub)) {
            $this->warning("  [skip] {$path} existe déjà.");
            return 1;
        }

        $this->info("  [créé] {$path}");
        $this->line();
        $this->line("  Route enregistrée : GET {$routePrefix} → {$routeName}.index");
        $this->line();

        return 0;
    }

    // ------------------------------------------------------------------
    // Stub
    // ------------------------------------------------------------------

    private function controllerStub(string $className, string $routePrefix, string $routeName): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Controller;

        use Framework\Controller\AbstractController;
        use Framework\Http\Request;
        use Framework\Http\Response;
        use Framework\ORM\EntityManager;
        use Framework\Routing\Attribute\Route;

        class {$className} extends AbstractController
        {
            public function __construct(
                private readonly EntityManager \$em,
            ) {}

            #[Route('{$routePrefix}', name: '{$routeName}.index', methods: ['GET'])]
            public function index(Request \$request): Response
            {
                return \$this->render('{$routeName}/index.html.twig', [
                    // 'items' => \$this->em->getRepository(MyEntity::class)->findAll(),
                ]);
            }
        }
        PHP;
    }
}
