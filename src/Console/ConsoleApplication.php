<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\Container\Container;

/**
 * Point d'entrée de la console CLI.
 *
 * Enregistre les commandes et dispatch vers la bonne commande
 * en fonction des arguments argv.
 */
class ConsoleApplication
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    public function __construct(private readonly Container $container) {}

    // ------------------------------------------------------------------
    // Enregistrement
    // ------------------------------------------------------------------

    public function add(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    // ------------------------------------------------------------------
    // Exécution
    // ------------------------------------------------------------------

    /**
     * @param string[] $argv Arguments bruts de la ligne de commande.
     */
    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? null;
        $args        = array_slice($argv, 2);

        if ($commandName === null || in_array($commandName, ['help', '--help', '-h'], true)) {
            $this->printHelp();

            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            echo "\033[31mCommande inconnue : « $commandName »\033[0m" . PHP_EOL;
            $this->printHelp();

            return 1;
        }

        try {
            return $this->commands[$commandName]->execute($args);
        } catch (\Throwable $e) {
            echo "\033[31m[ERREUR] " . $e->getMessage() . "\033[0m" . PHP_EOL;

            return 1;
        }
    }

    // ------------------------------------------------------------------
    // Aide
    // ------------------------------------------------------------------

    private function printHelp(): void
    {
        echo PHP_EOL;
        echo "\033[36m┌─────────────────────────────┐\033[0m" . PHP_EOL;
        echo "\033[36m│  \033[1mPHP Framework — Console\033[0m\033[36m    │\033[0m" . PHP_EOL;
        echo "\033[36m└─────────────────────────────┘\033[0m" . PHP_EOL;
        echo PHP_EOL;
        echo "\033[33mUsage :\033[0m  php bin/console <commande>" . PHP_EOL;
        echo PHP_EOL;
        echo "\033[33mCommandes disponibles :\033[0m" . PHP_EOL;

        foreach ($this->commands as $name => $command) {
            printf("  \033[32m%-25s\033[0m %s\n", $name, $command->getDescription());
        }

        echo PHP_EOL;
    }
}
