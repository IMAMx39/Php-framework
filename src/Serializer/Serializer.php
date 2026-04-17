<?php

declare(strict_types=1);

namespace Framework\Serializer;

use Framework\ORM\Attribute\Column;

/**
 * Convertit des objets PHP en tableaux/JSON sérialisables.
 *
 * Stratégie (par ordre de priorité) :
 *   1. Méthode toArray() sur l'objet   → utilisée telle quelle
 *   2. Propriétés annotées #[Column]   → converties depuis leurs getters ou valeur directe
 *   3. Toutes les propriétés publiques → converties directement
 *
 * Nommage : les noms de propriété sont convertis en snake_case dans la sortie.
 *
 * Groupes (optionnels) :
 *   Annotez une propriété avec #[SerializeGroup('public', 'admin')] pour la filtrer.
 *   Si aucun groupe n'est précisé sur la propriété, elle est toujours incluse.
 *
 * Usage :
 *   $serializer = new Serializer();
 *   $array = $serializer->normalize($user);
 *   $json  = $serializer->toJson($user);
 *   $array = $serializer->normalizeCollection($users);
 */
class Serializer
{
    // ------------------------------------------------------------------
    // API publique
    // ------------------------------------------------------------------

    /**
     * Convertit un objet (ou scalaire / tableau) en tableau PHP.
     *
     * @param string[] $groups Groupes de sérialisation actifs ([] = tous).
     */
    public function normalize(mixed $value, array $groups = []): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->normalize($v, $groups), $value);
        }

        if (!is_object($value)) {
            return $value;
        }

        return $this->normalizeObject($value, $groups);
    }

    /**
     * Convertit une collection d'objets en tableau de tableaux.
     *
     * @param object[] $collection
     * @param string[] $groups
     * @return array[]
     */
    public function normalizeCollection(array $collection, array $groups = []): array
    {
        return array_values(array_map(
            fn ($item) => $this->normalize($item, $groups),
            $collection,
        ));
    }

    /**
     * Sérialise en JSON.
     *
     * @param string[] $groups
     */
    public function toJson(mixed $value, array $groups = [], int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        $normalized = $this->normalize($value, $groups);
        $json       = json_encode($normalized, $flags);

        if ($json === false) {
            throw new \RuntimeException('Échec de la sérialisation JSON : ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Désérialise un tableau associatif vers un objet (hydratation simple).
     *
     * @param array<string, mixed> $data
     */
    public function denormalize(array $data, string $class): object
    {
        $reflector = new \ReflectionClass($class);
        $object    = $reflector->newInstanceWithoutConstructor();

        foreach ($reflector->getProperties() as $property) {
            $name        = $property->getName();
            $snakeName   = $this->toSnakeCase($name);

            $value = $data[$name] ?? $data[$snakeName] ?? null;

            if ($value !== null) {
                $property->setAccessible(true);
                $property->setValue($object, $value);
            }
        }

        return $object;
    }

    // ------------------------------------------------------------------
    // Normalisation interne
    // ------------------------------------------------------------------

    private function normalizeObject(object $object, array $groups): array
    {
        // Priorité 1 : toArray()
        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        $reflector  = new \ReflectionClass($object);
        $properties = $reflector->getProperties();
        $result     = [];

        $hasColumnAttrs = $this->hasColumnAttributes($properties);

        foreach ($properties as $property) {
            // Filtrage par groupe
            if (!$this->isInGroups($property, $groups)) {
                continue;
            }

            $name = $property->getName();

            // Propriétés de relations ORM (non scalaires) → skip par défaut
            if ($hasColumnAttrs && empty($property->getAttributes(Column::class))) {
                continue;
            }

            $value = $this->readProperty($object, $property);
            $key   = $this->toSnakeCase($name);

            $result[$key] = $this->normalize($value, $groups);
        }

        return $result;
    }

    private function readProperty(object $object, \ReflectionProperty $property): mixed
    {
        // Essaie le getter d'abord
        $getter = 'get' . ucfirst($property->getName());

        if (method_exists($object, $getter)) {
            return $object->$getter();
        }

        // Accès direct
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Vérifie si au moins une propriété a l'attribut #[Column].
     *
     * @param \ReflectionProperty[] $properties
     */
    private function hasColumnAttributes(array $properties): bool
    {
        foreach ($properties as $property) {
            if (!empty($property->getAttributes(Column::class))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si la propriété appartient aux groupes demandés.
     * Si aucun groupe n'est requis ($groups vide), tout passe.
     */
    private function isInGroups(\ReflectionProperty $property, array $groups): bool
    {
        if (empty($groups)) {
            return true;
        }

        $attrs = $property->getAttributes(SerializeGroup::class);

        if (empty($attrs)) {
            return true;
        }

        /** @var SerializeGroup $group */
        $group = $attrs[0]->newInstance();

        foreach ($group->groups as $g) {
            if (in_array($g, $groups, true)) {
                return true;
            }
        }

        return false;
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}
