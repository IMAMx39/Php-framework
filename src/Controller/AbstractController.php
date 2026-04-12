<?php

declare(strict_types=1);

namespace Framework\Controller;

use Framework\Container\Container;
use Framework\Http\JsonResponse;
use Framework\Http\Response;

abstract class AbstractController
{
    private Container $container;

    /** @internal Appelé par le Kernel avant d'invoquer l'action. */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    // ------------------------------------------------------------------
    // Helpers réponse
    // ------------------------------------------------------------------

    protected function response(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }

    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    // ------------------------------------------------------------------
    // Accès au conteneur
    // ------------------------------------------------------------------

    protected function get(string $id): mixed
    {
        return $this->container->get($id);
    }
}
