<?php

declare(strict_types=1);

namespace Framework\Pipeline;

use Framework\Container\Container;

/**
 * Fait transiter une valeur à travers une série d'étapes ordonnées.
 *
 * Chaque étape (pipe) est une classe avec une méthode handle() :
 *
 *   class TrimInput {
 *       public function handle(string $value, callable $next): string {
 *           return $next(trim($value));
 *       }
 *   }
 *
 * Usage :
 *   $result = Pipeline::send($value)
 *       ->through([StepA::class, StepB::class])
 *       ->thenReturn();
 *
 *   // Court-circuit : une étape arrête le pipeline
 *   class CheckBanned {
 *       public function handle(User $user, callable $next): Response {
 *           if ($user->banned) return new Response('Interdit', 403);
 *           return $next($user);
 *       }
 *   }
 */
class Pipeline
{
    private mixed $passable;

    /** @var array<int, string|callable|object> */
    private array $pipes = [];

    private string $method = 'handle';

    public function __construct(private readonly ?Container $container = null) {}

    public static function send(mixed $passable): static
    {
        $instance           = new static();
        $instance->passable = $passable;

        return $instance;
    }

    /**
     * Étapes à traverser — classes, callables, ou objets instanciés.
     *
     * @param array<string|callable|object> $pipes
     */
    public function through(array $pipes): static
    {
        $clone        = clone $this;
        $clone->pipes = $pipes;

        return $clone;
    }

    /**
     * Ajoute une étape supplémentaire en fin de pipeline.
     */
    public function pipe(string|callable|object $pipe): static
    {
        $clone          = clone $this;
        $clone->pipes[] = $pipe;

        return $clone;
    }

    /**
     * Nom de la méthode à appeler sur chaque étape-classe (défaut : "handle").
     */
    public function via(string $method): static
    {
        $clone         = clone $this;
        $clone->method = $method;

        return $clone;
    }

    /**
     * Lance le pipeline et retourne la valeur finale via $destination.
     */
    public function then(callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->buildLayer(),
            $destination,
        );

        return $pipeline($this->passable);
    }

    /**
     * Lance le pipeline et retourne directement la valeur transformée.
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn (mixed $passable) => $passable);
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    private function buildLayer(): callable
    {
        return function (callable $next, mixed $pipe): callable {
            return function (mixed $passable) use ($next, $pipe): mixed {
                if (is_callable($pipe)) {
                    return $pipe($passable, $next);
                }

                $instance = is_object($pipe)
                    ? $pipe
                    : ($this->container?->make($pipe) ?? new $pipe());

                return $instance->{$this->method}($passable, $next);
            };
        };
    }
}
