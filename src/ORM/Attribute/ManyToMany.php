<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Relation N→N via une table de jointure (pivot).
 *
 * La table pivot n'est PAS une entité — elle est gérée automatiquement
 * par les méthodes attach() / detach() / sync() du repository.
 *
 * Exemple (Post ↔ Tag) :
 *
 *   // Dans Post.php
 *   #[ManyToMany(
 *       targetEntity:     Tag::class,
 *       joinTable:        'post_tags',
 *       joinColumn:       'post_id',   // FK de CETTE entité dans la table pivot
 *       inverseJoinColumn:'tag_id',    // FK de l'entité CIBLE dans la table pivot
 *   )]
 *   private array $tags = [];
 *
 *   // Dans Tag.php (côté inverse — optionnel)
 *   #[ManyToMany(
 *       targetEntity:     Post::class,
 *       joinTable:        'post_tags',
 *       joinColumn:       'tag_id',
 *       inverseJoinColumn:'post_id',
 *   )]
 *   private array $posts = [];
 *
 * SQL de la table pivot :
 *   CREATE TABLE post_tags (
 *       post_id INTEGER NOT NULL,
 *       tag_id  INTEGER NOT NULL,
 *       PRIMARY KEY (post_id, tag_id)
 *   );
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly string $joinTable,          // Nom de la table pivot
        public readonly string $joinColumn,         // FK de CETTE entité dans la pivot
        public readonly string $inverseJoinColumn,  // FK de l'entité CIBLE dans la pivot
        public readonly array  $orderBy = [],       // Ex: ['name' => 'ASC']
    ) {}
}
