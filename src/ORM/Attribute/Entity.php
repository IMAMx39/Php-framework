<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Marque une classe comme entité mappée à une table.
 *
 * Exemple :
 *   #[Entity(table: 'users', repositoryClass: UserRepository::class)]
 *   class User { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public readonly string $table,
        public readonly ?string $repositoryClass = null,
    ) {}
}
