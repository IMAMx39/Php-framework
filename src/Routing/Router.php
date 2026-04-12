<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Exception\HttpNotFoundException;
use Framework\Exception\MethodNotAllowedException;
use Framework\Http\Request;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    // ------------------------------------------------------------------
    // Enregistrement des routes
    // ------------------------------------------------------------------

    public function add(string $path, array $methods, mixed $handler, ?string $name = null): Route
    {
        $methods = array_map('strtoupper', $methods);
        $route   = new Route($path, $methods, $handler, $name);

        $this->routes[] = $route;

        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }

        return $route;
    }

    public function get(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add($path, ['GET'], $handler, $name);
    }

    public function post(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add($path, ['POST'], $handler, $name);
    }

    public function put(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add($path, ['PUT'], $handler, $name);
    }

    public function patch(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add($path, ['PATCH'], $handler, $name);
    }

    public function delete(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add($path, ['DELETE'], $handler, $name);
    }

    // ------------------------------------------------------------------
    // Résolution
    // ------------------------------------------------------------------

    /**
     * Cherche la route correspondant à la requête.
     *
     * @throws MethodNotAllowedException Si le chemin existe mais pas la méthode.
     * @throws HttpNotFoundException     Si aucune route ne correspond.
     */
    public function match(Request $request): Route
    {
        $uri    = $request->getUri();
        $method = $request->getMethod();

        $pathMatched = false;

        foreach ($this->routes as $route) {
            $pattern = $route->buildPattern();

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            $pathMatched = true;

            if (!in_array($method, $route->getMethods(), true)) {
                continue;
            }

            // On extrait uniquement les paramètres nommés
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $route->setParameters($params);

            return $route;
        }

        if ($pathMatched) {
            throw new MethodNotAllowedException("Méthode $method non autorisée pour $uri.");
        }

        throw new HttpNotFoundException("Aucune route trouvée pour $method $uri.");
    }

    // ------------------------------------------------------------------
    // Génération d'URL
    // ------------------------------------------------------------------

    /**
     * Génère l'URL d'une route nommée.
     *
     * @param array<string, string> $params Paramètres dynamiques.
     */
    public function generate(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route nommée '$name' introuvable.");
        }

        $path = $this->namedRoutes[$name]->getPath();

        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", (string) $value, $path);
        }

        return $path;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
