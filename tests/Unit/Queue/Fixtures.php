<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use Framework\Queue\JobInterface;

class FakeService
{
    public array $called = [];

    public function process(string $value): void
    {
        $this->called[] = $value;
    }
}

class SimpleJob implements JobInterface
{
    public static int $executed = 0;

    public function handle(): void
    {
        self::$executed++;
    }
}

class JobWithDep implements JobInterface
{
    public function __construct(public readonly string $value) {}

    public function handle(FakeService $svc): void
    {
        $svc->process($this->value);
    }
}

class FailingJob implements JobInterface
{
    public function handle(): void
    {
        throw new \RuntimeException('Job failure');
    }
}
