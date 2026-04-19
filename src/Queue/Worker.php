<?php

declare(strict_types=1);

namespace Framework\Queue;

use Framework\Container\Container;

class Worker
{
    public function __construct(
        private readonly QueueInterface $queue,
        private readonly Container      $container,
    ) {}

    /**
     * Traite un seul job et retourne true si un job a été traité, false si la queue était vide.
     */
    public function processOne(): bool
    {
        $envelope = $this->queue->pop();

        if ($envelope === null) {
            return false;
        }

        try {
            $this->dispatch($envelope->job);
            $this->queue->ack($envelope);
        } catch (\Throwable $e) {
            $this->queue->nack($envelope);
            throw $e;
        }

        return true;
    }

    /**
     * Boucle infinie — traite les jobs jusqu'à l'arrêt du processus.
     *
     * @param int $sleep Secondes à attendre quand la queue est vide.
     * @param callable|null $onIdle Callback appelé quand la queue est vide.
     */
    public function work(int $sleep = 1, ?callable $onIdle = null): void
    {
        while (true) {
            if (!$this->processOne()) {
                if ($onIdle !== null) {
                    ($onIdle)();
                }

                sleep($sleep);
            }
        }
    }

    // ------------------------------------------------------------------
    // Résolution des dépendances de handle() via le container
    // ------------------------------------------------------------------

    private function dispatch(JobInterface $job): void
    {
        if (!method_exists($job, 'handle')) {
            throw new \LogicException(get_class($job) . ' doit définir une méthode handle().');
        }

        $reflection = new \ReflectionMethod($job, 'handle');
        $args       = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->container->get($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(
                "Impossible de résoudre le paramètre \${$param->getName()} de " . get_class($job) . '::handle().'
            );
        }

        $job->handle(...$args);
    }
}
