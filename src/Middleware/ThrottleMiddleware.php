<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\RateLimiter\RateLimiter;

/**
 * Middleware de limitation de débit (rate limiting).
 *
 * La clé est construite à partir du préfixe + l'IP du client.
 * Pour limiter par utilisateur connecté, étendez cette classe et surchargez resolveKey().
 *
 * Exemple :
 *   new ThrottleMiddleware($limiter, maxAttempts: 60, decaySeconds: 60)
 *   → max 60 requêtes par minute par IP
 *
 *   new ThrottleMiddleware($limiter, maxAttempts: 5, decaySeconds: 60, keyPrefix: 'login')
 *   → max 5 tentatives de login par minute par IP
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int         $maxAttempts  = 60,
        private readonly int         $decaySeconds = 60,
        private readonly string      $keyPrefix    = 'global',
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);

        if (!$this->limiter->attempt($key, $this->maxAttempts, $this->decaySeconds)) {
            return $this->tooManyAttemptsResponse($request, $key);
        }

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key);
    }

    // ------------------------------------------------------------------

    protected function resolveKey(Request $request): string
    {
        $ip = $request->server('REMOTE_ADDR', '127.0.0.1');

        return "{$this->keyPrefix}:{$ip}";
    }

    protected function tooManyAttemptsResponse(Request $request, string $key): Response
    {
        return new Response('Too Many Requests.', 429, [
            'Retry-After'           => (string) $this->decaySeconds,
            'X-RateLimit-Limit'     => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => '0',
        ]);
    }

    private function addRateLimitHeaders(Response $response, string $key): Response
    {
        $remaining = $this->limiter->remaining($key, $this->maxAttempts);

        return $response
            ->setHeader('X-RateLimit-Limit',     (string) $this->maxAttempts)
            ->setHeader('X-RateLimit-Remaining', (string) $remaining);
    }
}
