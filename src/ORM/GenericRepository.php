<?php

declare(strict_types=1);

namespace Framework\ORM;

use Framework\Database\Connection;

/**
 * Repository générique utilisé en interne pour charger les entités liées
 * qui n'ont pas de repository dédié.
 *
 * Aussi utile pour des tests rapides sans créer de classe de repository.
 */
class GenericRepository extends AbstractRepository
{
    public function __construct(
        Connection $db,
        private readonly string $entityClass,
    ) {
        parent::__construct($db);
    }

    protected function getEntityClass(): string
    {
        return $this->entityClass;
    }
}
