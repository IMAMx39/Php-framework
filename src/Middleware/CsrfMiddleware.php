<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Security\CsrfTokenManager;

/**
 * Protège les méthodes non-idempotentes (POST, PUT, PATCH, DELETE) contre les attaques CSRF.
 *
 * Le token est lu dans cet ordre :
 *   1. Champ POST  « _csrf_token »
 *   2. En-tête HTTP « X-CSRF-TOKEN » (pour les requêtes AJAX)
 *
 * Routes exemptées : passez leurs préfixes dans $exemptPaths.
 *
 * Exemple d'enregistrement :
 *   $app->addMiddleware(new CsrfMiddleware($csrfManager, exemptPaths: ['/api/']));
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param string[] $exemptPaths Préfixes d'URI exemptés de la vérification.
     */
    public function __construct(
        private readonly CsrfTokenManager $csrfManager,
        private readonly array            $exemptPaths = [],
    ) {}

    public function process(Request $request, callable $next): Response
    {
        if (!$this->requiresProtection($request)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if (!$this->csrfManager->validate($token)) {
            return new Response('Invalid CSRF token.', 403);
        }

        return $next($request);
    }

    // ------------------------------------------------------------------

    private function requiresProtection(Request $request): bool
    {
        if (!in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            return false;
        }

        $uri = $request->getUri();

        foreach ($this->exemptPaths as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function extractToken(Request $request): ?string
    {
        // 1. Champ POST
        $token = $request->post(CsrfTokenManager::FIELD_NAME);

        if ($token !== null && $token !== '') {
            return (string) $token;
        }

        // 2. En-tête HTTP
        return $request->header(CsrfTokenManager::HEADER_NAME);
    }
}
