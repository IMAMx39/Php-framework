<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Auth\Auth;
use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Protège les routes réservées aux visiteurs non connectés (/login, /register).
 *
 * Si l'utilisateur est déjà authentifié, il est redirigé vers $redirectTo.
 */
class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Auth   $auth,
        private readonly string $redirectTo = '/dashboard',
    ) {}

    public function process(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect($this->redirectTo);
        }

        return $next($request);
    }
}
