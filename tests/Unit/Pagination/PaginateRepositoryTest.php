<?php

declare(strict_types=1);

namespace Tests\Unit\Pagination;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Framework\Database\Connection;
use Framework\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

/**
 * Teste AbstractRepository::paginate() via TagRepository + SQLite mémoire.
 */
class PaginateRepositoryTest extends TestCase
{
    private Connection    $db;
    private TagRepository $repo;

    protected function setUp(): void
    {
        $_ENV['DATABASE_URL'] = 'sqlite::memory:';
        $this->db   = new Connection();
        $this->repo = new TagRepository($this->db);

        $this->db->query('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL UNIQUE, color VARCHAR(7))');

        // Insère 25 tags
        for ($i = 1; $i <= 25; $i++) {
            $tag = new Tag("Tag{$i}", '#AAAAAA');
            $this->repo->save($tag);
        }
    }

    // ------------------------------------------------------------------

    public function testPaginateReturnsLengthAwarePaginator(): void
    {
        $page = $this->repo->paginate(page: 1, perPage: 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
    }

    public function testFirstPageHas10Items(): void
    {
        $page = $this->repo->paginate(page: 1, perPage: 10);

        $this->assertCount(10, $page->items());
        $this->assertSame(25, $page->total());
        $this->assertSame(1, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertTrue($page->hasMore());
        $this->assertFalse($page->hasPrevious());
    }

    public function testSecondPage(): void
    {
        $page = $this->repo->paginate(page: 2, perPage: 10);

        $this->assertCount(10, $page->items());
        $this->assertSame(11, $page->from());
        $this->assertSame(20, $page->to());
        $this->assertTrue($page->hasMore());
        $this->assertTrue($page->hasPrevious());
    }

    public function testLastPartialPage(): void
    {
        $page = $this->repo->paginate(page: 3, perPage: 10);

        $this->assertCount(5, $page->items());
        $this->assertSame(21, $page->from());
        $this->assertSame(25, $page->to());
        $this->assertFalse($page->hasMore());
        $this->assertTrue($page->hasPrevious());
    }

    public function testPageBeyondLastReturnsEmptyItems(): void
    {
        $page = $this->repo->paginate(page: 99, perPage: 10);

        $this->assertCount(0, $page->items());
        $this->assertSame(25, $page->total());
        $this->assertFalse($page->hasMore());
        $this->assertSame(0, $page->from());
        $this->assertSame(0, $page->to());
    }

    public function testPaginateWithCriteria(): void
    {
        // Aucun tag n'a la couleur #000000 → total = 0
        $page = $this->repo->paginate(page: 1, perPage: 10, criteria: ['color' => '#000000']);

        $this->assertCount(0, $page->items());
        $this->assertSame(0, $page->total());
        $this->assertSame(1, $page->lastPage());
    }

    public function testPageNormalisedToAtLeast1(): void
    {
        $page = $this->repo->paginate(page: 0, perPage: 10);
        $this->assertSame(1, $page->currentPage());
    }

    public function testPerPageNormalisedToAtLeast1(): void
    {
        $page = $this->repo->paginate(page: 1, perPage: 0);
        $this->assertSame(1, $page->perPage());
    }
}
