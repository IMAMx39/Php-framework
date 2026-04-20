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
    /*
     * Base de données — lue depuis DATABASE_URL dans .env.
     * Une seule instance PDO partagée (singleton).
     */
    $container->singleton(Connection::class, fn () => new Connection());

    /*
     * Moteur de templates Twig.
     * - APP_DEBUG=true  → cache désactivé, dump() disponible
     * - APP_DEBUG=false → templates compilés dans var/cache/twig/
     */
    $container->singleton(TwigRenderer::class, function () {
        $debug    = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        $basePath = dirname(__DIR__);

        $renderer = new TwigRenderer(
            templatesPath: $basePath . '/templates',
            cachePath:     $basePath . '/var/cache/twig',
            debug:         $debug,
        );

        // Variable globale {{ app.env }} disponible dans tous les templates
        $renderer->addGlobal('app', [
            'env'   => $_ENV['APP_ENV']  ?? 'production',
            'debug' => $debug,
            'name'  => $_ENV['APP_NAME'] ?? 'Framework',
        ]);

        return $renderer;
    });

    /*
     * Session — une seule instance partagée par requête.
     */
    $container->singleton(Session::class, function (): Session {
        $session = new Session();
        $session->start();

        return $session;
    });

    /*
     * Auth — dépend de Session et de UserRepository.
     */
    $container->singleton(Auth::class, fn (Container $c) => new Auth(
        $c->get(Session::class),
        $c->get(UserRepository::class),
    ));

    /*
     * Logger — écrit dans var/logs/app.log (tous niveaux) et var/logs/error.log (ERROR+).
     * Injecté automatiquement dans les contrôleurs via le conteneur.
     */
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

    /*
     * EntityManager — point d'entrée ORM unique ($this->em dans les contrôleurs).
     */
    $container->singleton(EntityManager::class, fn (Container $c) => new EntityManager(
        $c->get(Connection::class),
    ));

    /*
     * Repositories — injectables via le conteneur ou le constructeur.
     */
    $container->singleton(UserRepository::class, fn (Container $c) => new UserRepository(
        $c->get(Connection::class),
    ));

    /*
     * Queue — FileQueue en prod, SyncQueue si QUEUE_DRIVER=sync.
     * Worker résout les dépendances de handle() via le container.
     */
    $container->singleton(QueueInterface::class, function () {
        $driver = $_ENV['QUEUE_DRIVER'] ?? 'file';

        if ($driver === 'sync') {
            return new SyncQueue();
        }

        return new FileQueue(dirname(__DIR__) . '/var');
    });

    $container->singleton(Dispatcher::class, fn (Container $c) => new Dispatcher(
        $c->get(QueueInterface::class),
    ));

    $container->singleton(Worker::class, fn (Container $c) => new Worker(
        $c->get(QueueInterface::class),
        $c,
    ));

    /*
     * Gate — système d'autorisation (abilities + policies).
     * Le userResolver récupère l'utilisateur depuis Auth (session).
     */
    $container->singleton(Gate::class, fn (Container $c) => new Gate(
        fn () => $c->get(Auth::class)->user(),
    ));
};
