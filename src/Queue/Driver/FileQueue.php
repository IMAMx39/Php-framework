<?php

declare(strict_types=1);

namespace Framework\Queue\Driver;

use Framework\Queue\Envelope;
use Framework\Queue\JobInterface;
use Framework\Queue\QueueInterface;

/**
 * Stocke les jobs dans var/queue/ sous forme de fichiers JSON.
 * Chaque job = un fichier. Aucune dépendance externe.
 */
class FileQueue implements QueueInterface
{
    private string $path;
    private string $failedPath;

    public function __construct(string $storagePath, private readonly int $maxAttempts = 3)
    {
        $this->path       = rtrim($storagePath, '/') . '/queue';
        $this->failedPath = rtrim($storagePath, '/') . '/queue/failed';

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, recursive: true);
        }

        if (!is_dir($this->failedPath)) {
            mkdir($this->failedPath, 0755, recursive: true);
        }
    }

    public function push(JobInterface $job, int $delay = 0): void
    {
        $id       = $this->generateId();
        $envelope = [
            'id'           => $id,
            'payload'      => base64_encode(serialize($job)),
            'attempts'     => 0,
            'max_attempts' => $this->maxAttempts,
            'available_at' => time() + $delay,
            'created_at'   => time(),
        ];

        file_put_contents(
            $this->path . "/{$id}.json",
            json_encode($envelope, JSON_PRETTY_PRINT),
        );
    }

    public function pop(): ?Envelope
    {
        $files = glob($this->path . '/*.json');

        if (empty($files)) {
            return null;
        }

        // Trie par date de création (ordre FIFO)
        usort($files, fn ($a, $b) => filemtime($a) <=> filemtime($b));

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), associative: true);

            if ($data === null || $data['available_at'] > time()) {
                continue;
            }

            $job = unserialize(base64_decode($data['payload']));

            if (!$job instanceof JobInterface) {
                unlink($file);
                continue;
            }

            // Supprime le fichier — le Worker est responsable de nack() si échec
            unlink($file);

            return new Envelope(
                id:          $data['id'],
                job:         $job,
                attempts:    $data['attempts'] + 1,
                maxAttempts: $data['max_attempts'],
                availableAt: $data['available_at'],
            );
        }

        return null;
    }

    public function ack(Envelope $envelope): void
    {
        // Fichier déjà supprimé dans pop() — rien à faire
    }

    public function nack(Envelope $envelope, int $delay = 5): void
    {
        if ($envelope->hasExceededMaxAttempts()) {
            $this->movToFailed($envelope);
            return;
        }

        $data = [
            'id'           => $envelope->id,
            'payload'      => base64_encode(serialize($envelope->job)),
            'attempts'     => $envelope->attempts,
            'max_attempts' => $envelope->maxAttempts,
            'available_at' => time() + $delay,
            'created_at'   => time(),
        ];

        file_put_contents(
            $this->path . "/{$envelope->id}.json",
            json_encode($data, JSON_PRETTY_PRINT),
        );
    }

    public function flush(): void
    {
        foreach (glob($this->path . '/*.json') as $file) {
            unlink($file);
        }
    }

    public function size(): int
    {
        return count(glob($this->path . '/*.json') ?: []);
    }

    // ------------------------------------------------------------------

    private function movToFailed(Envelope $envelope): void
    {
        $data = [
            'id'         => $envelope->id,
            'payload'    => base64_encode(serialize($envelope->job)),
            'attempts'   => $envelope->attempts,
            'failed_at'  => time(),
        ];

        file_put_contents(
            $this->failedPath . "/{$envelope->id}.json",
            json_encode($data, JSON_PRETTY_PRINT),
        );
    }

    private function generateId(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(8));
    }
}
