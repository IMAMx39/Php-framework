<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Container\Container;
use Framework\Controller\AbstractController;
use Framework\Exception\HttpException;
use Framework\Http\JsonResponse;
use Framework\Template\TwigRenderer;
use Framework\Validation\ValidationException;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\MiddlewareInterface;
use Framework\Middleware\Pipeline;
use Framework\Routing\Router;

class Kernel
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
    ) {}

    // ------------------------------------------------------------------
    // Enregistrement des middlewares globaux
    // ------------------------------------------------------------------

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    // ------------------------------------------------------------------
    // Point d'entrée principal
    // ------------------------------------------------------------------

    public function handle(Request $request): Response
    {
        try {
            $pipeline = new Pipeline();

            foreach ($this->middlewares as $middleware) {
                $pipeline->pipe($middleware);
            }

            return $pipeline->run($request, fn (Request $req) => $this->dispatch($req));
        } catch (ValidationException $e) {
            return new JsonResponse(['message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (\Throwable $e) {
            return $this->handleServerError($e);
        }
    }

    // ------------------------------------------------------------------
    // Dispatch vers le handler de route
    // ------------------------------------------------------------------

    private function dispatch(Request $request): Response
    {
        $route  = $this->router->match($request);
        $result = $this->callHandler($route->getHandler(), $request, $route->getParameters());

        return $this->prepareResponse($result);
    }

    private function callHandler(mixed $handler, Request $request, array $params): mixed
    {
        // Closure ou callable anonyme
        if ($handler instanceof \Closure) {
            return $this->invoke($handler, $request, $params);
        }

        // "Controller@method" ou "Controller::method"
        if (is_string($handler)) {
            $separator = str_contains($handler, '@') ? '@' : '::';
            $handler   = explode($separator, $handler, 2);
        }

        // [ControllerClass::class, 'method']
        if (is_array($handler)) {
            [$class, $method] = $handler;

            $controller = $this->container->make($class);

            if ($controller instanceof AbstractController) {
                $controller->setContainer($this->container);
            }

            return $this->invoke([$controller, $method], $request, $params);
        }

        throw new \RuntimeException('Handler de route invalide.');
    }

    /**
     * Invoque le callable en résolvant ses paramètres automatiquement.
     */
    private function invoke(callable $callable, Request $request, array $routeParams): mixed
    {
        $reflection = is_array($callable)
            ? new \ReflectionMethod($callable[0], $callable[1])
            : new \ReflectionFunction(\Closure::fromCallable($callable));

        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $name = $param->getName();

            // Injection de la Request
            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $args[] = $request;
                continue;
            }

            // Injection depuis le conteneur (service)
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && $this->container->has($type->getName())) {
                $args[] = $this->container->make($type->getName());
                continue;
            }

            // Paramètre dynamique de la route (ex: {id})
            if (array_key_exists($name, $routeParams)) {
                $args[] = $routeParams[$name];
                continue;
            }

            // Valeur par défaut
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            $args[] = null;
        }

        return $callable(...$args);
    }

    // ------------------------------------------------------------------
    // Normalisation de la réponse
    // ------------------------------------------------------------------

    private function prepareResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || (is_object($result) && !($result instanceof \Stringable))) {
            return new JsonResponse($result);
        }

        return new Response((string) $result);
    }

    // ------------------------------------------------------------------
    // Gestion des erreurs
    // ------------------------------------------------------------------

    private function handleHttpException(HttpException $e): Response
    {
        $status = $e->getStatusCode();

        // Tente de rendre un template d'erreur Twig si disponible
        if ($this->container->has(TwigRenderer::class)) {
            try {
                $twig    = $this->container->get(TwigRenderer::class);
                $content = $twig->render("errors/{$status}.html.twig", [
                    'message' => $e->getMessage(),
                    'status'  => $status,
                ]);

                return new Response($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
            } catch (\Throwable) {
                // Template d'erreur absent → réponse texte brute
            }
        }

        return new Response($e->getMessage(), $status);
    }

    private function handleServerError(\Throwable $e): Response
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        if ($debug) {
            $content = sprintf(
                "<h1>%s</h1><p>%s</p><pre>%s</pre>",
                htmlspecialchars(get_class($e)),
                htmlspecialchars($e->getMessage()),
                htmlspecialchars($e->getTraceAsString()),
            );

            return new Response($content, 500);
        }

        return new Response('Erreur interne du serveur.', 500);
    }
}
