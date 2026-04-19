<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Container\Container;
use Framework\Database\Connection;
use Framework\Debug\Collector\LogCollector;
use Framework\Debug\Collector\MemoryCollector;
use Framework\Debug\Collector\QueryCollector;
use Framework\Debug\Collector\RequestCollector;
use Framework\Debug\QueryLog;
use Framework\Debug\Toolbar\ToolbarMiddleware;
use Framework\Event\EventDispatcher;
use Framework\Event\ExceptionEvent;
use Framework\Event\KernelEvents;
use Framework\Http\Request;
use Framework\Logger\Logger;
use Framework\Middleware\MiddlewareInterface;
use Framework\Routing\AttributeRouteLoader;
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

        $router     = new Router();
        $dispatcher = new EventDispatcher();

        $this->container->instance(Router::class, $router);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(EventDispatcher::class, $dispatcher);

        $this->loadServices();
        $this->loadAttributeRoutes($router);
        $this->loadRoutes($router);

        $this->kernel = new Kernel($this->container, $router, $dispatcher);

        $this->wireLogger($dispatcher);
        $this->wireDebugToolbar();
    }

    // ------------------------------------------------------------------
    // Câblage automatique Logger → kernel.exception
    // ------------------------------------------------------------------

    private function wireLogger(EventDispatcher $dispatcher): void
    {
        if (!$this->container->has(Logger::class)) {
            return;
        }

        $logger = $this->container->get(Logger::class);

        $dispatcher->on(
            KernelEvents::EXCEPTION,
            function (ExceptionEvent $e) use ($logger): void {
                $t = $e->getThrowable();
                $logger->error($t->getMessage(), [
                    'exception' => $t,
                    'url'       => $e->getRequest()->getUri(),
                    'method'    => $e->getRequest()->getMethod(),
                ]);
            },
            priority: -100,  // après les listeners métier
        );
    }

    // ------------------------------------------------------------------
    // Debug Toolbar — activée uniquement si APP_DEBUG=true
    // ------------------------------------------------------------------

    private function wireDebugToolbar(): void
    {
        if (($_ENV['APP_DEBUG'] ?? 'false') !== 'true') {
            return;
        }

        $request     = Request::fromGlobals();
        $queryLog    = new QueryLog();
        $logCollector = new LogCollector();

        // Attache le QueryLog à la Connection si elle est enregistrée
        if ($this->container->has(Connection::class)) {
            try {
                $this->container->get(Connection::class)->setQueryLog($queryLog);
            } catch (\Throwable) {
                // DB indisponible — on ignore silencieusement
            }
        }

        // Branche le LogCollector sur le Logger si disponible
        if ($this->container->has(Logger::class)) {
            $this->container->get(Logger::class)->addHandler($logCollector);
        }

        $collectors = [
            new RequestCollector($request),
            new QueryCollector($queryLog),
            new MemoryCollector(),
            $logCollector,
        ];

        $this->kernel->addMiddleware(new ToolbarMiddleware($collectors));
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
    // Découverte automatique des routes via attributs PHP 8 #[Route]
    // ------------------------------------------------------------------

    private function loadAttributeRoutes(Router $router): void
    {
        $loader = new AttributeRouteLoader($router);

        $loader->load(
            controllersDir: $this->basePath . '/app/Controller',
            namespace: 'App\\Controller',
        );
    }

    // ------------------------------------------------------------------
    // Chargement des routes manuelles (config/routes.php) — optionnel
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
    // Middlewares globaux
    // ------------------------------------------------------------------

    public function addMiddleware(MiddlewareInterface $middleware): static
    {
        $this->kernel->addMiddleware($middleware);

        return $this;
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

    public function getDispatcher(): EventDispatcher
    {
        return $this->container->get(EventDispatcher::class);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
