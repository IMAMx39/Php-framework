<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use Framework\Container\Container;
use Framework\Queue\Driver\SyncQueue;
use Framework\Queue\Envelope;
use Framework\Queue\Worker;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

class WorkerTest extends TestCase
{
    private SyncQueue   $queue;
    private Container   $container;
    private FakeService $svc;

    protected function setUp(): void
    {
        SimpleJob::$executed = 0;
        $this->queue         = new SyncQueue();
        $this->svc           = new FakeService();
        $this->container     = new Container();
        $this->container->instance(FakeService::class, $this->svc);
    }

    private function worker(): Worker
    {
        return new Worker($this->queue, $this->container);
    }

    public function testProcessOneExecutesJob(): void
    {
        $this->queue->push(new SimpleJob());
        $this->worker()->processOne();

        $this->assertSame(1, SimpleJob::$executed);
    }

    public function testProcessOneReturnsTrueWhenJobProcessed(): void
    {
        $this->queue->push(new SimpleJob());
        $this->assertTrue($this->worker()->processOne());
    }

    public function testProcessOneReturnsFalseWhenQueueEmpty(): void
    {
        $this->assertFalse($this->worker()->processOne());
    }

    public function testWorkerResolvesHandleDependencies(): void
    {
        $this->queue->push(new JobWithDep('hello'));
        $this->worker()->processOne();

        $this->assertSame(['hello'], $this->svc->called);
    }

    public function testWorkerNacksOnJobFailure(): void
    {
        $this->queue->push(new FailingJob());

        try {
            $this->worker()->processOne();
        } catch (\RuntimeException) {}

        $this->assertSame(1, $this->queue->size());
    }

    public function testEnvelopeHasExceededMaxAttempts(): void
    {
        $env = new Envelope('id', new SimpleJob(), attempts: 3, maxAttempts: 3, availableAt: time());
        $this->assertTrue($env->hasExceededMaxAttempts());
    }

    public function testEnvelopeWithAttemptIncrementsCount(): void
    {
        $env  = new Envelope('id', new SimpleJob(), attempts: 1, maxAttempts: 3, availableAt: time());
        $next = $env->withAttempt();

        $this->assertSame(2, $next->attempts);
        $this->assertNotSame($env, $next);
    }
}
