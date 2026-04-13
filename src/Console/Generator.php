<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Écrit des fichiers générés sur le disque.
 *
 * Injectable dans les commandes make:* pour permettre les tests
 * sans écrire dans les vrais dossiers du projet.
 */
class Generator
{
    public function __construct(private readonly string $basePath) {}

    /**
     * Crée le fichier (répertoires inclus).
     * Retourne false si le fichier existe déjà.
     */
    public function write(string $relativePath, string $content): bool
    {
        $fullPath = $this->basePath . '/' . ltrim($relativePath, '/');

        if (file_exists($fullPath)) {
            return false;
        }

        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        file_put_contents($fullPath, $content);

        return true;
    }

    public function exists(string $relativePath): bool
    {
        return file_exists($this->basePath . '/' . ltrim($relativePath, '/'));
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
