<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Session\Session;

/**
 * Génère et valide les tokens CSRF stockés en session.
 *
 * Usage dans un formulaire (via Twig) :
 *   {{ csrf_field() }}    → <input type="hidden" name="_csrf_token" value="...">
 *
 * Usage dans un middleware :
 *   $manager->validate($request->post('_csrf_token'))
 *
 * Le token est généré une fois par session et réutilisé (stratégie stateless-friendly).
 * Appelez refresh() pour forcer la rotation du token (ex: après login).
 */
class CsrfTokenManager
{
    public const SESSION_KEY  = '_csrf_token';
    public const FIELD_NAME   = '_csrf_token';
    public const HEADER_NAME  = 'X-CSRF-TOKEN';

    public function __construct(private readonly Session $session) {}

    /**
     * Retourne le token courant, ou en génère un nouveau si absent.
     */
    public function getToken(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, $this->generate());
        }

        return $this->session->get(self::SESSION_KEY);
    }

    /**
     * Génère un nouveau token et le stocke en session.
     */
    public function refresh(): string
    {
        $token = $this->generate();
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Vérifie que le token soumis correspond au token en session.
     * Utilise hash_equals() pour prévenir les attaques timing.
     */
    public function validate(?string $submittedToken): bool
    {
        if ($submittedToken === null || $submittedToken === '') {
            return false;
        }

        $sessionToken = $this->session->get(self::SESSION_KEY, '');

        if ($sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    // ------------------------------------------------------------------

    private function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
