<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use Framework\Container\Container;
use Framework\Database\Connection;
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
     * Repositories — injectables via le conteneur ou le constructeur.
     */
    $container->singleton(UserRepository::class, fn (Container $c) => new UserRepository(
        $c->get(Connection::class),
    ));
};
