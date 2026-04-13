<?php

declare(strict_types=1);

namespace Framework\Session;

/**
 * Wrapper autour des sessions PHP natives.
 *
 * Utilisation :
 *   $session->start();
 *   $session->set('user_id', 42);
 *   $session->get('user_id');          // 42
 *   $session->flash('success', 'Sauvegardé !');
 *   $session->getFlash('success');     // 'Sauvegardé !' (consommé une seule fois)
 */
class Session
{
    // ------------------------------------------------------------------
    // Cycle de vie
    // ------------------------------------------------------------------

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Régénère l'identifiant de session (protection CSRF / fixation).
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function getId(): string
    {
        return session_id() ?: '';
    }

    // ------------------------------------------------------------------
    // Lecture / écriture
    // ------------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Vide intégralement la session.
     */
    public function flush(): void
    {
        $_SESSION = [];
    }

    // ------------------------------------------------------------------
    // Flash messages (one-shot)
    // ------------------------------------------------------------------

    /**
     * Stocke un message flash (visible une seule requête).
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Consomme un message flash (le supprime après lecture).
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * Vérifie si un message flash existe sans le consommer.
     */
    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Retourne tous les messages flash en les vidant.
     *
     * @return array<string, mixed>
     */
    public function pullFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flashes;
    }
}
