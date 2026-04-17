<?php

declare(strict_types=1);

namespace Framework\Serializer;

/**
 * Marque une propriété comme appartenant à un ou plusieurs groupes de sérialisation.
 *
 * Usage :
 *   #[SerializeGroup('public')]
 *   private string $name;
 *
 *   #[SerializeGroup('admin', 'internal')]
 *   private string $passwordHash;
 *
 * Dans le serializer :
 *   $serializer->normalize($user, groups: ['public']);
 *   // → seules les propriétés sans groupe ou avec 'public' sont incluses
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SerializeGroup
{
    /** @var string[] */
    public readonly array $groups;

    public function __construct(string ...$groups)
    {
        $this->groups = $groups;
    }
}
