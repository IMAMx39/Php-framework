<?php

declare(strict_types=1);

namespace Framework\Event;

/**
 * Dispatcher d'événements inspiré de Symfony EventDispatcher.
 *
 * Enregistrement d'un listener :
 *   $dispatcher->on('user.registered', $listener);
 *   $dispatcher->on('user.registered', $listener, priority: 10);  // priorité haute = appelé en premier
 *
 * Émission d'un événement :
 *   $event = $dispatcher->emit('user.registered', new UserRegistered($user));
 *
 * Suppression d'un listener :
 *   $dispatcher->off('user.registered', $listener);
 */
class EventDispatcher
{
    /**
     * Structure : [eventName => [[listener, priority], ...]]
     * Non trié — trié à la demande via $this->sorted.
     *
     * @var array<string, array<int, array{callable, int}>>
     */
    private array $listeners = [];

    /**
     * Cache des listeners triés par priorité décroissante.
     *
     * @var array<string, callable[]>
     */
    private array $sorted = [];

    // ------------------------------------------------------------------
    // Enregistrement
    // ------------------------------------------------------------------

    /**
     * Abonne un listener à un événement.
     *
     * @param int $priority Plus la priorité est haute, plus le listener est appelé tôt.
     */
    public function on(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][] = [$listener, $priority];
        unset($this->sorted[$eventName]); // invalide le cache trié
    }

    /**
     * Désabonne un listener d'un événement.
     */
    public function off(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $key => [$registered]) {
            if ($registered === $listener) {
                unset($this->listeners[$eventName][$key]);
                unset($this->sorted[$eventName]);

                return;
            }
        }
    }

    // ------------------------------------------------------------------
    // Émission
    // ------------------------------------------------------------------

    /**
     * Émet un événement et appelle tous les listeners dans l'ordre de priorité.
     *
     * @template T of Event
     * @param T $event
     * @return T
     */
    public function emit(string $eventName, Event $event): Event
    {
        foreach ($this->getListeners($eventName) as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    // ------------------------------------------------------------------
    // Introspection
    // ------------------------------------------------------------------

    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    /**
     * Retourne les listeners triés par priorité décroissante.
     *
     * @return callable[]
     */
    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        if (!isset($this->sorted[$eventName])) {
            $entries = $this->listeners[$eventName];
            usort($entries, fn ($a, $b) => $b[1] <=> $a[1]); // tri décroissant
            $this->sorted[$eventName] = array_column($entries, 0);
        }

        return $this->sorted[$eventName];
    }

    /**
     * Retourne tous les noms d'événements ayant au moins un listener.
     *
     * @return string[]
     */
    public function getEventNames(): array
    {
        return array_keys(array_filter($this->listeners));
    }
}
