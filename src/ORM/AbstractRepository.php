<?php

declare(strict_types=1);

namespace Framework\ORM;

use Framework\Database\Connection;
use Framework\Database\QueryBuilder;
use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\ManyToOne;
use Framework\ORM\Attribute\OneToMany;
use Framework\ORM\Attribute\OneToOne;

/**
 * Repository de base à étendre pour chaque entité.
 *
 * ── Lecture simple ──────────────────────────────────────────────
 *   find(1)
 *   findAll()
 *   findBy(['active' => 1], ['name' => 'ASC'], limit: 10)
 *   findOneBy(['email' => 'a@b.com'])
 *   count(['active' => 1])
 *
 * ── Avec relations (chargement explicite) ───────────────────────
 *   find(1, relations: ['author', 'comments'])
 *   findBy(['active' => 1], relations: ['author'])
 *
 * ── Persistance ─────────────────────────────────────────────────
 *   save($entity)    → INSERT si id null, UPDATE sinon
 *   delete($entity)
 */
abstract class AbstractRepository
{
    private readonly string $table;
    private readonly EntityMapper $mapper;

    public function __construct(protected readonly Connection $db)
    {
        $this->mapper = new EntityMapper();
        $this->table  = $this->resolveTable();
    }

    // ------------------------------------------------------------------
    // À implémenter dans chaque repository
    // ------------------------------------------------------------------

    abstract protected function getEntityClass(): string;

    // ------------------------------------------------------------------
    // Lecture
    // ------------------------------------------------------------------

    public function find(int|string $id, array $relations = []): ?object
    {
        $idColumn = $this->mapper->getIdColumnName($this->getEntityClass());
        $row      = $this->createQueryBuilder()->where($idColumn, $id)->first();

        if ($row === null) {
            return null;
        }

        $entity = $this->mapper->hydrate($this->getEntityClass(), $row);

        if (!empty($relations)) {
            $this->loadRelations($entity, $relations);
        }

        return $entity;
    }

    /** @return object[] */
    public function findAll(array $relations = []): array
    {
        return $this->hydrateCollection(
            $this->createQueryBuilder()->get(),
            $relations,
        );
    }

    /**
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $orderBy
     * @return object[]
     */
    public function findBy(
        array  $criteria,
        array  $orderBy   = [],
        ?int   $limit     = null,
        ?int   $offset    = null,
        array  $relations = [],
    ): array {
        $qb = $this->createQueryBuilder();

        foreach ($criteria as $column => $value) {
            $qb->where($column, $value);
        }

        foreach ($orderBy as $column => $direction) {
            $qb->orderBy($column, $direction);
        }

        if ($limit !== null)  { $qb->limit($limit);   }
        if ($offset !== null) { $qb->offset($offset); }

        return $this->hydrateCollection($qb->get(), $relations);
    }

    public function findOneBy(array $criteria, array $relations = []): ?object
    {
        $results = $this->findBy($criteria, [], 1, null, $relations);

        return $results[0] ?? null;
    }

    public function count(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder();

        foreach ($criteria as $column => $value) {
            $qb->where($column, $value);
        }

        return $qb->count();
    }

    // ------------------------------------------------------------------
    // Persistance
    // ------------------------------------------------------------------

    public function save(object $entity): void
    {
        $id = $this->mapper->getId($entity);

        if ($id === null) {
            $data  = $this->mapper->extract($entity, includeId: false);
            $newId = $this->db->table($this->table)->insert($data);
            $this->mapper->setId($entity, (int) $newId);
        } else {
            $idColumn = $this->mapper->getIdColumnName($this->getEntityClass());
            $data     = $this->mapper->extract($entity, includeId: false);
            $this->db->table($this->table)->where($idColumn, $id)->update($data);
        }
    }

    public function delete(object $entity): void
    {
        $id = $this->mapper->getId($entity);

        if ($id !== null) {
            $idColumn = $this->mapper->getIdColumnName($this->getEntityClass());
            $this->db->table($this->table)->where($idColumn, $id)->delete();
        }
    }

    // ------------------------------------------------------------------
    // QueryBuilder
    // ------------------------------------------------------------------

    protected function createQueryBuilder(): QueryBuilder
    {
        return $this->db->table($this->table);
    }

    // ------------------------------------------------------------------
    // Chargement des relations
    // ------------------------------------------------------------------

