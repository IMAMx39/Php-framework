<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Exception\ForbiddenException;

/**
 * Système d'autorisation — Gates simples et Policies par entité.
 *
 * Usage :
 *   // Définir une gate
 *   $gate->define('admin', fn(User $u) => $u->role === 'admin');
 *   $gate->define('edit-post', fn(User $u, Post $p) => $u->id === $p->userId);
 *
 *   // Enregistrer une policy
 *   $gate->policy(Post::class, PostPolicy::class);
 *
 *   // Vérifier
 *   $gate->allows('admin');
 *   $gate->allows('edit-post', $post);
 *   $gate->authorize('edit-post', $post);  // lève ForbiddenException si refusé
 */
class Gate
{
    /** @var array<string, callable> */
    private array $definitions = [];

    /** @var array<string, string> entity class → policy class */
    private array $policies = [];

    /**
     * @param callable(): object|null $userResolver Retourne l'utilisateur connecté ou null.
     */
    public function __construct(private readonly mixed $userResolver) {}

    // ------------------------------------------------------------------
    // Enregistrement
    // ------------------------------------------------------------------

    public function define(string $ability, callable $callback): void
    {
        $this->definitions[$ability] = $callback;
    }

    public function policy(string $modelClass, string $policyClass): void
    {
        $this->policies[$modelClass] = $policyClass;
    }

    // ------------------------------------------------------------------
    // Vérification
    // ------------------------------------------------------------------

    public function allows(string $ability, mixed $model = null): bool
    {
        $user = ($this->userResolver)();

        if ($user === null) {
            return false;
        }

        // Policy en priorité si un modèle est fourni
        if ($model !== null) {
            $result = $this->checkPolicy($ability, $user, $model);
            if ($result !== null) {
                return $result;
            }
        }

        // Gate définie manuellement
        if (isset($this->definitions[$ability])) {
            return (bool) ($this->definitions[$ability])($user, $model);
        }

        return false;
    }

    public function denies(string $ability, mixed $model = null): bool
    {
        return !$this->allows($ability, $model);
    }

    /**
     * Lève une ForbiddenException (HTTP 403) si l'accès est refusé.
     */
    public function authorize(string $ability, mixed $model = null): void
    {
        if ($this->denies($ability, $model)) {
            throw new ForbiddenException("Action « {$ability} » non autorisée.");
        }
    }

    /**
     * Vérifie si l'utilisateur connecté possède un des rôles donnés.
     */
    public function hasRole(string ...$roles): bool
    {
        $user = ($this->userResolver)();

        if ($user === null) {
            return false;
        }

        $userRole = $user->role ?? null;

        return in_array($userRole, $roles, strict: true);
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    private function checkPolicy(string $ability, object $user, mixed $model): ?bool
    {
        $modelClass  = get_class($model);
        $policyClass = $this->policies[$modelClass] ?? null;

        if ($policyClass === null) {
            return null;
        }

        $policy = new $policyClass();

        if (!method_exists($policy, $ability)) {
            return null;
        }

        return (bool) $policy->$ability($user, $model);
    }
}
