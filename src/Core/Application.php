<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Routing\Router;

class Application
{
    private Container $container;
    private Kernel $kernel;

    public function __construct(private readonly string $basePath)
    {
        $this->bootstrap();
    }

    // ------------------------------------------------------------------
    // Bootstrap
    // ------------------------------------------------------------------

    private function bootstrap(): void
    {
        $this->loadEnv();

        $this->container = new Container();

        $router = new Router();
        $this->container->instance(Router::class, $router);
        $this->container->instance(Container::class, $this->container);

        $this->loadServices();
        $this->loadRoutes($router);

        $this->kernel = new Kernel($this->container, $router);
    }

    // ------------------------------------------------------------------
    // Chargement de l'environnement (.env)
    // ------------------------------------------------------------------

    private function loadEnv(): void
    {
        $file = $this->basePath . '/.env';

        if (!file_exists($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2) + [1 => ''];

            $key   = trim($key);
            $value = trim($value, " \t\"\\'");

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    // ------------------------------------------------------------------
    // Chargement des services (config/services.php)
    // ------------------------------------------------------------------

    private function loadServices(): void
    {
        $file = $this->basePath . '/config/services.php';

        if (!file_exists($file)) {
            return;
        }

        $configure = require $file;

        if (is_callable($configure)) {
            $configure($this->container);
        }
    }

    // ------------------------------------------------------------------
    // Chargement des routes (config/routes.php)
    // ------------------------------------------------------------------

    private function loadRoutes(Router $router): void
    {
        $file = $this->basePath . '/config/routes.php';

        if (!file_exists($file)) {
            return;
        }

        $register = require $file;

        if (is_callable($register)) {
            $register($router);
        }
    }

    // ------------------------------------------------------------------
    // Exécution
    // ------------------------------------------------------------------

    public function run(): void
    {
        $request  = Request::fromGlobals();
        $response = $this->kernel->handle($request);
        $response->send();
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
