<?php

declare(strict_types=1);

namespace Framework\ORM;

use Framework\Database\Connection;
use Framework\Database\QueryBuilder;
use Framework\ORM\Attribute\Entity;

/**
 * Repository de base à étendre pour chaque entité.
 *
 * Méthodes disponibles sans rien écrire :
 *   find(1)                              → ?Entity
 *   findAll()                            → Entity[]
 *   findBy(['active' => 1])              → Entity[]
 *   findBy(['active' => 1], ['name' => 'ASC'], limit: 10)
 *   findOneBy(['email' => 'a@b.com'])    → ?Entity
 *   save($entity)                        → void  (INSERT ou UPDATE)
 *   delete($entity)                      → void
 *
 * Méthodes personnalisées dans le repository enfant :
 *   protected function createQueryBuilder(): QueryBuilder
 *   → donne accès au QueryBuilder pré-configuré sur la table
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

    /**
     * Retourne le FQCN de l'entité gérée.
     *
     * Exemple : return User::class;
     */
    abstract protected function getEntityClass(): string;

    // ------------------------------------------------------------------
    // Méthodes de lecture
    // ------------------------------------------------------------------

    /**
     * Trouve une entité par sa clé primaire.
     */
    public function find(int|string $id): ?object
    {
        $idColumn = $this->mapper->getIdColumnName($this->getEntityClass());
        $row      = $this->createQueryBuilder()->where($idColumn, $id)->first();

        return $row ? $this->mapper->hydrate($this->getEntityClass(), $row) : null;
    }

    /**
     * Retourne toutes les entités de la table.
     *
     * @return object[]
     */
    public function findAll(): array
    {
        return $this->mapper->hydrateAll(
            $this->getEntityClass(),
            $this->createQueryBuilder()->get(),
        );
    }

    /**
     * Trouve toutes les entités correspondant aux critères.
     *
     * @param array<string, mixed>  $criteria  Ex: ['active' => 1, 'role' => 'admin']
     * @param array<string, string> $orderBy   Ex: ['name' => 'ASC', 'created_at' => 'DESC']
     * @return object[]
     */
    public function findBy(
        array  $criteria,
        array  $orderBy = [],
        ?int   $limit   = null,
        ?int   $offset  = null,
    ): array {
        $qb = $this->createQueryBuilder();

        foreach ($criteria as $column => $value) {
            $qb->where($column, $value);
        }

        foreach ($orderBy as $column => $direction) {
            $qb->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        if ($offset !== null) {
            $qb->offset($offset);
        }

        return $this->mapper->hydrateAll($this->getEntityClass(), $qb->get());
    }

    /**
     * Trouve la première entité correspondant aux critères.
     */
    public function findOneBy(array $criteria): ?object
    {
        $results = $this->findBy($criteria, [], 1);

        return $results[0] ?? null;
    }

    /**
     * Compte le nombre d'entités (avec critères optionnels).
     *
     * @param array<string, mixed> $criteria
     */
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

    /**
     * Persiste l'entité (INSERT si id est null, UPDATE sinon).
     */
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

    /**
     * Supprime l'entité de la base de données.
     */
    public function delete(object $entity): void
    {
        $id = $this->mapper->getId($entity);

        if ($id !== null) {
            $idColumn = $this->mapper->getIdColumnName($this->getEntityClass());
            $this->db->table($this->table)->where($idColumn, $id)->delete();
        }
    }

    // ------------------------------------------------------------------
    // Accès interne au QueryBuilder
    // ------------------------------------------------------------------

    /**
     * Retourne un QueryBuilder pré-configuré sur la table de l'entité.
     * Utilisable dans les méthodes personnalisées du repository enfant.
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return $this->db->table($this->table);
    }

    // ------------------------------------------------------------------
    // Résolution des métadonnées
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
