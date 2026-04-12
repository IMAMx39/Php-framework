<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Routing\Attribute\Route as RouteAttribute;

/**
 * Scanne un répertoire de controllers et enregistre automatiquement
 * les routes définies via l'attribut PHP 8 #[Route(...)].
 *
 * Supporte :
 *   - Un préfixe de chemin au niveau de la classe
 *   - Des routes sur chaque méthode publique
 *
 * Exemple :
 *
 *   #[Route('/api')]
 *   class UserController extends AbstractController
 *   {
 *       #[Route('/users', name: 'user.list')]
 *       public function list(): JsonResponse { ... }
 *
 *       #[Route('/users/{id}', name: 'user.show', methods: ['GET'])]
 *       public function show(int $id): JsonResponse { ... }
 *   }
 */
class AttributeRouteLoader
{
    public function __construct(private readonly Router $router) {}

    /**
     * Charge toutes les routes depuis les controllers d'un répertoire.
     *
     * @param string $controllersDir  Chemin absolu vers le dossier des controllers.
     * @param string $namespace       Namespace PHP correspondant (ex: "App\\Controller").
     */
    public function load(string $controllersDir, string $namespace): void
    {
        if (!is_dir($controllersDir)) {
            return;
        }

        foreach (glob($controllersDir . '/*.php') as $file) {
            $class = $namespace . '\\' . basename($file, '.php');

            if (!class_exists($class)) {
                require_once $file;
            }

            $this->loadFromClass($class);
        }
    }

    /**
     * Enregistre les routes d'un seul controller.
     */
    public function loadFromClass(string $class): void
    {
        $reflector = new \ReflectionClass($class);

        // Préfixe optionnel défini au niveau de la classe
        $classPrefix = $this->getClassPrefix($reflector);

        foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(RouteAttribute::class);

            foreach ($attributes as $attribute) {
                /** @var RouteAttribute $route */
                $route = $attribute->newInstance();

                $path = $classPrefix . $route->path;

                $this->router->add(
                    path: $path,
                    methods: $route->methods,
                    handler: [$class, $method->getName()],
                    name: $route->name,
                );
            }
        }
    }

    private function getClassPrefix(\ReflectionClass $reflector): string
    {
        $attributes = $reflector->getAttributes(RouteAttribute::class);

        if (empty($attributes)) {
            return '';
        }

        /** @var RouteAttribute $classRoute */
        $classRoute = $attributes[0]->newInstance();

        return rtrim($classRoute->path, '/');
    }
}
