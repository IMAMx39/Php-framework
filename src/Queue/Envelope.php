<?php

declare(strict_types=1);

namespace Framework\Queue;

class Envelope
{
    public function __construct(
        public readonly string      $id,
        public readonly JobInterface $job,
        public readonly int         $attempts,
        public readonly int         $maxAttempts,
        public readonly int         $availableAt,
    ) {}

    public function withAttempt(): static
    {
        return new static(
            $this->id,
            $this->job,
            $this->attempts + 1,
            $this->maxAttempts,
            $this->availableAt,
        );
    }

    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->maxAttempts;
    }
}