    /**
     * Charge les relations demandées sur une entité.
     *
     * @param string[] $relations Noms des propriétés de relation à charger.
     */
    protected function loadRelations(object $entity, array $relations): void
    {
        $reflector = new \ReflectionClass($entity);

        foreach ($relations as $propertyName) {
            if (!$reflector->hasProperty($propertyName)) {
                continue;
            }

            $property = $reflector->getProperty($propertyName);

            if (!empty($property->getAttributes(ManyToOne::class))) {
                /** @var ManyToOne $attr */
                $attr = $property->getAttributes(ManyToOne::class)[0]->newInstance();
                $this->loadManyToOne($entity, $property, $attr);
                continue;
            }

            if (!empty($property->getAttributes(OneToOne::class))) {
                /** @var OneToOne $attr */
                $attr = $property->getAttributes(OneToOne::class)[0]->newInstance();
                $this->loadOneToOne($entity, $property, $attr);
                continue;
            }

            if (!empty($property->getAttributes(OneToMany::class))) {
                /** @var OneToMany $attr */
                $attr = $property->getAttributes(OneToMany::class)[0]->newInstance();
                $this->loadOneToMany($entity, $property, $attr);
                continue;
            }
        }
    }

    // ── ManyToOne ──────────────────────────────────────────────────────

    private function loadManyToOne(object $entity, \ReflectionProperty $property, ManyToOne $attr): void
    {
        $fkValue = $this->getFkValue($entity, $attr->joinColumn);

        if ($fkValue === null) {
            return;
        }

        $related = $this->repositoryFor($attr->targetEntity)->find((int) $fkValue);

        $property->setAccessible(true);
        $property->setValue($entity, $related);
    }

    // ── OneToOne ───────────────────────────────────────────────────────

    private function loadOneToOne(object $entity, \ReflectionProperty $property, OneToOne $attr): void
    {
        $fkValue = $this->getFkValue($entity, $attr->joinColumn);

        if ($fkValue === null) {
            return;
        }

        $related = $this->repositoryFor($attr->targetEntity)->find((int) $fkValue);

        $property->setAccessible(true);
        $property->setValue($entity, $related);
    }

    // ── OneToMany ──────────────────────────────────────────────────────

    private function loadOneToMany(object $entity, \ReflectionProperty $property, OneToMany $attr): void
    {
        $entityId = $this->mapper->getId($entity);

        if ($entityId === null) {
            return;
        }

        $repo    = $this->repositoryFor($attr->targetEntity);
        $related = $repo->findBy([$attr->mappedBy => $entityId], $attr->orderBy);

        $property->setAccessible(true);
        $property->setValue($entity, $related);
    }

    // ------------------------------------------------------------------
    // Utilitaires internes
    // ------------------------------------------------------------------

    /**
     * Hydrate une collection de rows et charge les relations demandées.
     *
     * @return object[]
     */
    private function hydrateCollection(array $rows, array $relations): array
    {
        return array_map(function (array $row) use ($relations): object {
            $entity = $this->mapper->hydrate($this->getEntityClass(), $row);

            if (!empty($relations)) {
                $this->loadRelations($entity, $relations);
            }

            return $entity;
        }, $rows);
    }

    /**
     * Lit la valeur d'une colonne FK depuis les propriétés #[Column] de l'entité.
     */
    private function getFkValue(object $entity, string $columnName): mixed
    {
        foreach ((new \ReflectionClass($entity))->getProperties() as $prop) {
            $colAttrs = $prop->getAttributes(Column::class);

            if (empty($colAttrs)) {
                continue;
            }

            /** @var Column $col */
            $col     = $colAttrs[0]->newInstance();
            $colName = $col->name ?? $this->toSnakeCase($prop->getName());

            if ($colName === $columnName) {
                $prop->setAccessible(true);

                return $prop->getValue($entity);
            }
        }

        return null;
    }

    /**
     * Retourne un repository pour une entité cible.
     * Utilise le repositoryClass défini dans #[Entity], sinon un GenericRepository.
     */
    private function repositoryFor(string $entityClass): self
    {
        $reflector  = new \ReflectionClass($entityClass);
        $entityAttr = $reflector->getAttributes(Entity::class);

        if (!empty($entityAttr)) {
            /** @var Entity $meta */
            $meta = $entityAttr[0]->newInstance();

            if ($meta->repositoryClass !== null) {
                return new ($meta->repositoryClass)($this->db);
            }
        }

        return new GenericRepository($this->db, $entityClass);
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }

    // ------------------------------------------------------------------
    // Métadonnées
    // ------------------------------------------------------------------

    private function resolveTable(): string
    {
        $reflector = new \ReflectionClass($this->getEntityClass());
        $attrs     = $reflector->getAttributes(Entity::class);

        if (empty($attrs)) {
            throw new \RuntimeException(
                "La classe {$this->getEntityClass()} doit avoir l'attribut #[Entity(table: '...')]."
            );
        }

        /** @var Entity $entityAttr */
        $entityAttr = $attrs[0]->newInstance();

        return $entityAttr->table;
    }
}
