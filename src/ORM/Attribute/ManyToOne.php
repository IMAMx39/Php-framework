<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Relation N→1 : cette entité possède une clé étrangère vers une autre.
 *
 * La propriété PHP est l'objet lié, la FK doit être stockée dans une
 * propriété séparée avec #[Column].
 *
 * Exemple :
 *
 *   // FK stockée en base
 *   #[Column(name: 'user_id', type: 'integer', nullable: true)]
 *   private ?int $userId = null;
 *
 *   // Objet lié — chargé via find(..., relations: ['author'])
 *   #[ManyToOne(targetEntity: User::class, joinColumn: 'user_id')]
 *   private ?User $author = null;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly string $joinColumn,   // Nom de la colonne FK dans CETTE table
        public readonly bool   $nullable = true,
    ) {}
}
