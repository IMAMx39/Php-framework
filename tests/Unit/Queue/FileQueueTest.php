<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use Framework\Queue\Driver\FileQueue;
use Framework\Queue\Envelope;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

class FileQueueTest extends TestCase
{
    private string $tmpDir;
    private FileQueue $queue;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpfw_queue_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->queue = new FileQueue($this->tmpDir, maxAttempts: 2);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPushCreatesFile(): void
    {
        $this->queue->push(new SimpleJob());
        $this->assertSame(1, $this->queue->size());
    }

    public function testPopReturnsJobAndDeletesFile(): void
    {
        $this->queue->push(new SimpleJob());
        $env = $this->queue->pop();

        $this->assertInstanceOf(Envelope::class, $env);
        $this->assertSame(0, $this->queue->size());
    }

    public function testPopReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->queue->pop());
    }

    public function testNackRequeuesJobFile(): void
    {
        $this->queue->push(new SimpleJob());
        $env = $this->queue->pop();
        $this->queue->nack($env, delay: 0);

        $this->assertSame(1, $this->queue->size());
    }

    public function testNackMovesToFailedWhenMaxAttemptsExceeded(): void
    {
        $this->queue->push(new SimpleJob());

        $env = $this->queue->pop();         // attempts = 1
        $this->queue->nack($env, delay: 0);
        $env = $this->queue->pop();         // attempts = 2 = max
        $this->queue->nack($env, delay: 0); // → failed/

        $this->assertSame(0, $this->queue->size());
        $failed = glob($this->tmpDir . '/queue/failed/*.json');
        $this->assertCount(1, $failed);
    }

    public function testFlushRemovesAllFiles(): void
    {
        $this->queue->push(new SimpleJob());
        $this->queue->push(new SimpleJob());
        $this->queue->flush();

        $this->assertSame(0, $this->queue->size());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
