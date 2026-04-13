<?php

declare(strict_types=1);

namespace Framework\Event;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Événement émis après le dispatch, avant l'envoi de la réponse (kernel.response).
 *
 * Utile pour ajouter des headers, modifier le contenu, logger les réponses, etc.
 *
 *   $dispatcher->on(KernelEvents::RESPONSE, function (ResponseEvent $e) {
 *       $e->getResponse()->setHeader('X-Frame-Options', 'DENY');
 *   });
 */
class ResponseEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private Response $response,
    ) {}

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
