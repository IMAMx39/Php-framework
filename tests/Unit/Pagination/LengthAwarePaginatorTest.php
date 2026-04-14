<?php

declare(strict_types=1);

namespace Tests\Unit\Pagination;

use Framework\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

class LengthAwarePaginatorTest extends TestCase
{
    // ------------------------------------------------------------------
    // Construction et accesseurs de base
    // ------------------------------------------------------------------

    public function testBasicAccessors(): void
    {
        $paginator = new LengthAwarePaginator(['a', 'b', 'c'], 30, 1, 10);

        $this->assertSame(['a', 'b', 'c'], $paginator->items());
        $this->assertSame(30, $paginator->total());
        $this->assertSame(1, $paginator->currentPage());
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(3, $paginator->lastPage());
        $this->assertSame(3, $paginator->count());
    }

    // ------------------------------------------------------------------
    // lastPage
    // ------------------------------------------------------------------

    public function testLastPageIsComputedCorrectly(): void
    {
        // 30 items / 10 per page = 3 pages
        $this->assertSame(3, (new LengthAwarePaginator([], 30, 1, 10))->lastPage());

        // 31 items / 10 per page = 4 pages
        $this->assertSame(4, (new LengthAwarePaginator([], 31, 1, 10))->lastPage());

        // 10 items / 10 per page = 1 page
        $this->assertSame(1, (new LengthAwarePaginator([], 10, 1, 10))->lastPage());

        // 1 item / 10 per page = 1 page
        $this->assertSame(1, (new LengthAwarePaginator([], 1, 1, 10))->lastPage());
    }

    public function testLastPageIsOneWhenTotalIsZero(): void
    {
        $this->assertSame(1, (new LengthAwarePaginator([], 0, 1, 15))->lastPage());
    }

    // ------------------------------------------------------------------
    // Navigation
    // ------------------------------------------------------------------

    public function testHasMoreOnFirstPageOf3(): void
    {
        $p = new LengthAwarePaginator([], 30, 1, 10);
        $this->assertTrue($p->hasMore());
        $this->assertFalse($p->hasPrevious());
    }

    public function testMiddlePage(): void
    {
        $p = new LengthAwarePaginator([], 30, 2, 10);
        $this->assertTrue($p->hasMore());
        $this->assertTrue($p->hasPrevious());
    }

    public function testLastPage(): void
    {
        $p = new LengthAwarePaginator([], 30, 3, 10);
        $this->assertFalse($p->hasMore());
        $this->assertTrue($p->hasPrevious());
    }

    public function testSinglePage(): void
    {
        $p = new LengthAwarePaginator(['x'], 1, 1, 15);
        $this->assertFalse($p->hasMore());
        $this->assertFalse($p->hasPrevious());
    }

    // ------------------------------------------------------------------
    // From / to
    // ------------------------------------------------------------------

    public function testFromToFirstPage(): void
    {
        $items = array_fill(0, 10, 'x');
        $p     = new LengthAwarePaginator($items, 25, 1, 10);

        $this->assertSame(1, $p->from());
        $this->assertSame(10, $p->to());
    }

    public function testFromToSecondPage(): void
    {
        $items = array_fill(0, 10, 'x');
        $p     = new LengthAwarePaginator($items, 25, 2, 10);

        $this->assertSame(11, $p->from());
        $this->assertSame(20, $p->to());
    }

    public function testFromToLastPartialPage(): void
    {
        $items = array_fill(0, 5, 'x');   // 5 items on last page
        $p     = new LengthAwarePaginator($items, 25, 3, 10);

        $this->assertSame(21, $p->from());
        $this->assertSame(25, $p->to());
    }

    public function testFromToWhenEmpty(): void
    {
        $p = new LengthAwarePaginator([], 0, 1, 15);
        $this->assertSame(0, $p->from());
        $this->assertSame(0, $p->to());
    }

    // ------------------------------------------------------------------
    // toArray
    // ------------------------------------------------------------------

    public function testToArray(): void
    {
        $items = ['a', 'b'];
        $p     = new LengthAwarePaginator($items, 20, 1, 10);

        $arr = $p->toArray();

        $this->assertSame(1, $arr['current_page']);
        $this->assertSame(10, $arr['per_page']);
        $this->assertSame(20, $arr['total']);
        $this->assertSame(2, $arr['last_page']);
        $this->assertSame(1, $arr['from']);
        $this->assertSame(2, $arr['to']);
        $this->assertTrue($arr['has_more']);
        $this->assertSame($items, $arr['data']);
    }
}
