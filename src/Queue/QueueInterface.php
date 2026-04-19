<?php

declare(strict_types=1);

namespace Framework\Queue;

interface QueueInterface
{
    /**
     * Ajoute un job dans la queue.
     *
     * @param int $delay Délai en secondes avant que le job soit disponible.
     */
    public function push(JobInterface $job, int $delay = 0): void;

    /**
     * Récupère et réserve le prochain job disponible.
     * Retourne null si la queue est vide.
     */
    public function pop(): ?Envelope;

    /**
     * Signale qu'un job s'est terminé avec succès (le supprime).
     */
    public function ack(Envelope $envelope): void;

    /**
     * Remet un job en queue pour une nouvelle tentative.
     */
    public function nack(Envelope $envelope, int $delay = 5): void;

    /**
     * Vide complètement la queue.
     */
    public function flush(): void;

    /**
     * Nombre de jobs en attente.
     */
    public function size(): int;
}
