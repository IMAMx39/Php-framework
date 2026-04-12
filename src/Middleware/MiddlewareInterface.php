<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

interface MiddlewareInterface
{
    /**
     * Traite la requête et délègue au middleware suivant via $next.
     *
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
