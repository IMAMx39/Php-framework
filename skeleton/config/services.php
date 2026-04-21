<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use Framework\Auth\Auth;
use Framework\Auth\Gate;
use Framework\Container\Container;
use Framework\Database\Connection;
use Framework\Logger\Handler\FileHandler;
use Framework\Logger\Logger;
use Framework\Logger\LogLevel;
use Framework\ORM\EntityManager;
use Framework\Queue\Dispatcher;
use Framework\Queue\Driver\FileQueue;
use Framework\Queue\Driver\SyncQueue;
use Framework\Queue\QueueInterface;
use Framework\Queue\Worker;
use Framework\Session\Session;
use Framework\Template\TwigRenderer;

return function (Container $container): void {

    $container->singleton(Connection::class, fn () => new Connection());

    $container->singleton(TwigRenderer::class, function () {
        $debug    = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        $basePath = dirname(__DIR__);

        $renderer = new TwigRenderer(
            templatesPath: $basePath . '/templates',
            cachePath:     $basePath . '/var/cache/twig',
            debug:         $debug,
        );

        $renderer->addGlobal('app', [
            'env'   => $_ENV['APP_ENV']  ?? 'production',
            'debug' => $debug,
            'name'  => $_ENV['APP_NAME'] ?? 'Framework',
        ]);

        return $renderer;
    });

    $container->singleton(Session::class, function (): Session {
        $session = new Session();
        $session->start();
        return $session;
    });

    $container->singleton(Auth::class, fn (Container $c) => new Auth(
        $c->get(Session::class),
        $c->get(UserRepository::class),
    ));

    $container->singleton(Logger::class, function (): Logger {
        $basePath = dirname(__DIR__);
        $debug    = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        $logger = new Logger('app');
        $logger->addHandler(new FileHandler(
            $basePath . '/var/logs/app.log',
            $debug ? LogLevel::DEBUG : LogLevel::INFO,
        ));
        $logger->addHandler(new FileHandler(
            $basePath . '/var/logs/error.log',
            LogLevel::ERROR,
        ));

        return $logger;
    });

    $container->singleton(EntityManager::class, fn (Container $c) => new EntityManager(
        $c->get(Connection::class),
    ));

    $container->singleton(UserRepository::class, fn (Container $c) => new UserRepository(
        $c->get(Connection::class),
    ));

    $container->singleton(QueueInterface::class, function () {
        return ($_ENV['QUEUE_DRIVER'] ?? 'file') === 'sync'
            ? new SyncQueue()
            : new FileQueue(dirname(__DIR__) . '/var');
    });

    $container->singleton(Dispatcher::class, fn (Container $c) => new Dispatcher(
        $c->get(QueueInterface::class),
    ));

    $container->singleton(Worker::class, fn (Container $c) => new Worker(
        $c->get(QueueInterface::class),
        $c,
    ));

    $container->singleton(Gate::class, fn (Container $c) => new Gate(
        fn () => $c->get(Auth::class)->user(),
    ));
};
