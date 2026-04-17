<?php

declare(strict_types=1);

namespace Framework\RateLimiter;

use Framework\Cache\CacheInterface;

/**
 * Limiteur de débit basé sur le Cache.
 *
 * Stratégie : fenêtre fixe (fixed window).
 * Le compteur est stocké en cache sous la clé « rate::{key} » avec un TTL = $decaySeconds.
 *
 * Usage :
 *   $limiter = new RateLimiter($cache);
 *
 *   // Tentative de login
 *   if ($limiter->tooManyAttempts("login:{$ip}", maxAttempts: 5, decaySeconds: 60)) {
 *       // 429 Too Many Requests
 *   }
 *   $limiter->hit("login:{$ip}", decaySeconds: 60);
 *
 *   // Version one-liner (hit + check) :
 *   if (!$limiter->attempt("login:{$ip}", maxAttempts: 5, decaySeconds: 60)) {
 *       // limite dépassée
 *   }
 */
class RateLimiter
{
    private const PREFIX = 'rate::';

    public function __construct(private readonly CacheInterface $cache) {}

    // ------------------------------------------------------------------
    // API principale
    // ------------------------------------------------------------------

    /**
     * Incrémente le compteur ET retourne true si la limite n'est pas dépassée.
     * Retourne false si la limite est atteinte (la tentative est quand même comptée).
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->hit($key, $decaySeconds);

        return !$this->tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Incrémente le compteur de tentatives.
     * Si la clé n'existe pas encore, elle est créée avec TTL = $decaySeconds.
     */
    public function hit(string $key, int $decaySeconds): int
    {
        $cacheKey = $this->cacheKey($key);
        $current  = (int) $this->cache->get($cacheKey, 0);

        if ($current === 0) {
            $this->cache->put($cacheKey, 1, $decaySeconds);
            return 1;
        }

        // Incrémente sans réinitialiser le TTL
        $new = $current + 1;
        $this->cache->put($cacheKey, $new, $decaySeconds);

        return $new;
    }

    /**
     * Vérifie si la limite est dépassée (sans incrémenter).
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Nombre de tentatives effectuées dans la fenêtre courante.
     */
    public function attempts(string $key): int
    {
        return (int) $this->cache->get($this->cacheKey($key), 0);
    }

    /**
     * Nombre de tentatives restantes avant le blocage.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Remet le compteur à zéro.
     */
    public function clear(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
    }

    // ------------------------------------------------------------------

    private function cacheKey(string $key): string
    {
        return self::PREFIX . $key;
    }
}
