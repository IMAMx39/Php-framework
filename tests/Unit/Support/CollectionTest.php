<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Framework\Support\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    // ------------------------------------------------------------------
    // Construction & basic access
    // ------------------------------------------------------------------

    public function testCountAndIterable(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertCount(3, $c);

        $result = [];
        foreach ($c as $v) {
            $result[] = $v;
        }
        $this->assertSame([1, 2, 3], $result);
    }

    public function testArrayAccess(): void
    {
        $c = new Collection(['a' => 1]);
        $this->assertTrue(isset($c['a']));
        $this->assertSame(1, $c['a']);

        $c['b'] = 2;
        $this->assertSame(2, $c['b']);

        unset($c['b']);
        $this->assertFalse(isset($c['b']));
    }

    public function testToArrayAndToJson(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertSame([1, 2, 3], $c->toArray());
        $this->assertSame('[1,2,3]', $c->toJson());
    }

    // ------------------------------------------------------------------
    // map / filter / flatMap
    // ------------------------------------------------------------------

    public function testMap(): void
    {
        $result = (new Collection([1, 2, 3]))->map(fn ($v) => $v * 2)->toArray();
        $this->assertSame([2, 4, 6], $result);
    }

    public function testFilter(): void
    {
        $result = (new Collection([1, 2, 3, 4]))->filter(fn ($v) => $v % 2 === 0)->values()->toArray();
        $this->assertSame([2, 4], $result);
    }

    public function testFlatMap(): void
    {
        $result = (new Collection([1, 2, 3]))->flatMap(fn ($v) => [$v, $v * 10])->toArray();
        $this->assertSame([1, 10, 2, 20, 3, 30], $result);
    }

    // ------------------------------------------------------------------
    // each / tap / pipe
    // ------------------------------------------------------------------

    public function testEach(): void
    {
        $sum = 0;
        (new Collection([1, 2, 3]))->each(function ($v) use (&$sum) { $sum += $v; });
        $this->assertSame(6, $sum);
    }

    public function testEachBreakOnFalse(): void
    {
        $seen = [];
        (new Collection([1, 2, 3, 4]))->each(function ($v) use (&$seen) {
            $seen[] = $v;
            if ($v === 2) {
                return false;
            }
        });
        $this->assertSame([1, 2], $seen);
    }

    public function testTap(): void
    {
        $captured = null;
        $c = (new Collection([1, 2]))->tap(function ($col) use (&$captured) {
            $captured = $col->toArray();
        });
        $this->assertSame([1, 2], $captured);
        $this->assertSame([1, 2], $c->toArray()); // original unchanged
    }

    public function testPipe(): void
    {
        $result = (new Collection([1, 2, 3]))->pipe(fn ($c) => $c->sum());
        $this->assertSame(6, $result);
    }

    // ------------------------------------------------------------------
    // Aggregates
    // ------------------------------------------------------------------

    public function testSumAvgMinMax(): void
    {
        $c = new Collection([2, 4, 6]);
        $this->assertSame(12, $c->sum());
        $this->assertEqualsWithDelta(4.0, $c->avg(), 0.001);
        $this->assertSame(2, $c->min());
        $this->assertSame(6, $c->max());
    }

    public function testSumWithCallback(): void
    {
        $c = new Collection([['price' => 10], ['price' => 20]]);
        $this->assertSame(30, $c->sum(fn ($i) => $i['price']));
    }

    public function testReduce(): void
    {
        $result = (new Collection([1, 2, 3, 4]))->reduce(fn ($carry, $v) => $carry + $v, 0);
        $this->assertSame(10, $result);
    }

    // ------------------------------------------------------------------
    // Sort
    // ------------------------------------------------------------------

    public function testSortBy(): void
    {
        $result = (new Collection([3, 1, 2]))->sortBy(fn ($v) => $v)->values()->toArray();
        $this->assertSame([1, 2, 3], $result);
    }

    public function testSortByDesc(): void
    {
        $result = (new Collection([3, 1, 2]))->sortByDesc(fn ($v) => $v)->values()->toArray();
        $this->assertSame([3, 2, 1], $result);
    }

    public function testReverse(): void
    {
        $result = (new Collection([1, 2, 3]))->reverse()->values()->toArray();
        $this->assertSame([3, 2, 1], $result);
    }

    // ------------------------------------------------------------------
    // first / last / contains / some / every
    // ------------------------------------------------------------------

    public function testFirst(): void
    {
        $this->assertSame(1, (new Collection([1, 2, 3]))->first());
        $this->assertNull((new Collection([]))->first());
        $this->assertSame(2, (new Collection([1, 2, 3]))->first(fn ($v) => $v > 1));
    }

    public function testLast(): void
    {
        $this->assertSame(3, (new Collection([1, 2, 3]))->last());
        $this->assertNull((new Collection([]))->last());
        $this->assertSame(2, (new Collection([1, 2, 3]))->last(fn ($v) => $v < 3));
    }

    public function testContains(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(99));
        $this->assertTrue($c->contains(fn ($v) => $v > 2));
    }

    public function testSome(): void
    {
        $this->assertTrue((new Collection([1, 2, 3]))->some(fn ($v) => $v > 2));
        $this->assertFalse((new Collection([1, 2, 3]))->some(fn ($v) => $v > 10));
    }

    public function testEvery(): void
    {
        $this->assertTrue((new Collection([2, 4, 6]))->every(fn ($v) => $v % 2 === 0));
        $this->assertFalse((new Collection([2, 3, 6]))->every(fn ($v) => $v % 2 === 0));
    }

    // ------------------------------------------------------------------
    // groupBy / chunk / take / skip
    // ------------------------------------------------------------------

    public function testGroupBy(): void
    {
        $c = new Collection([
            ['type' => 'a', 'v' => 1],
            ['type' => 'b', 'v' => 2],
            ['type' => 'a', 'v' => 3],
        ]);

        $grouped = $c->groupBy(fn ($i) => $i['type']);
        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['a']);
        $this->assertCount(1, $grouped['b']);
    }

    public function testChunk(): void
    {
        $chunks = (new Collection([1, 2, 3, 4, 5]))->chunk(2);
        $this->assertCount(3, $chunks);
        $this->assertSame([1, 2], $chunks[0]->toArray());
        $this->assertSame([3, 4], $chunks[1]->toArray());
        $this->assertSame([5],    $chunks[2]->toArray());
    }

    public function testTake(): void
    {
        $result = (new Collection([1, 2, 3, 4]))->take(2)->toArray();
        $this->assertSame([1, 2], $result);
    }

    public function testSkip(): void
    {
        $result = (new Collection([1, 2, 3, 4]))->skip(2)->values()->toArray();
        $this->assertSame([3, 4], $result);
    }

    // ------------------------------------------------------------------
    // flatten / pluck / unique / values / keys / merge
    // ------------------------------------------------------------------

    public function testFlatten(): void
    {
        $result = (new Collection([[1, 2], [3, [4, 5]]]))->flatten()->toArray();
        $this->assertSame([1, 2, 3, 4, 5], $result);
    }

    public function testPluck(): void
    {
        $c = new Collection([['name' => 'Alice'], ['name' => 'Bob']]);
        $this->assertSame(['Alice', 'Bob'], $c->pluck('name')->toArray());
    }

    public function testUnique(): void
    {
        $result = (new Collection([1, 2, 2, 3, 3]))->unique()->values()->toArray();
        $this->assertSame([1, 2, 3], $result);
    }

    public function testKeys(): void
    {
        $result = (new Collection(['a' => 1, 'b' => 2]))->keys()->toArray();
        $this->assertSame(['a', 'b'], $result);
    }

    public function testMerge(): void
    {
        $a = new Collection([1, 2]);
        $b = new Collection([3, 4]);
        $this->assertSame([1, 2, 3, 4], $a->merge($b)->values()->toArray());
    }
}
