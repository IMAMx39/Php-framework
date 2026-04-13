<?php

declare(strict_types=1);

namespace Framework\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Framework\Session\Session;

/**
 * Service d'authentification.
 *
 * Utilisation typique dans un contrôleur :
 *
 *   // Connexion
 *   if ($this->auth->attempt($email, $password)) {
 *       return Response::redirect('/dashboard');
 *   }
 *
 *   // Utilisateur courant
 *   $user = $this->auth->user();   // ?User
 *
 *   // Déconnexion
 *   $this->auth->logout();
 *   return Response::redirect('/login');
 */
class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    /** Cache de l'utilisateur résolu pour la requête courante */
    private ?User $resolvedUser = null;

    public function __construct(
        private readonly Session        $session,
        private readonly UserRepository $users,
    ) {}

    // ------------------------------------------------------------------
    // Authentification
    // ------------------------------------------------------------------

    /**
     * Vérifie les identifiants et ouvre la session si valides.
     *
     * @return bool true si connecté, false si identifiants incorrects.
     */
    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        if (!$user->isActive()) {
            return false;
        }

        $this->login($user);

        return true;
    }

    /**
     * Connecte directement un utilisateur (sans vérification de mot de passe).
     * Utile après inscription ou dans les tests.
     */
    public function login(User $user): void
    {
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->getId());
        $this->resolvedUser = $user;
    }

    /**
     * Déconnecte l'utilisateur courant.
     */
    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate();
        $this->resolvedUser = null;
    }

    // ------------------------------------------------------------------
    // État de la session
    // ------------------------------------------------------------------

    /**
     * L'utilisateur est-il connecté ?
     */
    public function check(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    /**
     * L'utilisateur est-il un visiteur non connecté ?
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Retourne l'utilisateur connecté ou null.
     * Résolution lazy avec cache pour la requête courante.
     */
    public function user(): ?User
    {
        if ($this->resolvedUser !== null) {
            return $this->resolvedUser;
        }

        $id = $this->id();

        if ($id === null) {
            return null;
        }

        $this->resolvedUser = $this->users->find($id);

        return $this->resolvedUser;
    }

    /**
     * Retourne l'ID de l'utilisateur connecté ou null.
     */
    public function id(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }
}
