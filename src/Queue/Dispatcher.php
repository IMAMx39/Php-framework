<?php

declare(strict_types=1);

namespace Framework\Queue;

class Dispatcher
{
    public function __construct(private readonly QueueInterface $queue) {}

    public function dispatch(JobInterface $job, int $delay = 0): void
    {
        $this->queue->push($job, $delay);
    }
}
