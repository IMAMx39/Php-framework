<?php

declare(strict_types=1);

namespace Framework\Storage;

interface StorageInterface
{
    /**
     * Écrit le contenu dans le fichier. Crée les répertoires si nécessaire.
     * Retourne le chemin relatif stocké.
     */
    public function put(string $path, string $contents): string;

    /**
     * Lit le contenu d'un fichier. Retourne null si absent.
     */
    public function get(string $path): ?string;

    /**
     * Supprime un fichier. Silencieux si absent.
     */
    public function delete(string $path): void;

    /**
     * Vérifie si un fichier existe.
     */
    public function exists(string $path): bool;

    /**
     * Chemin absolu sur le disque.
     */
    public function path(string $path): string;

    /**
     * URL publique accessible depuis le navigateur.
     */
    public function url(string $path): string;

    /**
     * Liste les fichiers d'un répertoire (non récursif par défaut).
     *
     * @return string[] Chemins relatifs.
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * Copie un fichier uploadé ($_FILES) vers le storage.
     * Retourne le chemin relatif du fichier stocké.
     *
     * @param array{tmp_name: string, name: string} $uploadedFile Entrée de $_FILES.
     * @param string $directory Répertoire cible dans le storage.
     * @param string|null $name Nom du fichier (généré si null).
     */
    public function putUpload(array $uploadedFile, string $directory = 'uploads', ?string $name = null): string;
}
