<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use Framework\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    // ------------------------------------------------------------------
    // get / put / has / forget
    // ------------------------------------------------------------------

    public function testPutAndGet(): void
    {
        $this->cache->put('foo', 'bar');
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertNull($this->cache->get('missing'));
        $this->assertSame('default', $this->cache->get('missing', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->put('x', 1);
        $this->assertTrue($this->cache->has('x'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('nope'));
    }

    public function testForgetRemovesKey(): void
    {
        $this->cache->put('del', 'value');
        $this->cache->forget('del');

        $this->assertFalse($this->cache->has('del'));
        $this->assertNull($this->cache->get('del'));
    }

    public function testForgetNonExistingKeyDoesNotThrow(): void
    {
        $this->cache->forget('ghost');
        $this->assertFalse($this->cache->has('ghost'));
    }

    // ------------------------------------------------------------------
    // TTL
    // ------------------------------------------------------------------

    public function testEntryWithoutTtlNeverExpires(): void
    {
        $this->cache->put('forever', 42, null);
        $this->assertTrue($this->cache->has('forever'));
        $this->assertSame(42, $this->cache->get('forever'));
    }

    public function testExpiredEntryIsNotReturned(): void
    {
        // TTL de 1 seconde dans le passé
        $this->cache->put('expired', 'value', -1);

        $this->assertFalse($this->cache->has('expired'));
        $this->assertNull($this->cache->get('expired'));
    }

    public function testExpiredEntryIsEvictedOnHasCheck(): void
    {
        $this->cache->put('evict', 'gone', -1);
        $this->cache->has('evict');  // déclenche l'éviction

        // La clé doit être supprimée après has()
        $this->assertSame('fallback', $this->cache->get('evict', 'fallback'));
    }

    // ------------------------------------------------------------------
    // remember
    // ------------------------------------------------------------------

    public function testRememberCallsCallbackWhenMissing(): void
    {
        $called = 0;

        $result = $this->cache->remember('key', null, function () use (&$called) {
            $called++;
            return 'computed';
        });

        $this->assertSame('computed', $result);
        $this->assertSame(1, $called);
    }

    public function testRememberDoesNotCallCallbackWhenHit(): void
    {
        $this->cache->put('cached', 'existing');
        $called = 0;

        $result = $this->cache->remember('cached', null, function () use (&$called) {
            $called++;
            return 'should-not-run';
        });

        $this->assertSame('existing', $result);
        $this->assertSame(0, $called);
    }

    public function testRememberStoresComputedValue(): void
    {
        $this->cache->remember('computed', null, fn() => 99);

        $this->assertTrue($this->cache->has('computed'));
        $this->assertSame(99, $this->cache->get('computed'));
    }

    public function testRememberWithTtl(): void
    {
        $this->cache->remember('ttl-key', 3600, fn() => 'value');

        $this->assertTrue($this->cache->has('ttl-key'));
    }

    // ------------------------------------------------------------------
    // flush
    // ------------------------------------------------------------------

    public function testFlushClearsAllEntries(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);
        $this->cache->put('c', 3);

        $this->cache->flush();

        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
        $this->assertFalse($this->cache->has('c'));
    }

    // ------------------------------------------------------------------
    // Types divers
    // ------------------------------------------------------------------

    public function testCanStoreArray(): void
    {
        $this->cache->put('arr', [1, 2, 3]);
        $this->assertSame([1, 2, 3], $this->cache->get('arr'));
    }

    public function testCanStoreObject(): void
    {
        $obj    = new \stdClass();
        $obj->x = 'test';

        $this->cache->put('obj', $obj);
        $this->assertSame($obj, $this->cache->get('obj'));
    }

    public function testCanStoreFalse(): void
    {
        $this->cache->put('bool', false);
        $this->assertTrue($this->cache->has('bool'));
        $this->assertFalse($this->cache->get('bool'));
    }

    public function testCanStoreNull(): void
    {
        $this->cache->put('null', null);
        $this->assertTrue($this->cache->has('null'));
        $this->assertNull($this->cache->get('null'));
    }
}
