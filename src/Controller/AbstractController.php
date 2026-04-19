<?php

declare(strict_types=1);

namespace Framework\Controller;

use Framework\Container\Container;
use Framework\Http\JsonResponse;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\ORM\EntityManager;
use Framework\Template\TwigRenderer;
use Framework\Validation\Validator;

abstract class AbstractController
{
    private Container $container;

    protected EntityManager $em;

    /** @internal Appelé par le Kernel avant d'invoquer l'action. */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
        $this->em        = $container->get(EntityManager::class);
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

    /**
     * Rend un template Twig et retourne une Response HTML.
     *
     * Exemple :
     *   return $this->render('home/index.html.twig', ['user' => $user]);
     *
     * @param array<string, mixed> $context Variables passées au template.
     */
    protected function render(string $template, array $context = [], int $status = 200): Response
    {
        $html = $this->get(TwigRenderer::class)->render($template, $context);

        return new Response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Valide les données de la requête selon les règles données.
     * Lève une ValidationException (HTTP 422) si la validation échoue.
     *
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules): array
    {
        $data = $request->isJson()
            ? (array) $request->json()
            : $request->all();

        return Validator::make($data, $rules);
    }

    // ------------------------------------------------------------------
    // Accès au conteneur
    // ------------------------------------------------------------------

    protected function get(string $id): mixed
    {
        return $this->container->get($id);
    }
}
