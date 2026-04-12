<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Relation 1→1 : cette entité possède une FK unique vers une autre.
 *
 * Fonctionne comme #[ManyToOne] mais garantit l'unicité côté applicatif.
 *
 * Exemple :
 *
 *   // FK stockée en base
 *   #[Column(name: 'profile_id', type: 'integer', nullable: true)]
 *   private ?int $profileId = null;
 *
 *   // Objet lié — chargé via find(..., relations: ['profile'])
 *   #[OneToOne(targetEntity: Profile::class, joinColumn: 'profile_id')]
 *   private ?Profile $profile = null;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OneToOne
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly string $joinColumn,   // Nom de la colonne FK dans CETTE table
        public readonly bool   $nullable = true,
    ) {}
}
