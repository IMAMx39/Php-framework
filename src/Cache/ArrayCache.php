<?php

declare(strict_types=1);

namespace Framework\Cache;

/**
 * Cache en mémoire (tableau PHP).
 * Durée de vie respectée à la seconde près via time().
 * Idéal pour les tests et les environnements sans système de fichiers.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires_at: int|null}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key]['value'];
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => $ttl !== null ? time() + $ttl : null,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        $expiresAt = $this->store[$key]['expires_at'];

        if ($expiresAt !== null && time() > $expiresAt) {
            $this->forget($key);

            return false;
        }

        return true;
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function flush(): void
    {
        $this->store = [];
    }
}
