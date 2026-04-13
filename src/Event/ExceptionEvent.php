<?php

declare(strict_types=1);

namespace Framework\Event;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Événement émis quand une exception non gérée est levée (kernel.exception).
 *
 * Un listener peut transformer l'exception en réponse HTTP :
 *
 *   $dispatcher->on(KernelEvents::EXCEPTION, function (ExceptionEvent $e) {
 *       if ($e->getThrowable() instanceof AccessDeniedException) {
 *           $e->setResponse(new Response('Accès refusé.', 403));
 *       }
 *   });
 */
class ExceptionEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly Request    $request,
        private readonly \Throwable $throwable,
    ) {}

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
        $this->stopPropagation();
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }
}
