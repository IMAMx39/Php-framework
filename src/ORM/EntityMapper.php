<?php

declare(strict_types=1);

namespace Framework\ORM;

use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;

/**
 * Convertit les lignes de la BDD en entités PHP et vice-versa.
 *
 * Hydratation  : row (array) → objet entité (avec types castés)
 * Extraction   : objet entité → array pour INSERT / UPDATE
 */
class EntityMapper
{
    // ------------------------------------------------------------------
    // Hydratation : row → entité
    // ------------------------------------------------------------------

    /**
     * Crée une instance de $class et la remplit avec les données de $row.
     *
     * Le constructeur n'est PAS appelé (newInstanceWithoutConstructor)
     * pour ne pas dépendre de sa signature.
     */
    public function hydrate(string $class, array $row): object
    {
        $reflector = new \ReflectionClass($class);
        $entity    = $reflector->newInstanceWithoutConstructor();

        foreach ($reflector->getProperties() as $property) {
            $attrs = $property->getAttributes(Column::class);

            if (empty($attrs)) {
                continue;
            }

            /** @var Column $column */
            $column     = $attrs[0]->newInstance();
            $columnName = $column->name ?? $this->toSnakeCase($property->getName());

            if (!array_key_exists($columnName, $row)) {
                continue;
            }

            $value = $this->cast($row[$columnName], $column->type, $column->nullable);

            $property->setAccessible(true);
            $property->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * Hydrate une liste de lignes en tableau d'entités.
     *
     * @return object[]
     */
    public function hydrateAll(string $class, array $rows): array
    {
        return array_map(fn (array $row) => $this->hydrate($class, $row), $rows);
    }

    // ------------------------------------------------------------------
    // Extraction : entité → array
    // ------------------------------------------------------------------

    /**
     * Retourne les colonnes de l'entité sous forme de tableau associatif.
     *
     * @param bool $includeId  false → exclut la colonne #[Id] + #[GeneratedValue] (pour INSERT)
     *                         true  → l'inclut (pour UPDATE)
     */
    public function extract(object $entity, bool $includeId = false): array
    {
        $reflector = new \ReflectionClass($entity);
        $data      = [];

        foreach ($reflector->getProperties() as $property) {
            $columnAttrs = $property->getAttributes(Column::class);

            if (empty($columnAttrs)) {
                continue;
            }

            // Exclure l'id auto-généré lors d'un INSERT
            $isId        = !empty($property->getAttributes(Id::class));
            $isGenerated = !empty($property->getAttributes(GeneratedValue::class));

            if (!$includeId && $isId && $isGenerated) {
                continue;
            }

            /** @var Column $column */
            $column     = $columnAttrs[0]->newInstance();
            $columnName = $column->name ?? $this->toSnakeCase($property->getName());

            $property->setAccessible(true);
            $value = $property->getValue($entity);

            // On n'inclut pas les valeurs null sauf si la colonne est nullable
            if ($value === null && !$column->nullable) {
                continue;
            }

            $data[$columnName] = $value;
        }

        return $data;
    }

    // ------------------------------------------------------------------
    // Accès à la clé primaire
    // ------------------------------------------------------------------

    /**
     * Retourne la valeur de la propriété marquée #[Id].
     */
    public function getId(object $entity): mixed
    {
        foreach ((new \ReflectionClass($entity))->getProperties() as $property) {
            if (!empty($property->getAttributes(Id::class))) {
                $property->setAccessible(true);

                return $property->getValue($entity);
            }
        }

        return null;
    }

    /**
     * Injecte la valeur $id dans la propriété marquée #[Id].
     */
    public function setId(object $entity, mixed $id): void
    {
        foreach ((new \ReflectionClass($entity))->getProperties() as $property) {
            if (!empty($property->getAttributes(Id::class))) {
                $property->setAccessible(true);
                $property->setValue($entity, $id);

                return;
            }
        }
    }

    /**
     * Retourne le nom de la colonne de la clé primaire.
     */
    public function getIdColumnName(string $class): string
    {
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            if (!empty($property->getAttributes(Id::class))) {
                $columnAttrs = $property->getAttributes(Column::class);

                if (!empty($columnAttrs)) {
                    /** @var Column $col */
                    $col = $columnAttrs[0]->newInstance();

                    return $col->name ?? $this->toSnakeCase($property->getName());
                }

                return $this->toSnakeCase($property->getName());
            }
        }

        return 'id';
    }

    // ------------------------------------------------------------------
    // Utilitaires
    // ------------------------------------------------------------------

    /**
     * Caste une valeur brute (string depuis PDO) vers le type PHP déclaré.
     */
    private function cast(mixed $value, string $type, bool $nullable): mixed
    {
        if ($value === null) {
            return $nullable ? null : throw new \RuntimeException("Valeur null sur une colonne non nullable.");
        }

        return match ($type) {
            'integer', 'int'    => (int) $value,
            'float', 'double'   => (float) $value,
            'boolean', 'bool'   => (bool) $value,
            'json'              => json_decode((string) $value, true),
            'datetime'          => new \DateTimeImmutable((string) $value),
            default             => (string) $value,
        };
    }

    /**
     * Convertit camelCase en snake_case.
     * Ex: createdAt → created_at, isActive → is_active
     */
    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}
