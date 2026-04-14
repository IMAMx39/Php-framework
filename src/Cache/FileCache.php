<?php

declare(strict_types=1);

namespace Framework\Cache;

/**
 * Cache sur disque.
 * Chaque entrée est un fichier PHP sérialisé dans $directory.
 * Le nom de fichier est un hash SHA-256 de la clé (évite les conflits de nommage).
 */
class FileCache implements CacheInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, recursive: true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        $data = $this->read($key);

        return $data['value'];
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $data = [
            'value'      => $value,
            'expires_at' => $ttl !== null ? time() + $ttl : null,
        ];

        file_put_contents(
            $this->path($key),
            serialize($data),
            LOCK_EX,
        );
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function has(string $key): bool
    {
        $data = $this->read($key);

        if ($data === null) {
            return false;
        }

        if ($data['expires_at'] !== null && time() > $data['expires_at']) {
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
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    // ------------------------------------------------------------------
    // Helpers internes
    // ------------------------------------------------------------------

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }

    /** @return array{value: mixed, expires_at: int|null}|null */
    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = unserialize($content);

        if (!is_array($data) || !array_key_exists('value', $data)) {
            return null;
        }

        return $data;
    }
}
