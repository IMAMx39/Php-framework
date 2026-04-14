<?php

declare(strict_types=1);

namespace Framework\Cache;

/**
 * Contrat minimal de cache key-value avec TTL.
 */
interface CacheInterface
{
    /**
     * Récupère une valeur. Retourne $default si la clé est absente ou expirée.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stocke une valeur.
     *
     * @param int|null $ttl Durée de vie en secondes. null = pas d'expiration.
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Supprime une entrée.
     */
    public function forget(string $key): void;

    /**
     * Vérifie si une entrée existe et n'est pas expirée.
     */
    public function has(string $key): bool;

    /**
     * Retourne la valeur mise en cache, ou exécute $callback, stocke et retourne son résultat.
     *
     * @param int|null $ttl Durée de vie en secondes pour la mise en cache.
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed;

    /**
     * Vide tout le cache.
     */
    public function flush(): void;
}
