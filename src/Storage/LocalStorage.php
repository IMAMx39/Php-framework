<?php

declare(strict_types=1);

namespace Framework\Storage;

/**
 * Storage local sur le système de fichiers.
 *
 * Les fichiers sont stockés dans $root (ex: /var/www/app/storage/app/).
 * L'URL publique est générée à partir de $baseUrl (ex: /storage/).
 *
 * Exemple de configuration dans le conteneur :
 *   $container->singleton(StorageInterface::class, fn() => new LocalStorage(
 *       root:    dirname(__DIR__) . '/storage/app',
 *       baseUrl: '/storage',
 *   ));
 */
class LocalStorage implements StorageInterface
{
    public function __construct(
        private readonly string $root,
        private readonly string $baseUrl = '/storage',
    ) {
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, recursive: true);
        }
    }

    // ------------------------------------------------------------------

    public function put(string $path, string $contents): string
    {
        $full = $this->path($path);
        $dir  = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        file_put_contents($full, $contents, LOCK_EX);

        return $path;
    }

    public function get(string $path): ?string
    {
        $full = $this->path($path);

        if (!file_exists($full)) {
            return null;
        }

        $content = file_get_contents($full);

        return $content !== false ? $content : null;
    }

    public function delete(string $path): void
    {
        $full = $this->path($path);

        if (file_exists($full)) {
            unlink($full);
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function path(string $path): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? $this->root : $this->root . '/' . $path;
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $dir  = $this->path($directory);
        $glob = $recursive
            ? $this->globRecursive($dir)
            : (glob($dir . '/*') ?: []);

        $root = rtrim($this->root, '/') . '/';

        return array_values(array_filter(
            array_map(
                fn ($f) => is_file($f) ? str_replace($root, '', $f) : null,
                $glob,
            ),
        ));
    }

    public function putUpload(array $uploadedFile, string $directory = 'uploads', ?string $name = null): string
    {
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            throw new \RuntimeException('Fichier uploadé invalide.');
        }

        $extension = pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION);
        $filename  = $name ?? (bin2hex(random_bytes(16)) . ($extension ? ".{$extension}" : ''));
        $path      = trim($directory, '/') . '/' . $filename;
        $full      = $this->path($path);
        $dir       = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        if (!move_uploaded_file($uploadedFile['tmp_name'], $full)) {
            throw new \RuntimeException("Impossible de déplacer le fichier uploadé vers {$full}.");
        }

        return $path;
    }

    // ------------------------------------------------------------------

    private function globRecursive(string $dir): array
    {
        $results = glob($dir . '/*') ?: [];
        $files   = [];

        foreach ($results as $item) {
            if (is_dir($item)) {
                array_push($files, ...$this->globRecursive($item));
            } else {
                $files[] = $item;
            }
        }

        return $files;
    }
}
