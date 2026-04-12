<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Mappe une propriété à une colonne de la table.
 *
 * Exemple :
 *   #[Column(type: 'string', length: 180, unique: true)]
 *   private string $email;
 *
 * Si `name` est omis, le nom de colonne est déduit automatiquement
 * en snake_case depuis le nom de la propriété (ex: createdAt → created_at).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly ?string $name     = null,
        public readonly string  $type     = 'string',
        public readonly bool    $nullable = false,
        public readonly bool    $unique   = false,
        public readonly ?int    $length   = null,
    ) {}
}
