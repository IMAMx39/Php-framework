<?php

declare(strict_types=1);

namespace Framework\Event;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Événement émis avant le dispatch vers le contrôleur (kernel.request).
 *
 * Un listener peut court-circuiter le cycle en fournissant une réponse :
 *
 *   $dispatcher->on(KernelEvents::REQUEST, function (RequestEvent $e) {
 *       if ($e->getRequest()->getUri() === '/maintenance') {
 *           $e->setResponse(new Response('Site en maintenance.', 503));
 *       }
 *   });
 */
class RequestEvent extends Event
{
    private ?Response $response = null;

    public function __construct(private readonly Request $request) {}

    public function getRequest(): Request
    {
        return $this->request;
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
