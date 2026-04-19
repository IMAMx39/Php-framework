<?php

declare(strict_types=1);

namespace Framework\Queue\Driver;

use Framework\Queue\Envelope;
use Framework\Queue\JobInterface;
use Framework\Queue\QueueInterface;

/**
 * Exécute les jobs immédiatement dans le même processus.
 * Idéal pour les tests et le développement.
 */
class SyncQueue implements QueueInterface
{
    /** @var Envelope[] */
    private array $pending = [];

    public function push(JobInterface $job, int $delay = 0): void
    {
        $this->pending[] = new Envelope(
            id:           uniqid('sync_', more_entropy: true),
            job:          $job,
            attempts:     0,
            maxAttempts:  3,
            availableAt:  time() + $delay,
        );
    }

    public function pop(): ?Envelope
    {
        $now = time();

        foreach ($this->pending as $key => $envelope) {
            if ($envelope->availableAt <= $now) {
                unset($this->pending[$key]);
                return $envelope->withAttempt();
            }
        }

        return null;
    }

    public function ack(Envelope $envelope): void
    {
        // Rien à faire — déjà retiré dans pop()
    }

    public function nack(Envelope $envelope, int $delay = 5): void
    {
        $this->pending[] = new Envelope(
            $envelope->id,
            $envelope->job,
            $envelope->attempts,
            $envelope->maxAttempts,
            time() + $delay,
        );
    }

    public function flush(): void
    {
        $this->pending = [];
    }

    public function size(): int
    {
        return count($this->pending);
    }
}
