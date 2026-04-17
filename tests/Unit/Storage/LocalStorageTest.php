<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Framework\Storage\LocalStorage;
use PHPUnit\Framework\TestCase;

class LocalStorageTest extends TestCase
{
    private string       $dir;
    private LocalStorage $storage;

    protected function setUp(): void
    {
        $this->dir     = sys_get_temp_dir() . '/phpfw_storage_test_' . uniqid();
        $this->storage = new LocalStorage($this->dir, '/storage');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }

        rmdir($dir);
    }

    // ------------------------------------------------------------------

    public function testDirectoryCreatedOnConstruction(): void
    {
        $newDir = sys_get_temp_dir() . '/phpfw_new_' . uniqid();
        new LocalStorage($newDir);

        $this->assertDirectoryExists($newDir);
        rmdir($newDir);
    }

    public function testPutAndGet(): void
    {
        $this->storage->put('hello.txt', 'world');

        $this->assertSame('world', $this->storage->get('hello.txt'));
    }

    public function testGetReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->storage->get('ghost.txt'));
    }

    public function testExists(): void
    {
        $this->assertFalse($this->storage->exists('a.txt'));

        $this->storage->put('a.txt', 'content');

        $this->assertTrue($this->storage->exists('a.txt'));
    }

    public function testDelete(): void
    {
        $this->storage->put('del.txt', 'bye');
        $this->storage->delete('del.txt');

        $this->assertFalse($this->storage->exists('del.txt'));
    }

    public function testDeleteNonExistingDoesNotThrow(): void
    {
        $this->storage->delete('ghost.txt');
        $this->assertFalse($this->storage->exists('ghost.txt'));
    }

    public function testPutCreatesSubdirectories(): void
    {
        $this->storage->put('sub/dir/file.txt', 'deep');

        $this->assertTrue($this->storage->exists('sub/dir/file.txt'));
    }

    public function testPath(): void
    {
        $expected = $this->dir . '/foo/bar.txt';
        $this->assertSame($expected, $this->storage->path('foo/bar.txt'));
    }

    public function testUrl(): void
    {
        $this->assertSame('/storage/avatars/user.png', $this->storage->url('avatars/user.png'));
    }

    public function testFiles(): void
    {
        $this->storage->put('a.txt', '1');
        $this->storage->put('b.txt', '2');
        $this->storage->put('sub/c.txt', '3');

        $files = $this->storage->files();

        $this->assertContains('a.txt', $files);
        $this->assertContains('b.txt', $files);
        $this->assertNotContains('sub/c.txt', $files);  // non récursif
    }

    public function testFilesRecursive(): void
    {
        $this->storage->put('a.txt', '1');
        $this->storage->put('sub/c.txt', '3');
        $this->storage->put('sub/deep/d.txt', '4');

        $files = $this->storage->files('', recursive: true);

        $this->assertContains('a.txt', $files);
        $this->assertContains('sub/c.txt', $files);
        $this->assertContains('sub/deep/d.txt', $files);
    }

    public function testFilesSubDirectory(): void
    {
        $this->storage->put('images/a.png', 'x');
        $this->storage->put('images/b.png', 'y');
        $this->storage->put('docs/c.pdf', 'z');

        $files = $this->storage->files('images');

        $this->assertContains('images/a.png', $files);
        $this->assertContains('images/b.png', $files);
        $this->assertNotContains('docs/c.pdf', $files);
    }
}
