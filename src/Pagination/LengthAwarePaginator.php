<?php

declare(strict_types=1);

namespace Framework\Pagination;

/**
 * Résultat paginé renvoyé par AbstractRepository::paginate().
 *
 * Usage :
 *   $page = $repo->paginate(page: 1, perPage: 15);
 *
 *   $page->items()       → entités de la page courante
 *   $page->total()       → nombre total de résultats
 *   $page->currentPage() → numéro de page courante (1-based)
 *   $page->perPage()     → taille de page
 *   $page->lastPage()    → dernière page (= ceil(total / perPage))
 *   $page->hasMore()     → true si une page suivante existe
 *   $page->hasPrevious() → true si une page précédente existe
 *   $page->from()        → rang du premier élément (1-based, 0 si vide)
 *   $page->to()          → rang du dernier élément (0 si vide)
 */
class LengthAwarePaginator
{
    private readonly int $lastPage;

    /**
     * @param object[] $items   Entités de la page courante.
     * @param int      $total   Nombre total d'entités (toutes pages confondues).
     * @param int      $page    Numéro de page courant (1-based).
     * @param int      $perPage Nombre d'éléments par page.
     */
    public function __construct(
        private readonly array $items,
        private readonly int   $total,
        private readonly int   $page,
        private readonly int   $perPage,
    ) {
        $this->lastPage = $perPage > 0 && $total > 0
            ? (int) ceil($total / $perPage)
            : 1;
    }

    // ------------------------------------------------------------------
    // Accesseurs
    // ------------------------------------------------------------------

    /** @return object[] */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function currentPage(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /** Nombre d'éléments dans la page courante. */
    public function count(): int
    {
        return count($this->items);
    }

    // ------------------------------------------------------------------
    // Navigation
    // ------------------------------------------------------------------

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    /** Rang (1-based) du premier élément de la page. 0 si la page est vide. */
    public function from(): int
    {
        if ($this->count() === 0) {
            return 0;
        }

        return ($this->page - 1) * $this->perPage + 1;
    }

    /** Rang (1-based) du dernier élément de la page. 0 si la page est vide. */
    public function to(): int
    {
        if ($this->count() === 0) {
            return 0;
        }

        return $this->from() + $this->count() - 1;
    }

    // ------------------------------------------------------------------
    // Sérialisation (utile pour JSON APIs)
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        return [
            'current_page' => $this->page,
            'per_page'     => $this->perPage,
            'total'        => $this->total,
            'last_page'    => $this->lastPage,
            'from'         => $this->from(),
            'to'           => $this->to(),
            'has_more'     => $this->hasMore(),
            'data'         => $this->items,
        ];
    }
}
