<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Ajoute les headers CORS à chaque réponse.
 *
 * Configuration via le constructeur :
 *
 *   new CorsMiddleware(
 *       origins: ['https://monapp.com'],
 *       methods: ['GET', 'POST', 'PUT', 'DELETE'],
 *       headers: ['Content-Type', 'Authorization'],
 *   )
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly array $origins = ['*'],
        private readonly array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $headers = ['Content-Type', 'Authorization', 'Accept'],
        private readonly bool $credentials = false,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        // Répondre immédiatement aux preflight OPTIONS
        if ($request->isMethod('OPTIONS')) {
            return $this->addCorsHeaders(new Response('', 204), $request);
        }

        return $this->addCorsHeaders($next($request), $request);
    }

    private function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->header('ORIGIN', '');

        if (in_array('*', $this->origins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $this->origins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Vary', 'Origin');
        }

        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->methods));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $this->headers));

        if ($this->credentials) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
