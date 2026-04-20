<?php

declare(strict_types=1);

namespace Framework\ORM;

use Framework\Database\QueryBuilder;
use Framework\Pagination\LengthAwarePaginator;
use Framework\Support\Collection;

/**
 * Proxy retourné lors de l'appel à un scope.
 * Permet le chaînage : $repo->active()->recent()->paginate()
 */
class RepositoryScope
{
    public function __construct(
        private readonly AbstractRepository $repository,
        private QueryBuilder               $qb,
    ) {}

    /**
     * Applique un scope supplémentaire.
     * $repo->active()->role('admin')->get()
     */
    public function __call(string $name, array $args): static
    {
        $method = 'scope' . ucfirst($name);

        if (!method_exists($this->repository, $method)) {
            throw new \BadMethodCallException(
                "Scope « {$name} » introuvable sur " . get_class($this->repository) . '.'
            );
        }

        $clone     = clone $this;
        $clone->qb = $this->repository->$method($this->qb, ...$args);

        return $clone;
    }

    // ------------------------------------------------------------------
    // Terminaisons
    // ------------------------------------------------------------------

    public function get(array $relations = []): Collection
    {
        return collect($this->repository->hydrateRows($this->qb->get(), $relations));
    }

    public function first(array $relations = []): ?object
    {
        $row = $this->qb->first();
        return $row !== null ? $this->repository->hydrateRow($row, $relations) : null;
    }

    public function count(): int
    {
        return $this->qb->count();
    }

    public function paginate(int $page = 1, int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $total  = $this->qb->count();
        $offset = ($page - 1) * $perPage;

        // Clone pour ne pas modifier l'état du QB original
        $paginatedQb = clone $this->qb;
        $rows        = $paginatedQb->limit($perPage)->offset($offset)->get();
        $items       = $this->repository->hydrateRows($rows, $relations);

        return new LengthAwarePaginator($items, $total, $page, $perPage);
    }
}
