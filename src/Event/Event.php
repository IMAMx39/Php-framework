<?php

declare(strict_types=1);

namespace Framework\Event;

/**
 * Classe de base pour tous les événements du framework.
 *
 * Étendre cette classe pour créer des événements personnalisés :
 *
 *   class UserRegistered extends Event {
 *       public function __construct(public readonly User $user) {}
 *   }
 *
 *   $dispatcher->on('user.registered', function (UserRegistered $e) {
 *       sendWelcomeEmail($e->user);
 *   });
 *
 *   $dispatcher->emit('user.registered', new UserRegistered($user));
 */
class Event
{
    private bool $propagationStopped = false;

    /**
     * Arrête la propagation aux listeners suivants.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
