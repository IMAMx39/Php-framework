<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Marque la propriété comme clé primaire de la table.
 *
 * Exemple :
 *   #[Id]
 *   #[GeneratedValue]
 *   #[Column(type: 'integer')]
 *   private ?int $id = null;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Id {}
