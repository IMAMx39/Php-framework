<?php

declare(strict_types=1);

namespace Framework\Console;

interface CommandInterface
{
    /** Nom de la commande — ex: "migrate", "migrate:rollback" */
    public function getName(): string;

    /** Description affichée dans la liste des commandes */
    public function getDescription(): string;

    /**
     * Exécute la commande.
     *
     * @param  string[] $args Arguments passés en ligne de commande (hors nom de commande).
     * @return int       Code de sortie (0 = succès, 1 = erreur).
     */
    public function execute(array $args): int;
}
