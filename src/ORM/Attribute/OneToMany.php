<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Relation 1→N : l'entité liée possède une FK vers cette entité.
 *
 * La propriété PHP est un tableau d'objets liés.
 * Démarre toujours à [] — chargé via find(..., relations: ['comments']).
 *
 * Exemple :
 *
 *   #[OneToMany(targetEntity: Comment::class, mappedBy: 'post_id')]
 *   private array $comments = [];
 *
 * `mappedBy` est le nom de la colonne FK dans la table cible (snake_case).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OneToMany
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly string $mappedBy,    // Nom de la colonne FK dans la table CIBLE
        public readonly array  $orderBy = [], // Ex: ['created_at' => 'DESC']
    ) {}
}
