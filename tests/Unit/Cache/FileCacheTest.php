<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use Framework\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    private string    $dir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->dir   = sys_get_temp_dir() . '/phpfw_cache_test_' . uniqid();
        $this->cache = new FileCache($this->dir);
    }

    protected function tearDown(): void
    {
        // Nettoie le répertoire temporaire
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    // ------------------------------------------------------------------
    // Construction
    // ------------------------------------------------------------------

    public function testDirectoryIsCreatedIfMissing(): void
    {
        $dir = sys_get_temp_dir() . '/phpfw_new_dir_' . uniqid();
        new FileCache($dir);

        $this->assertDirectoryExists($dir);

        rmdir($dir);
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
        $this->assertNull($this->cache->get('ghost'));
        $this->assertSame('x', $this->cache->get('ghost', 'x'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('nope'));
        $this->cache->put('nope', 1);
        $this->assertTrue($this->cache->has('nope'));
    }

    public function testForget(): void
    {
        $this->cache->put('del', 'v');
        $this->cache->forget('del');

        $this->assertFalse($this->cache->has('del'));
    }

    public function testForgetNonExistingDoesNotThrow(): void
    {
        $this->cache->forget('ghost');
        $this->assertFalse($this->cache->has('ghost'));
    }

    // ------------------------------------------------------------------
    // TTL
    // ------------------------------------------------------------------

    public function testEntryWithoutTtlPersists(): void
    {
        $this->cache->put('forever', 99, null);
        $this->assertTrue($this->cache->has('forever'));
    }

    public function testExpiredEntryIsNotReturned(): void
    {
        $this->cache->put('old', 'value', -1);

        $this->assertFalse($this->cache->has('old'));
        $this->assertNull($this->cache->get('old'));
    }

    public function testExpiredEntryFileIsDeleted(): void
    {
        $this->cache->put('evict', 'gone', -1);
        $this->cache->has('evict');

        // Plus aucun fichier .cache pour cette clé après l'éviction
        $path = $this->dir . '/' . hash('sha256', 'evict') . '.cache';
        $this->assertFileDoesNotExist($path);
    }

    // ------------------------------------------------------------------
    // remember
    // ------------------------------------------------------------------

    public function testRememberCallsCallbackWhenMissing(): void
    {
        $called = 0;
        $result = $this->cache->remember('r', null, function () use (&$called) {
            $called++;
            return 'computed';
        });

        $this->assertSame('computed', $result);
        $this->assertSame(1, $called);
    }

    public function testRememberDoesNotCallCallbackOnHit(): void
    {
        $this->cache->put('r2', 'cached');
        $called = 0;

        $result = $this->cache->remember('r2', null, function () use (&$called) {
            $called++;
            return 'new';
        });

        $this->assertSame('cached', $result);
        $this->assertSame(0, $called);
    }

    public function testRememberPersistsValue(): void
    {
        $this->cache->remember('persist', null, fn() => [1, 2, 3]);
        $this->assertSame([1, 2, 3], $this->cache->get('persist'));
    }

    // ------------------------------------------------------------------
    // flush
    // ------------------------------------------------------------------

    public function testFlushDeletesAllCacheFiles(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);

        $this->cache->flush();

        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
        $this->assertEmpty(glob($this->dir . '/*.cache'));
    }

    // ------------------------------------------------------------------
    // Sérialisation de types complexes
    // ------------------------------------------------------------------

    public function testCanStoreAndRetrieveArray(): void
    {
        $this->cache->put('arr', ['x' => 1, 'y' => [2, 3]]);
        $this->assertSame(['x' => 1, 'y' => [2, 3]], $this->cache->get('arr'));
    }

    public function testCanStoreObject(): void
    {
        $obj    = new \stdClass();
        $obj->n = 42;

        $this->cache->put('obj', $obj);
        $retrieved = $this->cache->get('obj');

        $this->assertInstanceOf(\stdClass::class, $retrieved);
        $this->assertSame(42, $retrieved->n);
    }

    public function testCanStoreFalseWithoutConfusingWithMiss(): void
    {
        $this->cache->put('bool', false);
        $this->assertTrue($this->cache->has('bool'));
        $this->assertFalse($this->cache->get('bool'));
    }
}
