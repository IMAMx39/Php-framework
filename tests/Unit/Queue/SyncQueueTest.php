<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use Framework\Queue\Driver\SyncQueue;
use Framework\Queue\Envelope;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

class SyncQueueTest extends TestCase
{
    private SyncQueue $queue;

    protected function setUp(): void
    {
        $this->queue         = new SyncQueue();
        SimpleJob::$executed = 0;
    }

    public function testPushAndPopReturnsEnvelope(): void
    {
        $this->queue->push(new SimpleJob());
        $env = $this->queue->pop();

        $this->assertInstanceOf(Envelope::class, $env);
        $this->assertInstanceOf(SimpleJob::class, $env->job);
    }

    public function testPopReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->queue->pop());
    }

    public function testAttemptIsIncrementedOnPop(): void
    {
        $this->queue->push(new SimpleJob());
        $env = $this->queue->pop();

        $this->assertSame(1, $env->attempts);
    }

    public function testSizeReflectsQueueLength(): void
    {
        $this->assertSame(0, $this->queue->size());
        $this->queue->push(new SimpleJob());
        $this->assertSame(1, $this->queue->size());
    }

    public function testFlushEmptiesQueue(): void
    {
        $this->queue->push(new SimpleJob());
        $this->queue->push(new SimpleJob());
        $this->queue->flush();

        $this->assertSame(0, $this->queue->size());
    }

    public function testNackRequeuesJob(): void
    {
        $this->queue->push(new SimpleJob());
        $env = $this->queue->pop();
        $this->queue->nack($env, delay: 0);

        $this->assertSame(1, $this->queue->size());
    }

    public function testDelayedJobIsNotImmediatelyAvailable(): void
    {
        $this->queue->push(new SimpleJob(), delay: 3600);

        $this->assertNull($this->queue->pop());
    }
}
