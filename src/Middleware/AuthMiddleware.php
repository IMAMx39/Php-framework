<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Auth\Auth;
use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Protège les routes réservées aux utilisateurs connectés.
 *
 * Si l'utilisateur n'est pas authentifié, il est redirigé vers $redirectTo.
 *
 * Enregistrement dans Application :
 *   $app->addMiddleware(new AuthMiddleware($auth));
 *
 * Ou sur un groupe de routes dans config/routes.php :
 *   // via le middleware pipeline appliqué dans le contrôleur
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Auth   $auth,
        private readonly string $redirectTo = '/login',
    ) {}

    public function process(Request $request, callable $next): Response
    {
        if ($this->auth->guest()) {
            return Response::redirect($this->redirectTo);
        }

        return $next($request);
    }
}
