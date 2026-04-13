<?php

declare(strict_types=1);

namespace Framework\Event;

/**
 * Constantes des événements internes du Kernel.
 *
 * Usage :
 *   $dispatcher->on(KernelEvents::REQUEST, function (RequestEvent $e) { ... });
 *   $dispatcher->on(KernelEvents::RESPONSE, function (ResponseEvent $e) { ... });
 *   $dispatcher->on(KernelEvents::EXCEPTION, function (ExceptionEvent $e) { ... });
 */
final class KernelEvents
{
    /**
     * Émis avant le dispatch vers le contrôleur.
     * Un listener peut court-circuiter en appelant $event->setResponse().
     */
    public const REQUEST = 'kernel.request';

    /**
     * Émis après le dispatch, avant l'envoi de la réponse.
     * Un listener peut modifier ou remplacer la réponse.
     */
    public const RESPONSE = 'kernel.response';

    /**
     * Émis quand une exception non gérée est levée.
     * Un listener peut fournir une réponse via $event->setResponse().
     */
    public const EXCEPTION = 'kernel.exception';
}
