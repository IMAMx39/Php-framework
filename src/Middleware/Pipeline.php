<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Exécute une liste de middlewares en chaîne (pattern Pipeline / Onion).
 *
 * Chaque middleware reçoit la requête et un callable $next qui pointe
 * vers le middleware suivant. Le dernier $next est le handler final du Kernel.
 *
 * Ordre d'exécution (ex: 3 middlewares + handler) :
 *   MW1::before → MW2::before → MW3::before → handler → MW3::after → MW2::after → MW1::after
 */
class Pipeline
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    public function pipe(MiddlewareInterface $middleware): static
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * @param callable(Request): Response $destination Handler final (le Kernel dispatch)
     */
    public function run(Request $request, callable $destination): Response
    {
        $chain = $this->buildChain($destination);

        return $chain($request);
    }

    /**
     * Construit récursivement la chaîne de callbacks de l'intérieur vers l'extérieur.
     *
     * @param callable(Request): Response $destination
     * @return callable(Request): Response
     */
    private function buildChain(callable $destination): callable
    {
        $chain = $destination;

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next  = $chain;
            $chain = static fn (Request $request): Response => $middleware->process($request, $next);
        }

        return $chain;
    }
}
