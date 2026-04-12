<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Détecte les requêtes avec Content-Type: application/json
 * et force le header Accept: application/json en retour.
 *
 * Utile pour des APIs pures : garantit que les erreurs
 * sont aussi renvoyées en JSON.
 */
class JsonBodyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if ($request->isJson() && !$response->getHeaders()['Content-Type'] ?? false) {
            $response->setHeader('Content-Type', 'application/json');
        }

        return $response;
    }
}
