<?php

declare(strict_types=1);

namespace Framework\ORM;

use Framework\Database\Connection;
use Framework\ORM\Attribute\Entity;

/**
 * Point d'entrée unique pour toutes les opérations ORM.
 *
 * Usage dans un contrôleur (via $this->em) :
 *
 *   // Lire
 *   $product  = $this->em->find(Product::class, 1);
 *   $products = $this->em->getRepository(Product::class)->findAll();
 *   $page     = $this->em->getRepository(Product::class)->paginate(page: 1);
 *
 *   // Persister (Unit of Work léger)
 *   $this->em->persist($product);
 *   $this->em->persist($other);
 *   $this->em->flush();           // exécute tous les save() en attente
 *
 *   // Supprimer
 *   $this->em->remove($product);
 *   $this->em->flush();
 *
 *   // One-liner (persist + flush immédiat)
 *   $this->em->save($product);
 *   $this->em->delete($product);
 */
class EntityManager
{
    /** @var object[] Entités en attente d'INSERT / UPDATE */
    private array $toPersist = [];

    /** @var object[] Entités en attente de DELETE */
    private array $toRemove = [];

    /** @var array<string, AbstractRepository> Cache des repositories instanciés */
    private array $repositories = [];

    private readonly EntityMapper $mapper;

    public function __construct(private readonly Connection $db)
    {
        $this->mapper = new EntityMapper();
    }

    // ------------------------------------------------------------------
    // Accès aux repositories
    // ------------------------------------------------------------------

    /**
     * Retourne le repository associé à la classe d'entité.
     *
     * Utilise le repositoryClass défini dans #[Entity], sinon un GenericRepository.
     */
    public function getRepository(string $entityClass): AbstractRepository
    {
        if (!isset($this->repositories[$entityClass])) {
            $this->repositories[$entityClass] = $this->resolveRepository($entityClass);
        }

        return $this->repositories[$entityClass];
    }

    // ------------------------------------------------------------------
    // Raccourcis de lecture
    // ------------------------------------------------------------------

    /**
     * Trouve une entité par son ID.
     *
     *   $product = $this->em->find(Product::class, 1);
     */
    public function find(string $entityClass, int|string $id, array $relations = []): ?object
    {
        return $this->getRepository($entityClass)->find($id, $relations);
    }

    // ------------------------------------------------------------------
    // Unit of Work — persist / remove / flush
    // ------------------------------------------------------------------

    /**
     * Marque une entité pour sauvegarde (INSERT ou UPDATE selon l'ID).
     * Rien n'est écrit en base tant que flush() n'est pas appelé.
     */
    public function persist(object $entity): void
    {
        $oid = spl_object_id($entity);

        $this->toPersist[$oid] = $entity;

        // Retire du toRemove si elle était marquée pour suppression
        unset($this->toRemove[$oid]);
    }

    /**
     * Marque une entité pour suppression.
     * Rien n'est écrit en base tant que flush() n'est pas appelé.
     */
    public function remove(object $entity): void
    {
        $oid = spl_object_id($entity);

        $this->toRemove[$oid] = $entity;

        // Retire du toPersist si elle était marquée pour sauvegarde
        unset($this->toPersist[$oid]);
    }

    /**
     * Exécute toutes les opérations en attente (persist + remove).
     */
    public function flush(): void
    {
        foreach ($this->toPersist as $entity) {
            $this->getRepository($entity::class)->save($entity);
        }

        foreach ($this->toRemove as $entity) {
            $this->getRepository($entity::class)->delete($entity);
        }

        $this->toPersist = [];
        $this->toRemove  = [];
    }

    // ------------------------------------------------------------------
    // One-liners (persist + flush immédiat)
    // ------------------------------------------------------------------

    /**
     * Sauvegarde immédiatement (sans passer par persist/flush).
     *
     *   $this->em->save($product);
     */
    public function save(object $entity): void
    {
        $this->getRepository($entity::class)->save($entity);
    }

    /**
     * Supprime immédiatement (sans passer par remove/flush).
     *
     *   $this->em->delete($product);
     */
    public function delete(object $entity): void
    {
        $this->getRepository($entity::class)->delete($entity);
    }

    // ------------------------------------------------------------------
    // Nettoyage
    // ------------------------------------------------------------------

    /**
     * Vide les files d'attente sans exécuter les opérations.
     */
    public function clear(): void
    {
        $this->toPersist    = [];
        $this->toRemove     = [];
        $this->repositories = [];
    }

    // ------------------------------------------------------------------
    // Résolution interne du repository
    // ------------------------------------------------------------------

    private function resolveRepository(string $entityClass): AbstractRepository
    {
        $reflector = new \ReflectionClass($entityClass);
        $attrs     = $reflector->getAttributes(Entity::class);

        if (!empty($attrs)) {
            /** @var Entity $meta */
            $meta = $attrs[0]->newInstance();

            if ($meta->repositoryClass !== null) {
                return new ($meta->repositoryClass)($this->db);
            }
        }

        return new GenericRepository($this->db, $entityClass);
    }
}
