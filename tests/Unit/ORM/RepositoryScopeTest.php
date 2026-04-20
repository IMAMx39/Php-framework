<?php

declare(strict_types=1);

namespace Tests\Unit\ORM;

use Framework\Database\Connection;
use Framework\Database\QueryBuilder;
use Framework\ORM\AbstractRepository;
use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;
use Framework\ORM\RepositoryScope;
use Framework\Support\Collection;
use PHPUnit\Framework\TestCase;

// ── Fixtures ──────────────────────────────────────────────────────────

#[Entity(table: 'scope_items')]
class ScopeItem
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $name;

    #[Column(type: 'integer')]
    private int $active;

    #[Column(type: 'integer')]
    private int $priority;

    public function __construct(string $name, int $active, int $priority = 0)
    {
        $this->name     = $name;
        $this->active   = $active;
        $this->priority = $priority;
    }

    public function getId(): ?int     { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getActive(): int  { return $this->active; }
    public function getPriority(): int { return $this->priority; }
}

class ScopeItemRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return ScopeItem::class;
    }

    public function scopeActive(QueryBuilder $qb): QueryBuilder
    {
        return $qb->where('active', 1);
    }

    public function scopeHighPriority(QueryBuilder $qb, int $min = 5): QueryBuilder
    {
        return $qb->where('priority', '>=', $min);
    }
}

// ── Tests ─────────────────────────────────────────────────────────────

class RepositoryScopeTest extends TestCase
{
    private Connection            $db;
    private ScopeItemRepository   $repo;

    protected function setUp(): void
    {
        $_ENV['DATABASE_URL'] = 'sqlite::memory:';
        $this->db   = new Connection();
        $this->repo = new ScopeItemRepository($this->db);

        $this->db->query(
            'CREATE TABLE scope_items (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                name     VARCHAR(100) NOT NULL,
                active   INTEGER NOT NULL DEFAULT 1,
                priority INTEGER NOT NULL DEFAULT 0
            )'
        );

        // Seed data
        $this->repo->save(new ScopeItem('Alpha',   active: 1, priority: 3));
        $this->repo->save(new ScopeItem('Beta',    active: 0, priority: 8));
        $this->repo->save(new ScopeItem('Gamma',   active: 1, priority: 7));
        $this->repo->save(new ScopeItem('Delta',   active: 1, priority: 2));
        $this->repo->save(new ScopeItem('Epsilon', active: 0, priority: 9));
    }

    // ------------------------------------------------------------------
    // __call() returns RepositoryScope
    // ------------------------------------------------------------------

    public function testCallReturnsRepositoryScope(): void
    {
        $scope = $this->repo->active();
        $this->assertInstanceOf(RepositoryScope::class, $scope);
    }

    public function testUndefinedScopeThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->repo->nonexistentScope();
    }

    // ------------------------------------------------------------------
    // get()
    // ------------------------------------------------------------------

    public function testScopeGetReturnsOnlyActiveItems(): void
    {
        $items = $this->repo->active()->get()->toArray();
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertSame(1, $item->getActive());
        }
    }

    public function testGetReturnsCollection(): void
    {
        $result = $this->repo->active()->get();
        $this->assertInstanceOf(Collection::class, $result);
    }

    // ------------------------------------------------------------------
    // Scope with argument
    // ------------------------------------------------------------------

    public function testScopeWithArgument(): void
    {
        // Only active=0 items have priority >= 8 (Beta=8, Epsilon=9)
        $items = $this->repo->highPriority(8)->get()->toArray();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(8, $item->getPriority());
        }
    }

    // ------------------------------------------------------------------
    // Chaining scopes
    // ------------------------------------------------------------------

    public function testChainedScopes(): void
    {
        // active + highPriority(5) → only Gamma (active=1, priority=7)
        $items = $this->repo->active()->highPriority(5)->get()->toArray();
        $this->assertCount(1, $items);
        $this->assertSame('Gamma', $items[0]->getName());
    }

    // ------------------------------------------------------------------
    // first()
    // ------------------------------------------------------------------

    public function testFirst(): void
    {
        $item = $this->repo->active()->first();
        $this->assertNotNull($item);
        $this->assertSame(1, $item->getActive());
    }

    public function testFirstReturnsNullWhenNoMatch(): void
    {
        // No active item with priority >= 100
        $item = $this->repo->active()->highPriority(100)->first();
        $this->assertNull($item);
    }

    // ------------------------------------------------------------------
    // count()
    // ------------------------------------------------------------------

    public function testCount(): void
    {
        $this->assertSame(3, $this->repo->active()->count());
        $this->assertSame(2, $this->repo->highPriority(8)->count());
    }

    // ------------------------------------------------------------------
    // paginate()
    // ------------------------------------------------------------------

    public function testPaginate(): void
    {
        $page = $this->repo->active()->paginate(page: 1, perPage: 2);

        $this->assertSame(3, $page->total());
        $this->assertCount(2, $page->items());
        $this->assertSame(1, $page->currentPage());
        $this->assertTrue($page->hasMore());
    }

    public function testPaginateSecondPage(): void
    {
        $page = $this->repo->active()->paginate(page: 2, perPage: 2);

        $this->assertCount(1, $page->items());
        $this->assertFalse($page->hasMore());
    }

    public function testPaginateDoesNotMutateScope(): void
    {
        $scope = $this->repo->active();
        $scope->paginate(page: 1, perPage: 2);

        // Calling count() after paginate() should still return all active items
        $this->assertSame(3, $scope->count());
    }
}
